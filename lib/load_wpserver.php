<?php

/** 
 * The contents of this file are required from the end of wp-config.php
 */

// Can we avoid doing this somehow?
//define('WP_SITEURL', 'http://localhost:8000');
//define('WP_HOME', WP_SITEURL);

// Note: The error handling stuff we do seems to override WP_DEBUG

// Save queries so we can print out the execution time
define('SAVEQUERIES', true);


// Add the filters that we want.
add_filter("query", array($wps, "wps_filter_query"), 9999, 1);
add_filter("template_include", array($wps, "wps_filter_template_include"), 9999, 1);
add_filter("parse_query", array($wps, "wps_filter_parse_query"), 9999, 1);

add_action("all", array($wps, "wps_filter_all"), 9999, 1);
add_action("shutdown", array($wps, "wps_filter_shutdown"), 9999);
