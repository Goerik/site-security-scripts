#!/bin/bash
user="org.example.sub"
domain="sub.example.org"

cd /root/install-scripts/

sudo useradd ${user} -b /home -m -U -s /bin/false

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
