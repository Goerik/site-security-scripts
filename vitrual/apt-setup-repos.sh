#!/bin/bash
cd

sudo gpg --keyserver  hkp://keys.gnupg.net --recv-keys 1C4CBDCDCD2EFD2A
sudo gpg -a --export CD2EFD2A | sudo apt-key add -


sudo echo '
deb http://repo.percona.com/apt squeeze main
deb-src http://repo.percona.com/apt squeeze main

' > /etc/apt/sources.list.d/percona.list


sudo echo '
deb http://mirror.yandex.ru/debian-backports/ squeeze-backports main contrib non-free
deb-src http://mirror.yandex.ru/debian-backports/ squeeze-backports main contrib non-free

' > /etc/apt/sources.list.d/backports.list

sudo echo '
Package: *
Pin: release v=1.0,o=Percona Development Team,n=squeeze,l=percona,c=main
Pin-Priority: 700

' > /etc/apt/preferences.d/percona


sudo aptitude update

