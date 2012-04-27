#!/bin/bash

OWNER=root

if [ -z $1  ]
then
   echo 'Please specify FULL path to site root!'
else
  chown -R root:root $1
  chmod -R 777 $1
  find $1 -type d -exec chmod 775 {} \;
  find $1 -type f -exec chmod 664 {} \;
  chown -R $OWNER:$OWNER $1
fi

