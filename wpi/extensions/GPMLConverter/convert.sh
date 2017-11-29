gpml_file=$1
pathway_identifier=$2
pathway_version=$3
organism=$4

gpml2pvjson="/nix/var/nix/profiles/default/bin/gpml2pvjson"
bridgedb="/nix/var/nix/profiles/default/bin/bridgedb"
pvjs="/nix/var/nix/profiles/default/bin/pvjs"

#gpml2pvjson="npm run --silent gpml2pvjson -- "
#bridgedb="npm run --silent bridgedb -- "
#pvjs="npm run --silent pvjs -- "

#gpml2pvjson="./node_modules/.bin/gpml2pvjson"
#bridgedb="./node_modules/.bin/bridgedb"
#pvjs="./node_modules/.bin/pvjs"

#dir="/"$pathway_identifier
dir="/var/www/dev.wikipathways.org/pathways/"$pathway_identifier
mkdir -p $dir
path_stub=$dir"/"$pathway_version

original_json_file=$path_stub".original.json"
unified_json_file=$path_stub".unified.json"

original_svg_file=$path_stub".original.svg"
unified_svg_file=$path_stub".unified.svg"

entity_map_file=$(mktemp)

cat "$gpml_file" | \
       	$gpml2pvjson --id $pathway_identifier --pathway-version $pathway_version | \
	tee "$original_json_file" | \
	jq -rc '. | .entityMap[]' | \
	$bridgedb enrich "$organism" dbConventionalName dbId ncbigene ensembl wikidata | \
	jq --slurp 'reduce .[] as $entity ({}; .[$entity.id] = $entity)' \
	> "$entity_map_file"

cat "$entity_map_file" | \
	jq -rc --slurpfile original_json "$original_json_file" --slurp '. as $entity_map | ({pathway: $original_json[0].pathway, entityMap: $entity_map[0]})' \
	> "$unified_json_file"

rm "$entity_map_file"

cat "$original_json_file" | $pvjs json2svg --static false > "$original_svg_file"
cat "$unified_json_file" | $pvjs json2svg --static false > "$unified_svg_file"
