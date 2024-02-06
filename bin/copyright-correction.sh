#!/bin/bash

# Get all PHP files in src/ directory
files=$(find src/ -type f -name "*.php")

for file in $files
do
    # Get the year of the first commit of the file
    year=$(git log --follow --format=%ad --date=format:'%Y' $file | tail -1)

    # Replace the year in the copyright statement
    sed -i "s/Copyright [0-9]\{4\} SURFnet bv/Copyright $year SURFnet bv/g" $file
done
