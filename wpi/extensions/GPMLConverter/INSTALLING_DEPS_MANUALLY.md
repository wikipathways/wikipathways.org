# GPMLConverter

## Install

Install nix as multi-user. Make sure everything's up-to-date:
```sh
nix-channel --update
nix-env -u '*'
```

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
