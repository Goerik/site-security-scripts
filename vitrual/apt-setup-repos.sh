#!/bin/bash
cd

sudo gpg --keyserver  hkp://keys.gnupg.net --recv-keys 1C4CBDCDCD2EFD2A
sudo gpg -a --export CD2EFD2A | sudo apt-key add -


sudo echo '
deb http://repo.percona.com/apt squeeze main
deb-src http://repo.percona.com/apt squeeze main

' >> /etc/apt/sources.list.d/percona.list


wget http://www.dotdeb.org/dotdeb.gpg
cat dotdeb.gpg | sudo apt-key add -

sudo echo '
deb http://packages.dotdeb.org squeeze all
deb-src http://packages.dotdeb.org squeeze all

' >> /etc/apt/sources.list.d/dotdeb.list


sudo aptitude update

