#!/bin/sh
nix-channel --update
nix-env -u '*'
cd /var/www/dev.wikipathways.org/wpi/extensions/GPMLConverter
node2nix --flatten -6 -i node-packages.json
nix-env -f default.nix -i

rm ../../bin/jq
ln -s $(readlink $(which jq)) ../../bin/

rm ../../bin/gpml2pvjson
ln -s $(readlink $(which gpml2pvjson)) ../../bin/

rm ../../bin/bridgedb
ln -s $(readlink $(which bridgedb)) ../../bin/

rm ../../bin/pvjs
ln -s $(readlink $(which pvjs)) ../../bin/

sudo chown -R www-data:wikipathways ../../bin
sudo chmod -R u+rx-w ../../bin/
sudo chmod -R g+rxw ../../bin/
