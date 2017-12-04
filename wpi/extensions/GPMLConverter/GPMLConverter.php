<?php

class GPMLConverter{
	public static $gpml2pvjson_path="/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path="/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path="/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path="/nix/var/nix/profiles/default/bin/pvjs";

	function __construct() {
		// Do something
	}

	public static function gpml2pvjson1($gpml, $opts) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$identifier = escapeshellarg($opts["identifier"]);
		$version = escapeshellarg($opts["version"]);
		$organism = escapeshellarg($opts["organism"]);

		#$proc = proc_open("cat - | $pvjs_path json2svg -s $static;",
		$proc = proc_open("cat - | $gpml2pvjson_path --id $identifier --pathway-version $version;",
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

			fwrite($pipes[0], $gpml);
			fclose($pipes[0]);

			$result = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			/*
			$err = stream_get_contents($pipes[2]);
			fclose($pipes[2]);
			//*/

			proc_close($proc);

			/*
			if ($err) {
				return $err;
			}
			//*/
			

			return $result;
		} else {
			return "Error: $proc in GPMLConverter->gpml2pvjson must be a resource.";
		}
	}

	public static function gpml2pvjson($inputs) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$gpml_path = escapeshellarg($inputs["gpml_path"]);
		$identifier = escapeshellarg($inputs["identifier"]);
		$version = escapeshellarg($inputs["version"]);
		$organism = escapeshellarg($inputs["organism"]);

		$cmd = <<<TEXT
tmp=$(mktemp -d -t tmp.XXXXXXXXXX);

finish () { rm -rf "\$tmp" ; }
trap finish EXIT

original_json_file=\$tmp"/original_json_file.json";
entity_map_file=\$tmp"/entity_map_file.json";

echo \$original_json_file;
cat $gpml_path | \
	$gpml2pvjson_path --id $identifier --pathway-version $version | \
	tee "\$original_json_file" | \
	$jq_path -rc '. | .entityMap[]' | \
	$bridgedb_path enrich $organism dbConventionalName dbId ncbigene ensembl wikidata | \
	$jq_path --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)' > \$entity_map_file;

cat "\$entity_map_file" | \
	$jq_path -rc --slurpfile original_json "\$original_json_file" --slurp '. as \$entity_map | ({pathway: \$original_json[0].pathway, entityMap: \$entity_map[0]})';
TEXT;

		$pvjson = shell_exec($cmd);
		
		if ($pvjson === null || $pvjson === '' || $pvjson === '{"pathway":null,"entityMap":{}}' || json_decode($pvjson)->pathway == null) {
			// TODO should we log an error?
			return NULL;
		}

		return $pvjson;
	}

	public static function pvjson2svg($pvjson, $opts) {
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$static = $opts["static"] == false;

		# TODO should we parse with jq first for safety? If so, how, b/c the following hangs:
		#cat - | $jq_path . | $pvjs_path json2svg -s false;
		$proc = proc_open("cat - | $pvjs_path json2svg -s $static;",
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

			fwrite($pipes[0], $pvjson);
			fclose($pipes[0]);

			$result = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			proc_close($proc);

			return $result;
		} else {
			return "Error: $proc in GPMLConverter->json2svg must be a resource.";
		}
	}

}
