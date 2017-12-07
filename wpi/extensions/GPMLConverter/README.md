# GPMLConverter

## Install

### nix (multi-user)
cd to the directory containing the script `nix-install-deb-multi-user` and run it as root:
```sh
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
sudo -i su -c $(pwd)/nix-install-deb-multi-user
```

Make sure everything's up-to-date:
```sh
nix-channel --update
nix-env -u '*'
```

### GPMLConverter Dependencies

Install non-NPM dependencies:
```sh
nix-env -i jq
nix-env -i nodejs
nix-env -f '<nixpkgs>' -iA nodePackages.node2nix
```

Install NPM dependencies:
```sh
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
node2nix --flatten -6 -i node-packages.json
nix-env -f default.nix -i
```

Executables will be located here:
> /nix/var/nix/profiles/default/bin/gpml2pvjson
> /nix/var/nix/profiles/default/bin/pvjs
> /nix/var/nix/profiles/default/bin/bridgedb

## Permissions
Make sure you're part of the "wikipathways" group.
```sh
sudo adduser ariutta wikipathways
```
wikipathways group members should be able to read, write and execute files.
www-data should only be able to read and execute files. It's not secure for it to be able to write the files.
```sh
sudo chown -R www-data:wikipathways /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
sudo chmod -R g+rwx /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
sudo chmod -R u+rx-w /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
```

## Usage: convert some data

```sh
curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=json" | jq -r .data | base64 --decode | gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "http://webservice.wikipathways.org/getPathwayAs?fileType=xml&pwId=WP554&revision=77712&format=xml" | xpath "*/ns1:data/text()" | base64 --decode | gpml2pvjson --id "http://identifiers.org/wikipathways/WP554" --pathway-version "77712"

curl "https://cdn.rawgit.com/wikipathways/pvjs/e47ff1f6/test/input-data/troublesome-pathways/WP1818_73650.gpml" | gpml2pvjson --id "http://identifiers.org/wikipathways/WP1818" --pathway-version "73650" > "WP1818_73650.json"

bridgedb xrefs "Human" "Ensembl" "ENSG00000111186"
pvjs json2json "WP1818_73650.json"
```
