#!/bin/bash

# Системные пакеты
aptitude install sharutils htop sudo mc

# Установка mysql/percona
aptitude install percona-server-server percona-toolkit percona-xtrabackup percona-server-client

# Установка php
aptitude install apache2-mpm-itk libapache2-mod-rpaf php5 libapache2-mod-php5 php5-mysql php5-snmp php5-gd php5-imagick php5-recode php5-xmlrpc php5-xsl php5-mcrypt php5-curl php-pear php5-imap php5-mysql

# Установка nginx
aptitude install nginx-naxsi

# Апач перевесить на 127.0.0.1:8080
/etc/apache2/ports.conf


