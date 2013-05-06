#!/bin/bash
# (C) Copyright 2012 Continuent, Inc - Released under the New BSD License
# Version 1.0.3 - 2012-11-19

# User defined values for the cluster to be installed.

export TUNGSTEN_BASE=/opt/tungsten
export DATABASE_USER=tungsten
export BINLOG_DIRECTORY=/var/lib/mysql
export MY_CNF=/etc/mysql/my.cnf
export DATABASE_PASSWORD=0jmbdM345wG345od
export DATABASE_PORT=3306
export TUNGSTEN_SERVICE=tungstenservice
export RMI_PORT=10000
export THL_PORT=2112
[ -z "$START_OPTION" ] && export START_OPTION=start

