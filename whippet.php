#!/usr/bin/php
<?php

require dirname(__FILE__) . '/lib/colours.class.php';
require dirname(__FILE__) . '/lib/helpers.php';
require dirname(__FILE__) . '/lib/whippet.class.php';
require dirname(__FILE__) . '/lib/optionparser/lib/OptionParser.php';
require dirname(__FILE__) . '/lib/launcher-functions.php';

//
// Check that the environment we're running in is sane, and explain what's
// wrong if it's not.
//

// PHP version
if (version_compare(PHP_VERSION, '5.4.0', '<')) {
  die_with_error("whippet requires PHP 5.4 or greater");
}

// MySQL or sqlite/PDO extensions


// Libnotify
$output = array();
exec("which notify-send", $output);
if(!empty($output[0]) && file_exists(trim($output[0]))) {
  define("WPS_LIBNOTIFY_PATH", trim($output[0]));
}

// Timezone
date_default_timezone_set('UTC');

// HOME
if(empty($_SERVER['HOME'])) {
  echo Colours::fg('brown') . "Warning: " . Colours::fg("white") . "Unable to find a HOME environment variable. Paths containing ~ may not be found .\n";
}

//
// Parse and validate arguments
//

// If the user specified invalid options, this will not return
$options = parse_arguments($argv);

// Emit help, if required, and then exit
if(isset($options['h']) || isset($options['help'])) {
  usage();
}

// Capture the path to WordPress, if given
if(!empty($argv[0])) {
  $options['wp-root'] = $argv[0];
}

// Is there a mime.types file?
if(!file_exists($options['mime-file'])) {
  $local_fallback = dirname(__FILE__) . '/etc/mime.types';

  if(!file_exists($options['mime-file'])) {
    die_with_error(
      "Unable to find file {$options['mime-file']}, and failed to load fallback", 
      "You can obtain the most recent mime file here:\n\n  http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types\n\nInstruct Whippet to use this file using the --mime-file argument, or save it to /etc/mime.types");
  }
  else {
    $options['mime-file'] = $local_fallback;
  }
}
    

// Is the specified port sane?
if(!preg_match('/\d+/', $options['p']) || $options['p'] > 65536 || $options['p'] < 1) {
  die_with_error("Expected -p to give a port number between 1 and 65536");
}

// If wp-root is the current directory, set it to the absolute path
if($options['wp-root'] == '.') {
  $options['wp-root'] = getcwd();
}

// If --show-everything is given, set and unset the appropriate options
if($options['show-everything']) {
  unset($options['no-sql']);
  unset($options['no-sql']);
  unset($options['no-templates']);
  unset($options['no-assets']);
  unset($options['no-params']);
  unset($options['no-scripts']);

  $options['show-wp-hooks'] = true;
  $options['show-wp-errors'] = true;
  $options['show-wp-queries'] = true; 
  $options['show-hooks'] = '.*';
  $options['show-errors'] = 'E_ALL';
}

// Convert --show-hooks into an array
$show_hooks = explode(',', $options['show-hooks']);
$show_hooks = array_map('trim', $show_hooks);
$options['show-hooks'] = $show_hooks;

// Make sure that all paths are real paths
$options['wp-root'] = realpathex($options['wp-root']);
$options['wordpresses'] = realpathex($options['wordpresses']);
$options['mime-file'] = realpathex($options['mime-file']);

// Convert the show-errors field to something useful
$show_errors = @eval("return {$options['show-errors']};");

if($show_errors === false) {
  die_with_error(
    "Unable to evaluate error specification: {$options['show-errors']}",
    "The specification must be valid PHP code that identifies a set of errors to be displayed.\n" .
    "See the manual for more information: http://php.net/manual/en/function.error-reporting.php"
  );
}
else {
  $options['show-errors'] = $show_errors;
}



// Libnotify?
if(defined('WPS_LIBNOTIFY_PATH')) {
  $options['libnotify'] = WPS_LIBNOTIFY_PATH;
}

// Now that we have a root, figure out what it points to.
// If a wordpress folder, assume everything is standard
// If a wp-content folder, assume that there is no WP folder and
// that we need to twiddle things around

if((file_exists($options['wp-root'] . '/wp-config.php') || file_exists($options['wp-root'] . '/../wp-config.php')) && file_exists($options['wp-root'] . '/wp-includes')) {
  //
  // We are in a WordPress root.
  //

  define("WPS_LOCATION", "root");
}
elseif(file_exists($options['wp-root'] . '/themes') && file_exists($options['wp-root'] . '/plugins')){
  //
  // We are in a wp-content 
  //
  // We need to find a WP core to use.
  // 

  // Remember where we are
  define("WPS_LOCATION", "wp-content");

  $skeleton_whippet_wpconfig = <<<EOT
<?php 

/**
 * This file is used by Whippet when you run this wp-content folder without
 * putting it into a WordPress installation. You can put anything here that
 * you would normally put into wp-config.php.
 *
 * At a minimum, this file must contain working database details for the sites
 * that you're trying to run. There's no need to add the rest of the default
 * values, but you can if you like, or if you need to change them.
 *
 * Note: WP_DEBUG has no effect when running sites using Whippet.
 */

/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');
EOT;

  // Make sure there's a config we can use
  if(!file_exists($options['wp-root'] . "/whippet-wp-config.php")) {
    echo 
      Colours::fg('red') . "Error: " . Colours::fg('white') . 
      "Couldn't find a configuration file at " . $options['wp-root'] . "/whippet-wp-config.php\n" . 
      "Whippet needs this file in order to know where your database is. You can specify anything\n" .
      "there that you would normally put in wp-config.php. At a minimum, it must contain your\n" .
      "database configuration. Once it's created, you might want to add it to your source\n" .
      "control software's ignores file. Would you like to create this file now?\n"
    ;

    $fp = fopen("php://stdin", 'r');
    do {
      echo "\nCreate and edit file? [Y/n] ";
      $choice = trim(fgets($fp));
    } while(array_search($choice, array('Y', 'y', 'N', 'n', '')) === false);
    fclose($fp);

    if(strtolower($choice) != 'n') {
      file_put_contents("{$options['wp-root']}/whippet-wp-config.php", $skeleton_whippet_wpconfig);

      if(getenv("VISUAL")) {
        system("\$VISUAL {$options['wp-root']}/whippet-wp-config.php > `tty`");
      }
      else if(getenv("EDITOR")) {
        system("\$EDITOR {$options['wp-root']}/whippet-wp-config.php > `tty`");
      }
      else {
        echo 
          "Created settings file at {$options['wp-root']}/whippet-wp-config.php, but could\n" .
          "not load your editor because neither \$VISUAL nor \$EDITOR are set. Please add\n" .
          "your database configuration to this file and restart Whippet\n";

        exit(1);
      }
    }
    else {
      exit(1);
    }
  } 
}
else {
  // We could be *anywhere*
  die_with_error(
    "You did not specify a path to a WordPress installation or wp-content directory",
    "You must specify a path to a working WordPress installation, or to a wp-content directory:\n" .
    "  whippet /path/to/a/wordpress"
  );
}

// Check that the wordpress directory exists
if(!is_dir($options['wp-root'])) {
  die_with_error("Unable to find your WordPress or wp-content directory: {$options['wp-root']}");
}

// If location is root, check that the wordpress directory contains a wordpress installation
if(WPS_LOCATION == 'root' && !file_exists($options['wp-root'] . '/wp-config.php') && !file_exists($options['wp-root'] . '/../wp-config.php')) {
  die_with_error(
    "Unable to find wp-config.php in your wordpress directory ({$options['wp-root']}) or its parent.",
    "Is there a wordpress installation in your current directory? If not, specify the path with --wp-root"
  );
}

// If location is wp-content, check that we have some core files, and download them if we don't
if(WPS_LOCATION == 'wp-content' && !file_exists("{$options['wordpresses']}/{$options['wp-version']}")) {
  echo
    Colours::fg('red') . "Error: " . Colours::fg('white') . 
    "Unable to find the specified WordPress core in your wordpresses directory ({$options['wordpresses']})\n",
    "To run a site from its wp-content folder without it being in a WordPress installation, you need to set up your\n" .
    "core WordPress files in the directory above. Whippet can set this up for you automatically.\n\n";

  $fp = fopen("php://stdin", 'r');

  do {
    if($options['wp-version'] == 'latest') {
      echo "Attempt to download the latest version of WordPress now? [Y/n] ";
    }
    else {
      echo "Attempt to download WordPress version {$options['wp-version']} now? [Y/n] ";
    }

    $choice = trim(fgets($fp));
  } while(array_search($choice, array('Y', 'y', 'N', 'n', '')) === false);

  fclose($fp);

  if(strtolower($choice) != 'n') {
    $tempname = tempnam(sys_get_temp_dir() , ".whippet-") . "-download-wordpress-{$options['wp-version']}";

    if($options['wp-version'] == 'latest') {
      passthru("wget -O '{$tempname}' http://wordpress.org/latest.tar.gz");
    }
    else {
      passthru("wget -O '{$tempname}' http://wordpress.org/wordpress-{$options['wp-version']}.tar.gz");
    }

    passthru("mkdir -p " . $options['wordpresses']);

    // Check that the wordpresses directory exists
    if(!is_dir($options['wordpresses'])) {
      die_with_error("Unable to find or create your wordpresses directory: {$options['wordpresses']}");
    }

    passthru("tar -xvf '{$tempname}' -C " . $options['wordpresses']);
    passthru("rm '{$tempname}'");
    passthru("mv '{$options['wordpresses']}/wordpress'  '{$options['wordpresses']}/{$options['wp-version']}'");
    passthru("rm -rf '/{$options['wordpresses']}/{$options['wp-version']}/wp-content'");

    if(!file_exists("{$options['wordpresses']}/{$options['wp-version']}")) {
      die_with_error(
          "Tried to download WordPress, but something went wrong.", 
          "Please download the version of WordPress you'd like to use, and extract it to {$options['wordpresses']}/<version>");
    }
  } 
  else {
    exit(1);
  }
}



//
// Trap Ctrl-C so that we can say things before we quit
//

declare(ticks = 1);

pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT, "signal_handler");

function signal_handler($signal) {
  global $options;

  // Delete the settings file
  if(file_exists(sys_get_temp_dir() . "/.whippet-arguments")) {
    unlink(sys_get_temp_dir() . "/.whippet-arguments");
  }
 
  // Delete the output buffer
  if(file_exists(sys_get_temp_dir() . "/.whippet-output")) {
    unlink(sys_get_temp_dir() . "/.whippet-output");
  }

  // Delete the callback cache
  if(file_exists($options['cb-cache'])) {
    unlink($options['cb-cache']);
  }

  // Restore original wp-config
  if(WPS_LOCATION == 'root') {
    if(file_exists(dirname($options['wp-config']). "/wp-config-original.whippet.bak")) {
      file_put_contents($options['wp-config'], file_get_contents(dirname($options['wp-config']). "/wp-config-original.whippet.bak"));

      unlink(dirname($options['wp-config']). "/wp-config-original.whippet.bak");
    }
    else {
      if(WPS_LOCATION == 'root') {
        Whippet::message(
          Colours::fg('red') . "Error: " . Colours::fg('white') . "Unable to find wp-config backup file; could not restore original configuration",
          "Your wp-config file should have been backed up at " . dirname($options['wp-config']). "/wp-config-original.whippet.bak, but\n" .
          "it is missing or unreadable. You should edit your wp-config.php by hand to remove the\n" .
          "Whippet sections.\n");
      }
    }
  }
  else if(WPS_LOCATION == 'wp-content') {
    unlink("{$options['wp-root']}/wp-config.php");
  }

  echo "\nQuitting.\n\033[0m";

  exit(0);
}


//
// Inject our code into WordPress
//

$dir = dirname(__FILE__);

if(WPS_LOCATION == 'wp-content') {
  // Move wp-root to the actual wordpress root
  $options['wp-content'] = $options['wp-root'];
  $options['wp-root'] = "{$options['wordpresses']}/{$options['wp-version']}";
}
else {
  $options['wp-content'] = $options['wp-root'] . '/wp-content';
}


$inject  = <<<EOT

////Whippet START

define('WP_CONTENT_DIR', \$whippet->options['wp-content']);

if(!defined('WP_SITEURL')) {
  define('WP_SITEURL', "{\$whippet->options['siteurl']}");
}

if(!defined("WP_HOME")) {
  define('WP_HOME', WP_SITEURL);
}

define('WP_ALLOW_MULTISITE', true);
if (\$whippet->options['multisite']) {
  define('MULTISITE', true);
  define('SUBDOMAIN_INSTALL', false);
  \$base = '/';
  define('DOMAIN_CURRENT_SITE', \$whippet->options['i']);
  define('PATH_CURRENT_SITE', '/');
  define('SITE_ID_CURRENT_SITE', 1);
  define('BLOG_ID_CURRENT_SITE', 1);
}

// Set some useful constants
define("WP_MAX_MEMORY_LIMIT", "512M");
define("WP_MEMORY_LIMIT", "512M");

require_once(ABSPATH . 'wp-settings.php');

require_once('{$dir}/lib/load_whippet.php');
////Whippet END
EOT;

// Get the config
if(WPS_LOCATION == 'root') {
  if(file_exists($options['wp-root'] . "/wp-config.php")) {
    $options['wp-config'] = $options['wp-root'] . "/wp-config.php";
  }
  else if(file_exists($options['wp-root'] . "/../wp-config.php")) {
    $options['wp-config'] = $options['wp-root'] . "/../wp-config.php";
  }
  else {
    die_with_error(
      "Unable to find wp-config.php at {$options['wp-root']} or " . dirname($options['wp-root']),
      "This shouldn't happen. Please report this bug!"
    );
  }

  $wp_config = file_get_contents($options['wp-config']);

  // Modify it
  $new_wp_config = preg_replace('/^.*wp-settings\.php.*$/m', $inject, $wp_config);

  // Save it
  file_put_contents($options['wp-config'], $new_wp_config);

  // Save the original one so we can restore it later
  file_put_contents(dirname($options['wp-config']). "/wp-config-original.whippet.bak", $wp_config);
}
else if(WPS_LOCATION == 'wp-content') {
  $new_wp_config = <<<EOF
<?php
require_once("{$options['wp-content']}/whippet-wp-config.php");

if (!defined('DB_CHARSET'))       define('DB_CHARSET',       'utf8');
if (!defined('DB_COLLATE'))       define('DB_COLLATE',       '');
if (!defined('AUTH_KEY'))         define('AUTH_KEY',         'put your unique phrase here');
if (!defined('SECURE_AUTH_KEY'))  define('SECURE_AUTH_KEY',  'put your unique phrase here');
if (!defined('LOGGED_IN_KEY'))    define('LOGGED_IN_KEY',    'put your unique phrase here');
if (!defined('NONCE_KEY'))        define('NONCE_KEY',        'put your unique phrase here');
if (!defined('AUTH_SALT'))        define('AUTH_SALT',        'put your unique phrase here');
if (!defined('SECURE_AUTH_SALT')) define('SECURE_AUTH_SALT', 'put your unique phrase here');
if (!defined('LOGGED_IN_SALT'))   define('LOGGED_IN_SALT',   'put your unique phrase here');
if (!defined('NONCE_SALT'))       define('NONCE_SALT',       'put your unique phrase here');
if (!isset(\$table_prefix))       \$table_prefix  =          'wp_';
if (!defined('WPLANG'))           define('WPLANG',           '');
if (!defined('ABSPATH'))          define('ABSPATH', dirname(__FILE__) . '/');

$inject
EOF;

  file_put_contents("{$options['wp-root']}/wp-config.php", $new_wp_config);
}

//
// Mush up the arguments and start the server
//

$valid_arguments = serialize($options);

echo Colours::bg("black") . Colours::fg('blue');
echo "Whippet version " . WPS_VERSION  . " started at " . date('H:i:s \o\n d-m-Y') . "\n";

echo Colours::fg('red');
echo "\nNote: Whippet is Alpha software. We're sure it still has problems that need to be\n";
echo "fixed, and we know the install process is a bit labourious. Please do let us know\n";
echo "how you get on, or open an issue on GitHub if you have problems. Thanks!\n\n";

echo Colours::fg('white');
echo "Written and maintained by dxw. Visit http://whippet.labs.dxw.com for more information.\n";

if(WPS_LOCATION == 'root') {
  echo "Found a WordPress installation at {$options['wp-root']}\n";
}
else {
  echo "Found a WordPress content directory at {$options['wp-content']}\n";
  echo "Using a WordPress core at: {$options['wp-root']}\n";
}

echo "Listening on http://{$options['i']}:{$options['p']}\n";
echo "Press Ctrl-C to quit.\n";

//
// PHP -S outputs boilerplate crap that we don't want to see.
// Start it with popen so that we can read its stdout and decide what to do
//
// The file gets deleted when the user quits.
//

$handle = popen("echo '{$valid_arguments}' | " . PHP_BINARY  . " -S {$options['i']}:{$options['p']} " . dirname(__FILE__) . "/lib/router.php 2>&1", 'r');

while(!feof($handle)) {
  $line = fgets($handle);

  // 
  // Output filters
  // Run any filters that should be run on the output, and decide if we want to display it.
  //

  // Deal with PHP errors that the error handler can't manage
  if(preg_match('/(\[.+\]) (PHP .+):  (.+) in (.+) on line (\d+)$/', $line, $matches)) {
    $number = $matches[2];
    switch($matches[2]) {
      case "PHP Parse error": $number = E_PARSE;
      case "PHP Fatal error": $number = E_ERROR;
    }

    Whippet::emit_php_error($number, $matches[3], $matches[4], $matches[5], $options);
    continue;
  }

  // Other stuff that comes out of PHP -S (only seen invalid request warnings so far)
  if(preg_match('/\[\w\w\w \w\w\w [\d\s:]+\] (.+)$/', $line, $matches)) {
    Whippet::message(Colours::fg('blue') . "Server: " . Colours::fg('white') . $matches[1]);
    continue;
  }

  echo $line;
}


// We'll never get here.
echo "Kosinski: The truth is, Captain, I made a mistake - a wonderful, incredible mistake...\n";

