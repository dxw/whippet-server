<?php

/** 
 * The contents of this file are required from the end of wp-config.php
 */

// Note: The error handling stuff we do seems to override WP_DEBUG

// Save queries so we can print out the execution time

if(!defined("SAVEQUERIES")) {
  define('SAVEQUERIES', true);
}

if(!defined('WP_SITEURL')) {
  define('WP_SITEURL', "http://{$whippet->options['i']}" . ($whippet->options['p'] != 80 ? ":{$whippet->options['p']}/" : ''));
}

if(!defined("WP_HOME")) {
  define('WP_HOME', WP_SITEURL);
}

add_filter("query", array($whippet, "wps_filter_query"), 9999, 1);
add_filter("template_include", array($whippet, "wps_filter_template_include"), 9999, 1);
add_filter("parse_query", array($whippet, "wps_filter_parse_query"), 9999, 1);

add_action("all", array($whippet, "wps_filter_all"), 9999, 1);
add_action("shutdown", array($whippet, "wps_filter_shutdown"), 9999);

