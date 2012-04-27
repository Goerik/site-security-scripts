#!/bin/bash

if [ -z $1  ]
then
   echo 'Please specify FULL path to writable folder!'
else
  find $1 -type d -exec chmod 777 {} \; 
  find $1 -type f -exec chmod 666 {} \; 
fi



