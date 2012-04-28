#!/bin/bash

expressions=("eval(base64_decode(" "preg_replace(\"/.*/e\"" "jjdecode(" "eval(unescape(" "eval(gzinflate(" "'web shell'" "fromCharCode(");

for expr in "${expressions[@]}"
do
    echo "   Check on: "$expr
    grep -rl $expr ${1}
done
