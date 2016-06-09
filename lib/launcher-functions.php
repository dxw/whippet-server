<?php

/**
 * Displays an error message and exits.
 */
function die_with_error($error, $help = '') {
  echo Colours::fg('red');
  echo "Error: {$error}\n";
  echo Colours::off();

  if(!empty($help)) {
    echo "\n{$help}\n";
  }

  exit(1);
}

/**
 * Replacement for realpath. This version understands (and will expand) ~
 * It returns the expanded path if it exists, or the unexpanded one if it
 * doesnt. ~ will always be replaced with the contents of $_SERVER['HOME'].
 */
function realpathex($path) {
  if(strpos('~', $path) !== false) {
    $path = str_replace('~', $_SERVER['HOME']);
  }

  if(!file_exists($path)) {
    return $path;
  }

  return realpath($path);
}

function parse_arguments(&$argv) {
  // Default options
  $defaults = array(
    'i'           => 'localhost',
    'p'           => '8000',
    "mime-file"   => "/etc/mime.types",
    "wp-root"     => ".",
    "wp-version"  => "latest",
    "show-errors" => 'E_ALL',
    "show-assets" => false,
    "show-hooks"  => '',
    "show-everything" => false,
    "wordpresses" => $_SERVER['HOME'] . "/.cache/whippet/wordpresses",
    "cb-cache"    => $_SERVER['HOME'] . "/.cache/whippet/callback-cache",
    "multisite"   => false,
  );

  // Are there some options in a config file? Check them in order.
  if(file_exists("/etc/whippetrc")) {
    $defaults = array_merge($defaults, parse_ini_file("/etc/whippetrc"));
  }

  if(!empty($_SERVER['HOME']) && file_exists($_SERVER['HOME'] . "/.whippetrc")) {
    $defaults = array_merge($defaults, parse_ini_file($_SERVER['HOME'] . "/.whippetrc"));
  }

  $optparser = new OptionParser;

  $optparser->addRule('h|help');
  $optparser->addRule('i::');
  $optparser->addRule('p::');
  $optparser->addRule('siteurl::');
  $optparser->addRule('q');
  $optparser->addRule('multisite');
  $optparser->addRule('mime-file::');
  $optparser->addRule('no-sql');
  $optparser->addRule('no-templates');
  $optparser->addRule('no-params');
  $optparser->addRule('no-scripts');
  $optparser->addRule('show-assets');
  $optparser->addRule('show-wp-errors');
  $optparser->addRule('show-wp-queries');
  $optparser->addRule('show-wp-hooks');
  $optparser->addRule('show-errors::');
  $optparser->addRule('show-everything');
  $optparser->addRule('wp-version::');
  $optparser->addRule('show-hooks::');
  $optparser->addRule('wordpresses::');

  try{
    $argv = $optparser->parse();
  }
  catch(Exception $e) {
    echo Colours::fg('red') . "Error: " . Colours::fg("white") . $e->getMessage() . "\n\n";
    usage();

    exit(0);
  }

  $arguments = $optparser->getAllOptions();

  if(!isset($arguments->siteurl)) {
    $i = isset($arguments['i']) ? $arguments['i'] : $defaults['i'];
    $p = isset($arguments['p']) ? $arguments['p'] : $defaults['p'];

    if ($p == 80) {
      $defaults['siteurl'] = "http://{$i}/";
    } else {
      $defaults['siteurl'] = "http://{$i}:{$p}/";
    }
  }

  return array_merge($defaults, $arguments);
}

/**
 * Display usage information.
 */
function usage() {
?>
Whippet launches a stand-alone web server for a specific WordPress installation.
It makes WordPress easier to develop with, for example, by adding lots of debug
information to the terminal without cluttering up or breaking your templates.

Usage:
  whippet [options] <path>

Arguments:

  path                    Path to the WordPress installation or wp-content directory that
                          Whippet should launch. (Default: .)

Options:

  -i <interface address>  Specify an interface to listen on. (Default: localhost)
  -p <port>               Specify a port number to listen on. (Default: 8000)
  --siteurl <URL>         Set WP_SITEURL/WP_HOME. (Default: http://<value of -i>:<value of -p>)

  -q                      Quiet mode. Suppresses most output.

  --multisite             Activate and enable multisite.

  --mime-file <filename>  Specify a path to a mime.types file containg content-type
                          rules. The most recent version of this file is available at:

                            http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types

                          should you need to obtain it. (Default: /etc/mime.types)

  --wp-version <version>  When running Whippet on a wp-content directory, specifies the version
                          of WordPress that should be loaded. (Default: latest)

  --wordpresses <path>    Specify a path to the directory where Whippet should download and store
                          WordPress core files. (Default: ~/.cache/whippet/wordpresses)

  --show-errors <errors>  A specification of PHP error types to be displayed. You can use any
                          specification that you might normally use for error_reporting,
                          for example:

                            --show-errors "E_ALL & ~E_STRICT"

                          (Default: E_ALL)

  --show-hooks <hooks>    When set, Whippet will watch for calls to the specified hooks and display
                          them on the console. For example:

                            --show-hooks init,admin_menu

                          You can also use regexs:

                            --show-hooks 'wp_.*'

                          To show all hooks:

                            --show-hooks '.*'

                          You probably shouldn't try to put a comma in a regex. Hooks with no callbacks
                          defined and hooks defined by Whippet will not be displayed. Hooks defined by
                          the WordPress core will not be displayed unless you also specify --show-wp-hooks.
                          (Default: none)

  --no-sql                Do not display information about queries
  --no-templates          Do not display information about template paths
  --no-params             Do not display information about request query parameters
  --no-scripts            Do not display information about requests for scripts
                          For example: load-scripts.php, load-styles.php

  --show-assets           Show information about static files being served. (Default: only show WordPress
                          and scripts being served)

  --show-wp-errors        Show PHP errors that were generated by the WordPress core. (Default: only show
                          non-fatal errors from wp-config.php and files in wp-content)

  --show-wp-queries       Show queries that are executed by the WordPress core. (Default: only show
                          queries from files in wp-content)

  --show-wp-hooks         Show calls to hooks that are set by the WordPress core. (Default: only show
                          calls that are set by files in wp-content)

  --show-everything       Shows all the output that Whippet can display. Alias for:

                            --show-wp-hooks --show-wp-errors --show-wp-queries --show-hooks '.*'

                          This option will also override --no-sql et al, if set.

Setting Defaults:

  You can set defaults for Whippet by creating ~/.whippetrc or /etc/whippetrc. Both files will be read
  if present. Your local defaults will override system-wide defaults. The file should be in ini format:

    p = 8080
    show-wp-errors = true

  The path to your WordPress installation or wp-content folder can be set using wp-root:

    wp-root = /home/bob/WordPress

  Comments start with ';'.

Feedback and help:

  Visit Github and check the wiki, open an issue or send us a message: https://github.com/dxw/whippet
<?php

  exit(0);
}
