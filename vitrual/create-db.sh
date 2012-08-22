#!/bin/bash
user="org.example"
database="orgexample4"
domain="example.org"

genpass() { local h x y;h=${1:-14};x=( {a..z} {A..Z} {0..9} );y=$(echo ${x[@]} | tr ' ' '\n' | shuf -n$h | xargs);echo -e "${y// /}"; }
pass=`genpass`
passreader=`genpass`

echo "Database credentials
host: localhost
name: ${database}
Admin user
  login: ${database}
  password: ${pass}
Read-only user
  login: ${database}rou
  password: ${passreader}

" >>  /home/${user}/mysql-credentials.txt

sudo mysqladmin --defaults-file=/etc/mysql/debian.cnf create ${database}
sudo mysql --defaults-file=/etc/mysql/debian.cnf ${database} -e "CREATE USER '${database}'@'localhost' IDENTIFIED BY '${pass}'; GRANT ALL ON ${database}.* TO '${database}'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql --defaults-file=/etc/mysql/debian.cnf ${database} -e "CREATE USER '${database}rou'@'localhost' IDENTIFIED BY '${passreader}'; GRANT SELECT ON ${database}.* TO '${database}rou'@'localhost'; FLUSH PRIVILEGES;"

