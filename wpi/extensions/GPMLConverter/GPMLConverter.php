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

		$writeToPvjsonPipe = createPipe("$toPvjsonCmd", array("enable_errors"=>false));
		$rawPvjsonString = $writeToPvjsonPipe($gpml, true);

		$enrichCmd = <<<TEXT
$jq_path -rc '. | .entityMap[]' | \
$bridgedb_path enrich $organism dbConventionalName dbId ncbigene ensembl wikidata | \
$jq_path -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)';
TEXT;
		$writeToEntityMapPipe = createPipe("$enrichCmd");
		$entityMapString = $writeToEntityMapPipe($rawPvjsonString, true);

		#if (count(get_object_vars($entityMap)) == 0 || trim($entityMapString) == '{}') {}
		if (trim($entityMapString) == '{}') {
			return $rawPvjsonString;
		}

		$entityMap = json_decode($entityMapString);
		$pathway = json_decode($rawPvjsonString)->pathway;

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

		$writeToSvgPipe = createPipe("$pvjs_path json2svg -s $static", array("enable_errors"=>false));
		return $writeToSvgPipe($pvjson, true);
	}

}
