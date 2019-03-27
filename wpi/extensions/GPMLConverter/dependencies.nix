with import <nixpkgs> { config.allowUnfree = true; };

let
  nodePackagesCustom = import ./default.nix {};
in [
  nodePackagesCustom."gpml2pvjson-3.0.0-5"
  nodePackagesCustom."@wikipathways/pvjs-4.0.0-7"
  nodePackagesCustom."bridgedb-6.0.0-18"
]
