# GPMLConverter

## Install

### nix (multi-user)
cd to the directory containing the script `nix-install-deb-multi-user` and run it as root:
```sh
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
sudo -i su -c $(pwd)/nix-install-deb-multi-user
```

### use nix to install GPMLConverter Dependencies

To install automatically, run the `install` script:
```sh
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
sudo -i $(pwd)/install
```

Otherwise, manually install following the instructions in INSTALLING_DEPS_MANUALLY.md.

If Nix permissions get messed up, this command will restore them:
```sh
sudo chmod -R o+rx /nix/store/
```

Executables will be located here:
> /nix/var/nix/profiles/default/bin/gpml2pvjson
>
> /nix/var/nix/profiles/default/bin/pvjs
>
> /nix/var/nix/profiles/default/bin/bridgedb

## Use: convert some data

```sh
curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=json" | jq -r .data | base64 --decode | gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=xml" | xpath "*/ns1:data/text()" | base64 --decode | gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "https://cdn.rawgit.com/wikipathways/pvjs/e47ff1f6/test/input-data/troublesome-pathways/WP1818_73650.gpml" | gpml2pvjson --id "http://identifiers.org/wikipathways/WP1818" --pathway-version "73650" > "WP1818_73650.json"

bridgedb xrefs "Human" "Ensembl" "ENSG00000111186"
pvjs json2svg "WP1818_73650.json"
```
