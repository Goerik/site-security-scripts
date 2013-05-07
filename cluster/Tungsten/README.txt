Tungsten replicator home URL: https://code.google.com/p/tungsten-replicator/
Read wiki first!
# ubuntu 12.04 install


# Install mysql / percona first
sudo gpg --keyserver  hkp://keys.gnupg.net --recv-keys 1C4CBDCDCD2EFD2A
sudo gpg -a --export CD2EFD2A | sudo apt-key add -

sudo echo '
deb http://repo.percona.com/apt squeeze main
deb-src http://repo.percona.com/apt squeeze main

' > /etc/apt/sources.list.d/percona.list

sudo echo '
Package: *
Pin: release v=1.0,o=Percona Development Team,n=squeeze,l=percona,c=main
Pin-Priority: 700

' > /etc/apt/preferences.d/percona

sudo aptitude update

# install system packages
aptitude install sharutils htop sudo mc
aptitude install openjdk-6-jre

# Установка mysql/percona
aptitude install percona-server-server percona-toolkit percona-xtrabackup percona-server-client

# enable extended functions (for percona-toolkit)
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE FUNCTION fnv1a_64 RETURNS INTEGER SONAME 'libfnv1a_udf.so'"
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE FUNCTION fnv_64 RETURNS INTEGER SONAME 'libfnv_udf.so'"
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "CREATE FUNCTION murmur_hash RETURNS INTEGER SONAME 'libmurmur_udf.so'"

# Follow Tungsten pre requisite requirements
https://code.google.com/p/tungsten-replicator/wiki/InstallationPreRequisites


# remove old tungsten installations (if needed)
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "DROP DATABASE tungsten_alpha"
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "DROP DATABASE tungsten_bravo"
sudo mysql --defaults-file=/etc/mysql/debian.cnf -e "DROP DATABASE tungsten_charlie"
sudo rm -rf /opt/tungsten/*

# create tungsten user 
useradd tungsten -Gmysql -s /bin/bash -m
passwd tungsten

# add to sudoers
visudo

# tungsten ALL=(ALL)    NOPASSWD: ALL


# mysql settings
character-set-server=utf8
init-connect='SET NAMES utf8;'
server-id=1
innodb-file-per-table=1
innodb-flush-method=O_DIRECT
max_allowed_packet = 52M
innodb-thread-concurrency=0
default-storage-engine=innodb
innodb_flush_log_at_trx_commit=2


# create mysql replicator user >
grant all on *.* to tungsten@'%' identified by '0jmbdM345wG345od' with grant option;
flush privileges;


# install additional pre-reqs
sudo aptitude -y install ruby1.8
sudo aptitude -y install libopenssl-ruby1.8


# get and install tungsten
# READ official WIKI FIRST!!!
# Multi-master configuration

wget https://tungsten-replicator.googlecode.com/files/tungsten-replicator-2.0.7-278.tar.gz
tar -xzf tungsten-replicator-2.0.7-278.tar.gz
VALIDATE_ONLY=1 ./cookbook/install_all_masters.sh

#if problems, try VERBOSE'
VERBOSE=1 VALIDATE_ONLY=1 ./cookbook/install_all_masters.sh

# check replication status
/opt/tungsten/tungsten/tungsten-replicator/bin/trepctl services

#install service
sudo ln -s /opt/tungsten/tungsten/tungsten-replicator/bin/replicator /etc/init.d/replicator
sudo /usr/lib/insserv/insserv -v /etc/init.d/replicator

# configure autoincrement for offline mode in /etc/mysql/my.cnf
# for 2 node configuration

# first mysql server
auto_increment_increment = 2
auto_increment_offset = 1

# second mysql server
auto_increment_increment = 2
auto_increment_offset = 2

