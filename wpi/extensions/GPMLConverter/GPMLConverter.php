<?php
# TODO do we want to use trigger_error and try/catch/finally, or is it enough to just return false?

function write_to_stream($pipes, $proc) {
	return function($data, $end) use($pipes, $proc) {
		$stdin = $pipes[0];
		$stdout = $pipes[1];
		$stderr = $pipes[2];

		fwrite($stdin, $data);

		if (!isset($end) || $end != true) {
			return write_to_stream($pipes, $proc);
		}

		fclose($stdin);

		$result = stream_get_contents($stdout);
		$info = stream_get_meta_data($stdout);
		$err = stream_get_contents($stderr);

		fclose($stderr);

		if ($info['timed_out']) {
			#trigger_error('pipe timed out', E_USER_NOTICE);
			return false;
		}


		proc_close($proc);

		if ($err) {
			#trigger_error($err, E_USER_NOTICE);
			return false;
		}

		return $result;
	};
};

function create_stream($cmd, $opts = array()) {
	$timeout = $opts["timeout"];

	$proc = proc_open("cat - | $cmd",
		array(
			array("pipe","r"),
			array("pipe","w"),
			array("pipe","w")
		),
		$pipes);

	if (is_resource($proc)) {
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// Any error output will be appended to /tmp/error-output.txt

		if (isset($timeout)) {
			stream_set_timeout($pipes[0], $timeout);
		}

		return write_to_stream($pipes, $proc);
	} else {
		#trigger_error("Error: $proc for $cmd must be a resource.", E_USER_NOTICE);
		return false;
	}
}

class GPMLConverter{
	// TODO is there a better way to define these?
	public static $gpml2pvjson_path="/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path="/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path="/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path="/nix/var/nix/profiles/default/bin/pvjs";

	function __construct() {
		// do something
	}

	public static function gpml2pvjson($gpml, $opts) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if (empty($gpml)) {
			echo "Error: invalid gpml provided:<br>";
			echo $gpml;
			return;
		}

		$identifier = escapeshellarg($opts["identifier"]);
		$version = escapeshellarg($opts["version"]);
		$organism = escapeshellarg($opts["organism"]);

		$toPvjsonCmd = <<<TEXT
$gpml2pvjson_path --id $identifier --pathway-version $version | \
$jq_path -rc '. as {\$pathway} | (.entityMap | .[] |= (.type += if .dbId then [.dbConventionalName + ":" + .dbId] else [] end )) as \$entityMap | {\$pathway, \$entityMap}'
TEXT;
		//TODO: this timeout should be removed when we get async cacheing working
		$streamGpml2Pvjson = create_stream("$toPvjsonCmd", array("timeout" => 4));
		$rawPvjsonString = $streamGpml2Pvjson($gpml, true);

		/* TODO we disabled all unification, because it was overloading the server. We need to do caching or something.
		return $rawPvjsonString;
		//*/

		/*
		//Skip bridgedb unification unless view=widget (i.e., where the unification is useful)
		$view = isset($_GET["view"]) ? $_GET["view"] : "normal";
		if($view != 'widget')
			return $rawPvjsonString;
		//*/

## TODO the enrich method from bridgedbjs is extremely slow when this was
## installed via Nix, but it may have been faster when installed via NPM.
## Regardless, it's currently much slower than xrefsBatch, suggesting the
## batching is not happening below.
#		$enrichCmd = <<<TEXT
#$jq_path -rc '.entityMap[]' | \
#$bridgedb_path enrich $organism dbConventionalName dbId ncbigene ensembl wikidata | \
#$jq_path -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)';
#TEXT;

		$xrefsBatchCmd = <<<TEXT
$jq_path -rc '.entityMap[] | select(has("dbId") and has("dbConventionalName") and .gpmlElementName == "DataNode" and .dbConventionalName != "undefined" and .dbId != "undefined") | .dbConventionalName + "," + .dbId' | \
$bridgedb_path xrefsBatch --organism $organism | \
$jq_path -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.dbConventionalName + ":" + \$entity.dbId] = \$entity)';
TEXT;
		//TODO: this timeout should be removed when we get async cacheing working
		$writeToBridgeDbStream = create_stream("$xrefsBatchCmd", array("timeout" => 4));
		$bridgedbResultString = $writeToBridgeDbStream($rawPvjsonString, true);
		// TODO Are we actually saving any time by doing this instead of just parsing it as JSON?
		if (!$bridgedbResultString || empty($bridgedbResultString) || $bridgedbResultString == '{}' || $bridgedbResultString == '[]') {
			return $rawPvjsonString;
		}

# TODO should we use this? NOTE: if yes, be sure to update the timeout!
#		try{
#			$writeToBridgeDbStream = create_stream("$xrefsBatchCmd", array("timeout" => 30));
#			$bridgedbResultString = $writeToBridgeDbStream($rawPvjsonString, true);
#		} catch(Exception $e) {
#			return $rawPvjsonString;
#		}

		$bridgedbResult = json_decode($bridgedbResultString);
		$pvjson = json_decode($rawPvjsonString);
		$pathway = $pvjson->pathway;
		$entityMap = $pvjson->entityMap;
		foreach ($entityMap as $key => $value) {
			if (property_exists($value, 'dbConventionalName') && property_exists($value, 'dbId')) {
				$xrefId = $value->dbConventionalName.":".$value->dbId;
				if (property_exists($bridgedbResult, $xrefId)) {
					$mapper = $bridgedbResult->$xrefId;
					if (property_exists($mapper, 'xrefs')) {
						$xrefs = $mapper->xrefs;
						foreach ($xrefs as $xref) {
							if (property_exists($xref, 'isDataItemIn') && property_exists($xref, 'dbId')) {
								$datasource = $xref->isDataItemIn;
								if (property_exists($datasource, 'preferredPrefix')) {
									array_push($value->type, "$datasource->preferredPrefix:$xref->dbId");
								}
							}
						}
					}
				}
			}
		}

		return json_encode(array("pathway"=>$pathway, "entityMap"=>$entityMap));
	}

	public static function pvjson2svg($pvjson, $opts) {
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if (empty($pvjson) || trim($pvjson) == '{}') {
			echo "Error: invalid pvjson provided:<br>";
			echo $pvjson;
			return;
		}

		$static = isset($opts["static"]) ? $opts["static"] : false;

		$streamPvjsonToSvg = create_stream("$pvjs_path json2svg -s $static", array("timeout" => 2));
		return $streamPvjsonToSvg($pvjson, true);
	}

}
