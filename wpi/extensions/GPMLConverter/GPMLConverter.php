<?php
// TODO do we need wpi.php for anything?
#require_once('wpi.php');

function writeToPipe($pipes, $enable_errors, $proc) {
	return function($data, $end) use($pipes, $enable_errors, $proc) {
		fwrite($pipes[0], $data);
		if (!isset($end) || $end != true) {
			return writeToPipe($pipes, $enable_errors, $proc);
		}

		fclose($pipes[0]);
		$result = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		if ($enable_errors) {
			$err = stream_get_contents($pipes[2]);
			fclose($pipes[2]);
		}

		proc_close($proc);

		if ($enable_errors && $err) {
			return $err;
		}

		return $result;
	};
};


function createPipe($cmd, $opts = array()) {
	$enable_errors = isset($opts['enable_errors']) ? $opts['enable_errors'] : false;

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

		return writeToPipe($pipes, $enable_errors, $proc);
	} else {
		return "Error: $proc for $cmd must be a resource.";
	}
}

class GPMLConverter{
	// TODO is there a better way to define these?
	public static $gpml2pvjson_path="/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path="/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path="/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path="/nix/var/nix/profiles/default/bin/pvjs";

	public static $enable_errors=false;

	function __construct() {
		// do something
	}

	public static function gpml2pvjson($gpml, $opts) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$identifier = escapeshellarg($opts["identifier"]);
		$version = escapeshellarg($opts["version"]);
		$organism = escapeshellarg($opts["organism"]);

		$writeToPvjsonPipe = createPipe("$gpml2pvjson_path --id $identifier --pathway-version $version");
		$rawPvjsonString = $writeToPvjsonPipe($gpml, true);

		$enrichCmd = <<<TEXT
$jq_path -rc '. | .entityMap[]' | \
$bridgedb_path enrich $organism dbConventionalName dbId ncbigene ensembl wikidata | \
$jq_path --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)';
TEXT;
		$writeToEntityMapPipe = createPipe("$enrichCmd");
		$entityMap = json_decode($writeToEntityMapPipe($rawPvjsonString, true));

		if (!isset($entityMap) || $entityMap == null || !$entityMap) {
			return $rawPvjsonString;
		}

		$pathway = json_decode($rawPvjsonString)->pathway;
		$output = array("pathway"=>$pathway, "entityMap"=>$entityMap);
		return json_encode($output);
	}

	public static function pvjson2svg($pvjson, $opts) {
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$static = isset($opts["static"]) ? $opts["static"] : false;

		# TODO should we parse with jq first for safety? If so, how, b/c the following command hangs the server:
		#$jq_path . | $pvjs_path json2svg -s false;

		$writeToSvgPipe = createPipe("$pvjs_path json2svg -s $static");
		return $writeToSvgPipe($pvjson, true);
	}

}
