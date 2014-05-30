<?php

require dirname(__FILE__) . '/callback-cache.class.php';

define('WPS_VERSION', '0.1 ALPHA');

class Whippet {

  /* Stores options passed to the routing script
   */
  public $options; 

  /* Stores the absolute path to the requested resource.
   *
   * Note: For a request for a real resource (like a css file) it should be a 
   * path to an existing file. For a request for a WordPress page, it will not be.
  */
  public $request_path; 

  /* Stores the parsed version of the requested URI.
  */
  public $request_uri;

  /* The type of request we're serving 
   */
  public $request_type;

  const ASSET = 'ASSET';
  const WP = 'WP';
  const SCRIPT = 'SCRIPT';


  /* The time this request started being processed
  */
  public $start_time;

  /* Used to manage a cache of hook callbacks
   */
  public $cb_cache;

  /* Flag used to ensure that only one shutdown function gets set
  */
  public $done_shutdown_function;

  public function __construct() {
    date_default_timezone_set('UTC');

    $this->options = $this->get_options();

    $this->cb_cache = new CallbackCache($this->options);

    if(!$this->cb_cache->load($this->options['cb-cache'])) {
      $this->message(Colours::fg('brown') . "Warning: " . Colours::fg('white') . "Unable to load or create callback cache file {$this->options['cb-cache']}. Displaying hook data will be slow.");
    }
  }

  /**
   * Parse options that are passed to us from stdin.Not sure what the format 
   * should be yet. To start with, we probably need options to specify the 
   * location of the wordpress installation to run.
   *
   * Eventually it would be nice to be able to start a development version
   * of wordpress using sqlite (with minimal setup) and just a wp-content
   * folder, with the core being loaded from /usr/share (or similar)
   *
   * @return Array An array of options (probably)
   */
  public function get_options() {

    //
    // Sort out options.
    // Because a routing script can't read command arguments, we need to read 
    // this from stdin (on the first request) or from the temporary settings 
    // file (on subsequent requests)
    //

    if(file_exists(sys_get_temp_dir() . "/.whippet-arguments")) {
      $options = file_get_contents(sys_get_temp_dir() . "/.whippet-arguments");
    }
    else {
      $read = array(fopen('php://stdin', 'r'));
      $write = null;
      $except = null;

      $options = '';

      if(stream_select($read, $write, $except, 0) > 0)  {
        $options = stream_get_contents($read[0]);
        fclose($read[0]);
      }

      if(file_put_contents(sys_get_temp_dir() . "/.whippet-arguments", $options) === false) {
        $this->message(
          Colours::fg('bold_red') .
          "Error: " . 
          Colours::fg('white') .
          "Unable to write options file. This is a serious error. You should probably give up and report a bug.");
      }
    }
    
    if(!$options) {
      $this->message(
        Colours::fg('bold_red') .
        "Error: " . 
        Colours::fg('white') .
        "Unable to locate options on stdin or on the disk. This is a serious error. You should probably give up and report a bug.");
    }

    // There's no need to validate here because the bootstrap script has done that.

    return unserialize($options);
  }

  /** 
   * Emits a message to STDOUT, prepended with the current time.
   *
   * Note: if the first character of the string is a newline, that newline will
   * be emitted before the time.
   *
   * Note: this method appends a newline to the string.
   *
   * @param String The message
   */
  public static function message($string) {
    global $whippet;
  
    if(isset($whippet->options['q'])) {
      return;
    }

    if($string[0] == "\n") {
      $string = substr($string, 1);
      file_put_contents("php://stdout", "\n");
    }

    file_put_contents("php://stdout", 
      Colours::fg('dark_grey') . "[" . date("Y-m-d H:i:s") . "]" .
      Colours::fg('white') . " {$string}" . 
      Colours::fg('white') . "\n" );
  }

  
  /**
   * Dumps an object to STDOUT, using print_r
   *
   */
  static public function print_r($variable) {
    Whippet::message(print_r($variable, true));
  }


  /**
   * Prints basic information about the request being processed
   */
  public function request_message() {
    $this->message("\nStarted {$_SERVER['REQUEST_METHOD']} " . Colours::fg('green') . "\"{$_SERVER['REQUEST_URI']}\"" . Colours::fg('white') . " for {$_SERVER['REMOTE_ADDR']}");
  }

  /** 
   * This function actually emits handled PHP errors. It's here instead of
   * in handle_php_error because we want a static version to be used from
   * the bootstrap script.
   */
  static public function emit_php_error($number, $error, $file, $line, $options = array('show-errors' => E_ALL)) {
    if($number != E_ERROR && !isset($options['show-wp-errors']) && Whippet::file_is_in_core($file, $options)) {
      return;
    }

    $error_type = array (
      E_ERROR          => 'Fatal error',
      E_WARNING        => 'Warning',
      E_PARSE          => 'Parsing error',
      E_NOTICE         => 'Notice',
      E_CORE_ERROR     => 'Core error',
      E_CORE_WARNING   => 'Core warning',
      E_COMPILE_ERROR  => 'Compile error',
      E_COMPILE_WARNING => 'Compile warning',
      E_USER_ERROR     => 'User error',
      E_USER_WARNING   => 'User warning',
      E_USER_NOTICE    => 'User notice',
      E_STRICT         => 'Strict notice',
      E_RECOVERABLE_ERROR  => 'Recoverable error',
      E_DEPRECATED     => 'Deprecated',
      E_USER_DEPRECATED     => 'User Deprecated',
    );

    // If the error is unknown, pass it through directly
    if(empty($error_type[$number])) {
      $error_type[$number] = $number;
    }

    // Should we show this error?
    if(($number & $options['show-errors']) != $number) {
      return;
    }

    // Display the error
    Whippet::message(
      Colours::fg('bold_red') .
      $error_type[$number] . 
      Colours::fg('red') .
      ": " .
      $error .
      Colours::fg('brown') .
      " in " .
      $file .
      " at line {$line}" .
      Colours::fg("white")
    );

    // Show a notification, if we've got libnotify
    if(!empty($options['libnotify']) && $number === E_ERROR) {
      $message = "{$error_type[$number]}: {$error} in {$file} at line {$line}";
      $message = str_replace("'", "\\'", $message);

      exec("{$options['libnotify']} -i error 'Whippet' '{$message}'");
    }
  }

  static public function file_is_in_core($file, $options) {
    if(!empty($options['wp-content'])) {
      return strpos($file, $options['wp-content']) === false;
    }

    return strpos($file, WP_CONTENT_DIR) === false;
  }

  /** 
   * Called by the PHP core when an error occurs
   */
  public function handle_php_error($number, $error, $file, $line, $context) {
    $this->emit_php_error($number, $error, $file, $line, $this->options);

    return true;
  }

  /** 
   * Called when the command is run. Sets up the options and environment and  
   * then passes off to a more specific handler
   */
  function run() {
    //
    // Fetch options and set up the environment
    //

    $this->request_uri = parse_url($_SERVER['REQUEST_URI']);

    // Is this a Multisite install?
    if($this->options['multisite']) {
      // We're in a Multisite install. There are a couple of extra steps. Or just one?
      if(preg_match('/^\/[_0-9a-zA-Z-]+\/(wp-(content|admin|includes).*)/', $this->request_uri['path'], $matches)) {
        $this->request_uri['path'] = "/" . $matches[1];
      }

      // TODO: Is there anything else we need to do?
    }

    if ($this->startswith($this->request_uri['path'], '/wp-content/')) {
      $this->request_path = $this->options['wp-content'] . substr($this->request_uri['path'], 11);
    } else {
      $this->request_path = $this->options['wp-root'] . $this->request_uri['path'];
    }

    // If the path is to a directory, append the default document
    if(is_dir($this->request_path)) {
      $this->request_path .= "index.php";
    }

    // If you don't set this, WordPress adds index.php into all the links.
    $_SERVER['SERVER_SOFTWARE'] = 'Apache';

    // Set up a custom error handler so that we can make errors and notices purty
    set_error_handler(array($this, "handle_php_error"), E_ALL);

    //
    // What sort of request is this?
    //

    // Save the start time
    $this->start_time = microtime(true);

    // Is it a real file, other than the root of the site?
    if($this->request_uri['path'] != '/' && file_exists($this->request_path)) {
      
      // If so, is it PHP that we need to execute?
      if(preg_match('/\.php$/', $this->request_path) && !isset($this->options['no-scripts'])) {
        $this->request_message();

        return $this->serve_script();
      }

      // If not, assume it's a static asset
      if($this->options['show-assets']) {
        $this->request_message();
      }

      // This gets set in load_whippet for wordpress requests, but that might not get included
      $this->register_shutdown_function();

      return $this->serve_file();
    }



    // It's not a real file, and Multisite is not enabled, so it must be a wordpress permalink. Execute index.php
    $this->request_message();
    $this->message("Processing {$this->request_uri['path']}");
    return $this->serve_wordpress();
  }

  /**
   * The built-in webserver only emits a minimal set of headers, so add in the
   * usual ones.
   *
   * Note: this function will not serve content-type or content-length headers.
   */
  public function serve_headers() {
    // TODO: I made these up. I'm not sure they're standards compliant. I'm not 
    // sure if they set stuff that WP already sets. I'm not sure if I missed things.

    header("Date: " . gmdate('D, d-M-Y H:i:s \U\T\C'));
    header("Expires: " . gmdate('D, d-M-Y H:i:s \U\T\C'));
    header("Cache-Control: no-cache");
    header("Server: Whippet " . WPS_VERSION);
  }

  /**
   * Serves a static file from the disk
   */
  public function serve_file() {
    //
    // Work out what the content type is
    //

    $this->request_type = Whippet::ASSET;

    // Default to the official WTF mime type
    $content_type = "application/octet-stream";

    $extension = substr(strrchr($this->request_path, '.'), 1);

    if(!empty($extension)) {
      foreach(file($this->options['mime-file']) as $line) {
        // Skip comments
        if (substr($line, 0, 1) == '#') {
          continue;
        }
        
        if(preg_match("/^([\w\+\-\.\/]+)\s+(\w+\s)*($extension\s)/i", rtrim($line) . " ", $matches)) {
          $content_type = $matches[1];
          break;
        }
      }
    }


    //
    // Get the size
    //

    $content_length = filesize($this->request_path);


    //
    // Reply
    //

    header("Content-type: {$content_type}");
    header("Content-length: {$content_length}");

    $this->serve_headers();

    echo file_get_contents($this->request_path);

    return '';
  }

  /**
   * Serves a specific script; for example, /wp-admin/install.php
   */
  public function serve_script() {
    $this->request_type = Whippet::SCRIPT;

    // Change to the script's directory so that relative includes work
    chdir(dirname($this->request_path));

    $this->serve_headers();

    // Return some code to be executed by the router at global scope
    return 'require "' . $this->request_path . '";';
  }

  
  /**
   * Serves a wordpress permalink
   */
  public function serve_wordpress() {
    $this->request_type = Whippet::WP;

    // Change to index.php's directory
    chdir($this->options['wp-root']);

    $this->serve_headers();

    // Return some code to be executed by the router at global scope
    return 'require "' . $this->options['wp-root'] . '/index.php";';
  }


  //
  // FILTERS
  //

  /**
   * Emits all queries to the console
   */
  public function wps_filter_query($query) {
    if(isset($this->options['no-sql'])) {
      return $query;
    }

    $in_wp_content = false;
    $backtrace = debug_backtrace();

    // Check for something in wp-content
    foreach($backtrace as $i => $func) {
      if(empty($func['file'])) {
        continue;
      }

      // I am not sure that this is robust
      // It assumes that the stack looks like this:
      // - wp-db.php stuff
      //  - wp-db.php stuff
      //   - function that calls wpdb with a query
      //    - function that calls the thing which does the query <-- user interested in this
      if(preg_match('/wp-db/', $func['file']) && preg_match('/wp-db/', $backtrace[$i+1]['file'])) {
        $in_func = $backtrace[$i+3];
      }

      if(preg_match('/wp-content/', $func['file'])) {
        $in_wp_content = true;
        $in_func = $func;
        break;
      }
    }

    if(!isset($this->options['show-wp-queries']) && !$in_wp_content) {
      return $query;
    }

    $message = Colours::fg("purple") . "Query: ";

    if(isset($in_func)) {
      $file = str_replace($this->options['wp-root'] . "/wp-content/", '', $in_func['file']);
   
      $message .=
        Colours::fg("white") ."Triggered by function " . 
        Colours::fg("blue") . "{$in_func['function']}" . 
        Colours::fg("white") . " called from " . 
        Colours::fg("brown") . $file . 
        Colours::fg("white") . " at line {$in_func['line']}:";
    }
    else {

    }

    $this->message($message);
    $this->message(Colours::highlight_sql("  " . trim($query)));

    return $query;
  }

  /**
   * Emits template loads to the console
   */
  public function wps_filter_template_include($template) {
    if(isset($this->options['no-templates'])) {
      return $template;
    }

    //
    // Try to work out what the template is
    //

    $want_template = '';
    $got_template  = '';

    if     ( is_404()            ): $want_template = '404';
    elseif ( is_search()         ): $want_template = 'Search';
    elseif ( is_tax()            ): $want_template = 'Taxonomy';
    elseif ( is_front_page()     ): $want_template = 'Front page';
    elseif ( is_home()           ): $want_template = 'Home';
    elseif ( is_attachment()     ): $want_template = 'Attachment';
    elseif ( is_single()         ): $want_template = 'Single';
    elseif ( is_page()           ): $want_template = 'Page';
    elseif ( is_category()       ): $want_template = 'Category';
    elseif ( is_tag()            ): $want_template = 'Tag';
    elseif ( is_author()         ): $want_template = 'Author';
    elseif ( is_date()           ): $want_template = 'Date';
    elseif ( is_archive()        ): $want_template = 'Archive';
    elseif ( is_comments_popup() ): $want_template = 'Comments popup';
    elseif ( is_paged()          ): $want_template = 'Paged';
    endif;


    if     ( $template == get_404_template()            ) : $got_template = '404';
    elseif ( $template == get_search_template()         ) : $got_template = 'Search';
    elseif ( $template == get_taxonomy_template()       ) : $got_template = 'Taxonomy';
    elseif ( $template == get_front_page_template()     ) : $got_template = 'Front page';
    elseif ( $template == get_home_template()           ) : $got_template = 'Home';
    elseif ( $template == get_attachment_template()     ) : $got_template = 'Attachment';
    elseif ( $template == get_single_template()         ) : $got_template = 'Single';
    elseif ( $template == get_page_template()           ) : $got_template = 'Page';
    elseif ( $template == get_category_template()       ) : $got_template = 'Category';
    elseif ( $template == get_tag_template()            ) : $got_template = 'Tag';
    elseif ( $template == get_author_template()         ) : $got_template = 'Author';
    elseif ( $template == get_date_template()           ) : $got_template = 'Date';
    elseif ( $template == get_archive_template()        ) : $got_template = 'Archive';
    elseif ( $template == get_comments_popup_template() ) : $got_template = 'Comments popup';
    elseif ( $template == get_paged_template()          ) : $got_template = 'Paged';
    elseif ( $template == get_index_template()          ) : $got_template = 'Index';
    else                                                  : $got_template = 'Unknown';
    endif;

    $this->message(
      Colours::fg('yellow') . 
      "Template load: " . 
      Colours::fg('white') . 
      "wanted {$want_template}, got {$got_template} (" . str_replace($this->options['wp-root'] . "/wp-content/", '', $template) . ")"
    );

    return $template;
  }

  public function register_shutdown_function() {
    register_shutdown_function(array($this, "wps_filter_shutdown"));
  }

  /** 
   * Runs right at the end of execution
   */
  public function wps_filter_shutdown() {
    global $wpdb;

    if($this->done_shutdown_function) {
      return;
    }

    if(!$this->options['show-assets'] && $this->request_type == Whippet::ASSET) {
      return;
    }

    $request_time = round(microtime(true) - $this->start_time, 3);

    $query_time = 0;
    $num_queries = 0;
    $request = "Served asset {$this->request_uri['path']} -";

    if(isset($wpdb) && is_array($wpdb->queries)) {
      foreach($wpdb->queries as $query) {
        $query_time += $query[1];
        $num_queries++;
      }

      $query_time = round($query_time, 3);
      $request = "Completed";
    }

    $code = http_response_code();
    $location = '';

    if(preg_match('/3\d\d/', $code)) {
      foreach(headers_list() as $header) {
        if(preg_match('/^Location: (.*)$/', $header, $matches)) {
          $location = " => " . $matches[1];
          break;
        }
      }
    }

    $this->message("{$request} {$code} " . response_code_text($code) . "$location ({$request_time}s" . ($num_queries == 0 ? '' : ", {$num_queries} queries took {$query_time}s") . ")");
  }

  /**
   * Emits the details of hooks and filters as they are called, if required
   */
  public function wps_filter_all($hook) {
    global $wp_filter;

    //
    // Check whether this hook should be displayed
    //
    
    $display = false;

    foreach($this->options['show-hooks'] as $show) {
      if(preg_match("/^{$show}$/", $hook)) {
        $display = true;
        break;
      }
    }

    if(!$display) {
      return;
    }

    //
    // If this hook has no callbacks, just bail
    //

    if(empty($wp_filter[$hook]) || !count($wp_filter[$hook])) {
      return;
    }
    
    //
    // Find the callbacks
    //

    $callback_messages = array();
    $all_wp_core = true;

    $hooks = $wp_filter[$hook];

    // TODO: this erroneously reorders hooks with the same priority
    ksort($hooks, SORT_NUMERIC);

    foreach($hooks as $priority => $callbacks) {
      foreach($callbacks as $callback) {
        if(is_array($callback['function'])) {
          $function = $callback['function'][1];
        }
        else{
          $function = $callback['function'];
        }

        // Skip Whippet callbacks
        if(preg_match('/^wps_/', $function)) {
          continue;
        }

        $callback_message =  "\t{$priority}: " . Colours::fg('cyan') . $function .  Colours::fg('white');
        $callback_data = $this->cb_cache->lookup($function);

        if(!$callback_data) {
          // Find the function
          $file = exec("grep -rn 'function {$function}' {$this->options['wp-root']}/*");

          if(empty($file) && isset($this->options['wp-content'])) {
            $file = exec("grep -rn 'function {$function}' {$this->options['wp-content']}/*");
          }

          // If we got it, add an entry to the cache
          if(!empty($file) && preg_match('/^([^:]+):(\d+):/', $file, $matches)) {
            $this->cb_cache->add($function, $matches[1], $matches[2]);
            $callback_data = $this->cb_cache->lookup($function);
          }
        }

        if($callback_data) {
          // Is this a callback outside the WP core?
          if(preg_match('/wp-content/', $callback_data['file'])) {
            $all_wp_core = false;
          }

          // Make paths relative
          $callback_data['file'] = str_replace($this->options['wp-root'], '', $callback_data['file']);

          if(!empty($this->options['wp-content'])) {
            $callback_data['file'] = str_replace($this->options['wp-content'], '', $callback_data['file']);
          }

          $callback_message .= " in " . Colours::fg("brown") . str_replace($this->options['wp-root'] . "/wp-content/", '', $callback_data['file']) . Colours::fg("white") . " at line {$callback_data['line']}";
        }
        else {
          $callback_message .= " (couldn't find this function's definition)";
        }

        $callback_messages[] = $callback_message;
      }
    }

    //
    // If we're not showing WP core hooks, and all these callbacks are from the core, bail
    // If there are no callbacks (probably because a whippet callback was skipped), bail
    //

    if(!isset($this->options['show-wp-hooks']) && $all_wp_core) {
      return;
    }

    if(!count($callback_messages)) {
      return;
    }


    //
    // Find the caller
    //

    $type = '';
    $caller = '';

    $backtrace = debug_backtrace();
    foreach($backtrace as $i => $value) {
      if($value['function'] == 'apply_filters' || $value['function'] == 'do_action' || $value['function'] == 'apply_filters_ref_array' || $value['function'] == 'do_action_ref_array') {
        $caller = $backtrace[$i + 1];
        
        if($value['function'] == 'apply_filters') {
          $type = "Filter";
        }
        else {
          $type = "Action";
        }

        break;
      }
    }


    //
    // Put together the message
    //

    $message = 
      Colours::fg('bold_cyan') . "Hook triggered: " . 
      Colours::fg('white') . "{$type} " . 
      Colours::fg('cyan') . "{$hook}" . 
      Colours::fg('white') . " called from function " . 
      Colours::fg('cyan') . "{$caller['function']}";

    if(!empty($caller['file'])) {
      $message .= 
        Colours::fg("white") . " in " . 
        Colours::fg('brown') . str_replace($this->options['wp-root'], '', $caller['file']);
    }

    if(!empty($caller['line'])) {
      $message .= Colours::fg("white") . " at line {$caller['line']}";
    }

    $this->message("{$message}" . Colours::fg('white'));
    foreach($callback_messages as $callback_message) {
      $this->message($callback_message);
    }
  }

  /** 
   * Emits page parameters
   */
  public function wps_filter_parse_query($query) {
    if(isset($this->options['no-params'])) {
      return;
    }

    $params = array();
    foreach($query->query_vars as $key => $value) {
      if(!empty($value)) {
        $params[] = "{$key} => '{$value}'";
      }
    }

    if(!count($params)) {
      $this->message("Parameters: (none)");
    }
    else {
      $this->message("Parameters: " . join(", ", $params));
    }
  }

  public function startswith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
  }
}
