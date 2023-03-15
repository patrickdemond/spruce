<?php
/**
 * settings.ini.php
 *
 * Defines initialization settings.
 * DO NOT edit this file, to override these settings use settings.local.ini.php instead.
 * Any changes in the local ini file will override the settings found here.
 */

global $SETTINGS;

// tagged version
$SETTINGS['general']['application_name'] = 'pine';
$SETTINGS['general']['instance_name'] = $SETTINGS['general']['application_name'];
$SETTINGS['general']['version'] = '2.8';
$SETTINGS['general']['build'] = 'c647726';

// the default maximum number of seconds that a page should take to complete
$SETTINGS['general']['default_page_max_time'] = 60;

// hard-coded databaseaccess ID which identifies the respondent's access record
$SETTINGS['general']['respondent_access_id'] = NULL;

// whether this instance gets its respondents from a detached beartooth instance
$SETTINGS['general']['detached'] = false;

// the parent instance of pine that this instance will sync its qnaires with
$SETTINGS['url']['PARENT_INSTANCE'] = NULL;

// the location of the application's internal path
$SETTINGS['path']['APPLICATION'] = str_replace( '/settings.ini.php', '', __FILE__ );

// the user to use when filling in a qnaire without logging in
$SETTINGS['utility']['qnaire_username'] = 'pine';

// the location of qnaire export files
$SETTINGS['path']['QNAIRE_EXPORT'] = str_replace( 'settings.ini.php', 'doc/qnaire_export', __FILE__ );

// the location of qnaire print files
$SETTINGS['path']['QNAIRE_PRINT'] = str_replace( 'settings.ini.php', 'doc/qnaire_print', __FILE__ );

// the location to store response data
$SETTINGS['path']['RESPONSE_DATA'] = str_replace( 'settings.ini.php', 'doc/response_data', __FILE__ );

// add modules used by the application
$SETTINGS['module']['pdf'] = true;

// the number of days after which exported respondents are purged (used by detached instances only)
$SETTINGS['general']['purge_delay'] = 7;
