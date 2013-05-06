# ubuntu 12.04 install
sudo aptitude install -y csync2
sudo csync2 -k /etc/csync2.cluster.key 
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 -keyout /etc/csync2_ssl_key.pem -out /etc/csync2_ssl_cert.pem

cat /etc/csync2.cluster.key 

mkdir /var/csync2/

sudo echo "szNWf8SXmZEPCVer6jQ_OnZ_FVNOYmnh.vGG1mh_Gn4_OA1etYX0AXb5fzW759Rj
" > /etc/csync2.cluster.key 


sudo echo "group all {
host cluster1;
host cluster2;
host cluster3;

key /etc/csync2.cluster.key;

include /etc/csync2.cfg;
include /var/csync2/*;

auto younger;
}

" > /etc/csync2.cfg

crontab entry:
#*/5 * * * * /usr/sbin/csync2 -x >/dev/null 2>&1
