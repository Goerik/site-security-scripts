#!/bin/bash

# Системные пакеты
aptitude install sharutils htop sudo mc

# Установка mysql/percona
aptitude install percona-server-server percona-toolkit percona-xtrabackup percona-server-client

# Установка php
aptitude install apache2-mpm-itk libapache2-mod-rpaf php5 libapache2-mod-php5 php5-mysql php5-snmp php5-gd php5-imagick php5-recode php5-xmlrpc php5-xsl php5-mcrypt php5-curl php-pear php5-imap php5-mysql php-apc

# Установка nginx
aptitude install nginx-full

# Удаляем модуль php5-suhosin
aptitude purge php5-suhosin

# Включаем mod_rewrite
a2enmod rewrite

apache2ctl restart

rm /etc/apache2/sites-enabled/*
rm /etc/nginx/sites-enabled/*

# Апач перевесить на 127.0.0.1:8080
sed -i -e 's/80/8080/g' -e 's/NameVirtualHost \*/NameVirtualHost 127.0.0.1/g' /etc/apache2/ports.conf
apache2ctl restart
service nginx restart

#Конфигурирование сервера MySQL
#копируем конфиг, т. к. в перконе он по умолчанию отсутствует
cp /usr/share/mysql/my-medium.cnf /etc/mysql/my.cnf
service mysql restart

#Добавляем кодировку по-умолчанию UTF-8
#В файл /etc/mysql/my.cnf вставляем строки 
#в секцию [mysqld]
#character-set-server=utf8
#init-connect='SET NAMES utf8;'
#в секцию [mysql]
#default-character-set=utf8
#Перезапускаем mysqld:
#$ sudo service mysql restart


