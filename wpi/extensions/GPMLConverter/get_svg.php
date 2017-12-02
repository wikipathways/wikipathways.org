<html>
	 <head>
		  <title>PHP Test</title>
	 </head>
	 <body>
		 <?php
		 $jq="/nix/var/nix/profiles/default/bin/jq";
		 $gpml2pvjson="/nix/var/nix/profiles/default/bin/gpml2pvjson";
		 $bridgedb="/nix/var/nix/profiles/default/bin/bridgedb";
		 $pvjs="/nix/var/nix/profiles/default/bin/pvjs";

		 $gpml_file = '/var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/WP2864_79278.gpml';
		 $pathway_identifier = 'WP2864';
		 $pathway_version = '79278';
		 $organism="Human";

		 $cmd = <<<TEXT
original_json_file=$(mktemp)
entity_map_file=$(mktemp)

cat "$gpml_file" | \
       	$gpml2pvjson --id $pathway_identifier --pathway-version $pathway_version | \
	tee "\$original_json_file" | \
	$jq -rc '. | .entityMap[]' | \
	$bridgedb enrich "$organism" dbConventionalName dbId ncbigene ensembl wikidata | \
	$jq --slurp 'reduce .[] as \$entity ({}; .[\$entity.id] = \$entity)' > \$entity_map_file;

cat "\$entity_map_file" | \
	$jq -rc --slurpfile original_json "\$original_json_file" --slurp '. as \$entity_map | ({pathway: \$original_json[0].pathway, entityMap: \$entity_map[0]})' | \
	$pvjs json2svg -s false;
TEXT;

		$unifiedJson = shell_exec($cmd);
		echo $unifiedJson;
		 ?>
	 </body>
</html>
