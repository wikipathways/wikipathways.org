# nix-install: Perform a multi-user installation of Nix on a Debian systems

To install, cd to the directory containing the script `nix-install-deb-multi-user` and run it as root:
```sh
cd wpi/extensions/GPMLConverter/nix-install
sudo -i su -c $(pwd)/nix-install-deb-multi-user
```

## TODO

Right now, `nix-install-deb-multi-user` has the code for downloading the binary. It would be better to use [this script](https://nixos.org/nix/install) to get the binary. The only thing that needs to be changed is this line:
`script=$(echo "$unpack"/*/install)`

That command should be updated so it runs `nix-install-deb-multi-user` when the detected system is Debian.

Also, the downloaded tar archive includes a multi-user install script for macOS. Much of the logic from that script, such as telling the user to remove Nix content from local .profile/.bashrc files could be ported to `nix-install-deb-multi-user`.
