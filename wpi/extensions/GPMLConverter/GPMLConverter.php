<?php

class GPMLConverter{
	public static $gpml2pvjson_path="/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path="/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path="/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path="/nix/var/nix/profiles/default/bin/pvjs";

	function __construct() {
		// Do something
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

cat $gpml_path | \
	$gpml2pvjson_path --id $identifier --pathway-version $version | \
	tee "\$original_json_file" | \
	$jq_path -rc '. | .entityMap[]' | \
	$bridgedb_path enrich $organism dbConventionalName dbId ncbigene ensembl wikidata | \
	$jq_path --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)' > \$entity_map_file;

cat "\$entity_map_file" | \
	$jq_path -rc --slurpfile original_json "\$original_json_file" --slurp '. as \$entity_map | ({pathway: \$original_json[0].pathway, entityMap: \$entity_map[0]})';
TEXT;

		return shell_exec($cmd);
	}

	public static function pvjson2svg($inputs) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		$pvjson = escapeshellarg($inputs["pvjson"]);
		#$pvjson = $inputs["pvjson"];

/*
echo $pvjson | $jq_path .;
echo $pvjson;
printf $pvjson;
printf $pvjson | $jq_path .;
$jq_path <<< $pvjson
printf """$pvjson""" | $jq_path;
{ echo $pvjson; cat - ; } | $jq_path;
( printf """$pvjson"""; cat - ) | $jq_path;
( printf ""$pvjson""; cat - ) | $jq_path;
printf '{"a": 1}' | cat - | $jq_path;
printf '{"a": 1}' | $jq_path;
printf """$pvjson""" | $jq_path;
printf $pvjson | tee "myfile.json" | $jq_path;

#`echo $pvjson` > "\$myfile";

myfile="/tmp/myfile.json";

tmp=$(mktemp -d -t tmp.XXXXXXXXXX);
myfile=\$tmp"/myfile.json";
touch "\$myfile";
$(echo 'wow') > "\$myfile";
echo "\$myfile";

$(printf $pvjson) > \$myfile;

tmp=$(mktemp -d -t tmp.XXXXXXXXXX);
myfile=\$tmp"/myfile.json";
printf $pvjson > \$myfile;
echo \$myfile;

cat \$pvjson_path | $jq_path;



tmp=$(mktemp -d -t tmp.XXXXXXXXXX);
finish () { rm -rf "\$tmp" ; }
trap finish EXIT

pvjson_path=\$tmp"/pvjson.json";
printf $pvjson | tee "myfile1.json" > \$pvjson_path;
cat \$pvjson_path | $jq_path;
//*/

		$cmd = <<<TEXT
tmp=$(mktemp -d -t tmp.XXXXXXXXXX);
finish () { rm -rf "\$tmp" ; }
trap finish EXIT

pvjson_path=\$tmp"/pvjson.json";
printf $pvjson > \$pvjson_path;
cat \$pvjson_path | $jq_path .;
TEXT;

		#return escapeshellcmd($cmd);
		#return shell_exec(escapeshellcmd($cmd));
		#return shell_exec(escapeshellcmd($cmd));
		return shell_exec($cmd);
		#return $pvjson;
	}

}
