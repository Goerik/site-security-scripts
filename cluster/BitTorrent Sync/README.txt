Installation
# ubuntu 12.04 install

URL: http://labs.bittorrent.com/experiments/sync/get-started.html

mkdir bt
cd bt
wget http://btsync.s3-website-us-east-1.amazonaws.com/btsync_x64.tar.gz
tar -xzf btsync_x64.tar.gz
cp ./btsync /usr/bin/btsync
chmod +x /usr/bin/btsync

mkdir  /home/cluster/pids
chmod 777  /home/cluster/pids

mkdir /var/bt/.sync
chmod 777 /var/bt/.sync

sudo /usr/lib/insserv/insserv -v /etc/init.d/btsyncd
