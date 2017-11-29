# How to install on dev.wikipathways.org as single-user

These instructions use my username, ariutta, but substitute your own username if you do this.

Install nix
```sh
curl https://nixos.org/nix/install | sh
echo '. /home/ariutta/.nix-profile/etc/profile.d/nix.sh' » ~/.bashrc
. /home/ariutta/.nix-profile/etc/profile.d/nix.sh
```

Make sure env variables are set
```sh
echo '. /home/ariutta/.nix-profile/etc/profile.d/nix.sh' » ~/.bashrc
. /home/ariutta/.nix-profile/etc/profile.d/nix.sh
```

TODO: this is single-user. How does that relate to running it as www-data for Mediawiki?
See https://nixos.org/nix/manual/#ssec-multi-user

Update channel. TODO do we need both lines 1 and 2 below?
```sh
nix-channel --update
nix-channel --update nixpkgs
nix-env -u '*'
```

Install converter dependencies (non-NPM):
```sh
nix-env -i jq
nix-env -i nodejs
nix-env -f '<nixpkgs>' -iA nodePackages.node2nix
```

Install converter dependencies (NPM).
```sh
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
node2nix --flatten -6 -i node-packages.json
nix-env -f default.nix -iA '"gpml2pvjson-3.0.0-3"'
nix-env -f default.nix -iA '"@wikipathways/pvjs-4.0.0-4"'
nix-env -f default.nix -iA '"bridgedb-6.0.0-17"'
```

Executables will be here:
/nix/var/nix/profiles/default/bin/gpml2pvjson
/nix/var/nix/profiles/default/bin/pvjs
/nix/var/nix/profiles/default/bin/bridgedb

## Permissions
Make sure you're part of the "wikipathways" group.
```sh
sudo adduser ariutta wikipathways
```
wikipathways group members should be able to read, write and execute files.
www-data should only be able to read and execute files. It's not secure for it to be able to write the files.
```sh
sudo chgrp -R wikipathways /nix/var/nix/profiles/default/bin/ /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
sudo chown -R www-data /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
sudo chmod -R g+rwx /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
sudo chmod -R u+rx-w /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter/
```

Convert some data
```sh
cat WP2864_79278.gpml | /nix/var/nix/profiles/default/bin/gpml2pvjson
sh convert.sh "WP2864_79278.gpml" "WP2864" "79278" "Human"
sh convert.sh "/var/www/dev.wikipathways.org/images/0/00/WP1707_77754.gpml" "WP1707" "77754" "Mycobacterium tuberculosis"
```

Test commands
```sh
sh test_bridgedb.sh "Human" "Ensembl" "ENSG00000111186"
sh test_gpml2pvjson.sh "WP2864_79278.gpml" "WP2864" "79278" "Human"
sh test_kaavio.sh "WP2864.json" "WP2864" "79278" "Human"
```
