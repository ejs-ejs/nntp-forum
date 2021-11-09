#!/usr/bin/php
<?php

/**
 * The file is intended to be used as a cron job to clean out old thumbnail files
 *
 * To enable this cron job just create a symlink for it in the /etc/cron.daily directory.
 */

define('ROOT_DIR', dirname(__FILE__) . '/..');

// Load first because the `autodetect_lang()` function can be used in the configuration file.
require(ROOT_DIR . '/include/action_helpers.php');

// Set the stuff used in the config file… otherwise we will get some warnings.
$_SERVER['PHP_AUTH_USER'] = null;
$_SERVER['PHP_AUTH_PW'] = null;

// If we are run in an environment load the matching config file. Otherwise just load the
// defaul config.
if ($_CONFIG_ENV = getenv('ENVIRONMENT'))
	$CONFIG = require( ROOT_DIR . '/include/' . basename("config.$env.php") );
else
	$CONFIG = require( ROOT_DIR . '/include/config.php' );

// Delete old thumbnail files
$thumbnail_files = glob( ROOT_DIR . '/public/thumbnails/*' );
foreach($thumbnail_files as $file){
	if ( filemtime($file) + $CONFIG['thumbnails']['expire_time'] < time() )
		unlink($file);
}

?>
