#!/bin/bash
user="org.example"
domain="example.org"
database="orgexample"

sudo useradd ${user} -b /home -m -U -s /bin/bash

# доступы для пользователя по ключу
sudo -u ${user} mkdir -p /home/${user}/.ssh

ssh-keygen -t dsa -q -f /home/${user}/.ssh/${user}_dsa -N ""
cp /home/${user}/.ssh/${user}_dsa.pub /home/${user}/.ssh/authorized_keys
chown ${user}:${user} /home/${user}/.ssh/authorized_keys
chmod 600 /home/${user}/.ssh/authorized_keys


#В домашней директории создадим каталоги для файлов сервера, логов и временных файлов.
sudo mkdir -p -m 754 /home/${user}/www
sudo mkdir -p -m 777 /home/${user}/tmp
sudo mkdir -p -m 754 /home/${user}/logs

#Предоставим пользователю example права на эти директории:
sudo chown -R ${user}: /home/${user}/www/
sudo chown -R ${user}: /home/${user}/tmp/
sudo chown -R ${user}: /home/${user}/logs/


# Т.к. у нас Nginx работает от пользователя www-data, то он не сможет получить доступ
# к содержимому домашней директории пользователя example, но при создании была создана
# одноименная группа, в нее нам необходимо добавить пользователя www-data.
sudo usermod -a -G ${user} www-data

cat apache.sample | sed -e "s/\[user\]/${user}/g" -e "s/\[domain\]/${domain}/g" > /etc/apache2/sites-available/${user}
cat nginx.sample | sed -e "s/\[user\]/${user}/g" -e "s/\[domain\]/${domain}/g" > /etc/nginx/sites-available/${user}

sudo ln -s /etc/apache2/sites-available/${user} /etc/apache2/sites-enabled/${user}
sudo ln -s /etc/nginx/sites-available/${user} /etc/nginx/sites-enabled/${user}

sudo apache2ctl graceful
sudo service nginx reload

# Настройка ротации логов
echo "/home/${user}/logs/apache*.log {
        weekly
        missingok
        rotate 7
        compress
        delaycompress
        notifempty
        create 640 root adm
        sharedscripts
        postrotate
                /etc/init.d/apache2 reload > /dev/null
        endscript
}" > /etc/logrotate.d/${user}_apache

echo "/home/${user}/logs/nginx*.log {
        daily
        missingok
        rotate 7
        compress
        delaycompress
        notifempty
        create 0640 www-data adm
        sharedscripts
        prerotate
                if [ -d /etc/logrotate.d/httpd-prerotate ]; then \
                        run-parts /etc/logrotate.d/httpd-prerotate; \
                fi; \
        endscript
        postrotate
                [ ! -f /var/run/nginx.pid ] || kill -USR1 `cat /var/run/nginx.pid`
        endscript
}" > /etc/logrotate.d/${user}_nginx

# create database
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

