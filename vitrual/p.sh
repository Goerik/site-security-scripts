#!/bin/bash
genpass() { local h x y;h=${1:-14};x=( {a..z} {A..Z} {0..9} );y=$(echo ${x[@]} | tr ' ' '\n' | shuf -n$h | xargs);echo -e "${y// /}"; }

pass=`genpass`

echo ${pass}

