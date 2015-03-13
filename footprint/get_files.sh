#!/bin/bash

BASE=/var/www/data/share
TMP=$(tempfile)
touch $TMP

for V in $(ls $BASE | grep typo3_src- | grep -E "\-[34]\.[0-9]+\.[0-9]+$"); do
	for EXT in js sql inc txt css yaml rst csv less; do
		D=$BASE/$V
		L=$(( $(echo $D | wc -c) + 1 ))
		find $D -type f -iname "*.$EXT" | cut -b$L- >> $TMP
	done
done

cat $TMP | sort -u
rm -f $TMP
