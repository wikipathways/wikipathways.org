with import <nixpkgs> {};
let
  # I don't know how this should be done. This is just a rough start.
  # see https://github.com/NixOS/nixpkgs/blob/master/nixos/modules/services/web-servers/apache-httpd/mediawiki.nix
  apache = pkgs.apacheHttpd {extraModules = "mediawiki-1.29.1";};
  GPMLConverter = import ./wpi/extensions/GPMLConverter/default.nix;
  PathwayFinder = import ./wpi/extensions/PathwayFinder/default.nix;
in [
  apache
  pkgs.solr
  pkgs.virtuoso7
  pkgs.jenkins
  GPMLConverter
  PathwayFinder
]
