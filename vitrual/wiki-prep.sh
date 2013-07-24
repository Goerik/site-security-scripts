#!/bin/bash

user="ru.credit-crm.local.nord"

domain="nord.local.credit-crm.ru"
database="nordcrm"

dbcred=$(cat /home/${user}/mysql-credentials.txt);
sshcred=$(cat /home/${user}/.ssh/${user}_dsa);

echo "h1. Сервер разработки

DevUrl: http://$domain

h2. Доступ по ssh ключу сайта

Приватный ключ
<pre>
$sshcred
</pre>

Пример команд для подключения
<pre>
ssh -i /path/to/file/${user}_dsa ${user}@$domain
sshfs -o IdentityFile=/path/to/file/${user}_dsa ${user}@$domain:/home/${user}/www /path/to/local/folder
</pre>

h2. Доступ к БД

<pre>
$dbcred
</pre>"

