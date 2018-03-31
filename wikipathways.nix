with import <nixpkgs> {};
let
  GPMLConverter = import ./wpi/extensions/GPMLConverter/default.nix;
  PathwayFinder = import ./wpi/extensions/PathwayFinder/default.nix;
in [
  pkgs.apacheHttpd
  # see https://github.com/NixOS/nixpkgs/blob/master/nixos/modules/services/web-servers/apache-httpd/mediawiki.nix
  pkgs.mediawiki-1.29.1
  pkgs.solr
  GPMLConverter
  PathwayFinder
]
