# How to install on dev.wikipathways.org as multi-user

## Get or create binary
### Download binary
```sh
sudo su root
curl https://nixos.org/nix/install > nix-download-binary
```

Open `nix-download-binary` and change the install script to use:
```sh
cp nix-install-deb-multi-user "$unpack/nix-1.11.15-x86_64-linux/install/install-deb-multi-user"
script=$(echo "$unpack"/nix-1.11.15-x86_64-linux/install/install-deb-multi-user)
#script=$(echo "$unpack"/*/install)
```

Make `nix-download-binary` executable and run it:
```sh
chmod nix-download-binary u +rwx
sh nix-download-binary
```

### Install from source (if download above doesn't work)
If you cannot download a binary, you'll need to install from source.

Install [pre-reqs](https://nixos.org/nix/manual/#sec-prerequisites-source).

```sh
perl --version # must be >= 5.8
which bzip2 # must be installed
which gcc # must be installed
which make # must be installed
sudo apt-get update
sudo apt-get install pkg-config sqlite3 libsqlite3-dev
```

rest of this not completed yet...

```sh
groupadd -r nixbld
for n in $(seq 1 10); do useradd -c "Nix build user $n" \
	    -d /var/empty -g nixbld -G nixbld -M -N -r -s "$(which nologin)" \
	    nixbld$n; done
bash <(curl https://nixos.org/nix/install)
. /root/.nix-profile/etc/profile.d/nix.sh
chown -R root:nixbld /nix/store
sudo chmod 1775 /nix/store
# create an init.d script for nix-daemon.
# for Ubuntu, see the one I have at /etc/init.d/nix-daemon on dev.wikipathways.org server
# create symlinks:
cd /etc/rc2.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc3.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc4.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc5.d
ln -s ../init.d/nix-daemon S60nix-daemon
sudo mkdir -p -m 1777 /nix/var/nix/profiles/per-user
sudo mkdir -p -m 1777 /nix/var/nix/gcroots/per-user
echo "build-users-group = nixbld" >> /etc/nix/nix.conf

update-rc.d nix-daemon

sudo -i
# cd /etc/rc2.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc3.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc4.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc5.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# exit

```

# How to install on dev.wikipathways.org as root, single-user
The section below isn't working yet. See also this: http://sandervanderburg.blogspot.com/2013/06/setting-up-multi-user-nix-installation.html

```sh
groupadd -r nixbld
for n in $(seq 1 10); do useradd -c "Nix build user $n" \
	    -d /var/empty -g nixbld -G nixbld -M -N -r -s "$(which nologin)" \
	    nixbld$n; done
bash <(curl https://nixos.org/nix/install)
. /root/.nix-profile/etc/profile.d/nix.sh
chown -R root:nixbld /nix/store
sudo chmod 1775 /nix/store
# create an init.d script for nix-daemon.
# for Ubuntu, see the one I have at /etc/init.d/nix-daemon on dev.wikipathways.org server
# create symlinks:
cd /etc/rc2.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc3.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc4.d
ln -s ../init.d/nix-daemon S60nix-daemon
cd ../rc5.d
ln -s ../init.d/nix-daemon S60nix-daemon
sudo mkdir -p -m 1777 /nix/var/nix/profiles/per-user
sudo mkdir -p -m 1777 /nix/var/nix/gcroots/per-user
echo "build-users-group = nixbld" >> /etc/nix/nix.conf

update-rc.d nix-daemon

sudo -i
# cd /etc/rc2.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc3.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc4.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# cd ../rc5.d
# ln -s ../init.d/nix-daemon S60nix-daemon
# exit

```

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
. /home/ariutta/.nix-profile/etc/profile.d/nix.sh
```

TODO: this is single-user. How does that relate to running it as www-data for Mediawiki?
See https://nixos.org/nix/manual/#ssec-multi-user

```sh
nix-channel --update
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
nix-env -f default.nix -i
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
