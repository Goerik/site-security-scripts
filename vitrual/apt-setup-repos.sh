#!/bin/bash
cd

sudo gpg --keyserver  hkp://keys.gnupg.net --recv-keys 1C4CBDCDCD2EFD2A
sudo gpg -a --export CD2EFD2A | sudo apt-key add -


sudo echo '
deb http://repo.percona.com/apt wheezy main
deb-src http://repo.percona.com/apt wheezy main

' > /etc/apt/sources.list.d/percona.list



sudo echo '
Package: *
Pin: release v=1.0,o=Percona Development Team,n=wheezy,l=percona,c=main
Pin-Priority: 700

' > /etc/apt/preferences.d/percona


sudo aptitude update

