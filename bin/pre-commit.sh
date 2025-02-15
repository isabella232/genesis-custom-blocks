#!/bin/bash

# Lint staged PHP files
php_files=$( git diff --diff-filter=d --staged --name-only | grep -E '/*\.php$' )
if [ ! -z "$php_files" ]
	then
	npm run lint:php $php_files
	if [ $? != 0 ]
		then
		exit 1
	fi
fi

# Lint staged JS files
js_files=$( git diff --diff-filter=d --staged --name-only | grep -E '^js\/\S*\.js$' )
if [ ! -z "$js_files" ]
	then
	npm run lint:js:files $js_files
	if [ $? != 0 ]
		then
		exit 1
	fi
fi

# Lint staged .scss files
scss_files=$( git diff --diff-filter=d --staged --name-only | grep -E '/*\.scss$' )
if [ -n "$scss_files" ]
	then
	npm run lint:css:files $scss_files
	if [ $? != 0 ]
		then
		exit 1
	fi
fi

# Lint package.json
if [ -n "$( git diff --diff-filter=d --staged --name-only | grep -E 'package.json' )" ]
	then
	npm run lint:pkg-json
fi
