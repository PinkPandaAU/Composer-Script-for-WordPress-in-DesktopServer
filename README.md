## How to use

1. Install WordPress
2. Add these files to the root of your install
3. Run composer install
4. Run composer update and the script (installer.php) will carry out a number of tasks..
	1. Move wp-config.php (from initial WP install) to new webroot which is currently set to 'core'
	2. Set WP-CONTENT to look in a different directory (write's to wp-config.php)
	3. Delete initial WordPress install (files/folders in root)
	4. Update the blog header path in index.php
	5. Rename WP-CONTENT directory
	6. Update database to specify new location of core WordPress files

## Contributors Welcome
Would love some help tidying this up

## NOTE!
Once you have run the script successfully, you must move the appended wp-content declaration at the bottom of wp-config.php to the top of the document
*Only tested on Windows