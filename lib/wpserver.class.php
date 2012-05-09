<?php


class WPServer {

  /* What version are we?
   */
  public $version = "0.1";

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


  /* The time this request started being processed
  */
  public $start_time;


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

    if(file_exists("/tmp/.wpserver-arguments")) {
      $options = file_get_contents("/tmp/.wpserver-arguments");
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

      file_put_contents("/tmp/.wpserver-arguments", $options);
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
  public function print_r($variable) {
    $this->message(print_r($variable, true));
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
      E_USER_DEPRECATED     => 'Deprecated',
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
    WPServer::message(
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
    if(!empty($options['libnotify'])) {
      $message = "{$error_type[$number]}: {$error} in {$file} at line {$line}";
      $message = str_replace("'", "\\'", $message);

      exec("{$options['libnotify']} -i error 'WPServer' '{$message}'");
    }
  }

  /** 
   * Called by the PHP core when an error occurs
   */
  public function handle_php_error($number, $error, $file, $line, $context) {

    // Don't show errors from the WordPress core unless the user wants them
    // Note: Changes made to these conditions should also be made in the output
    // filters section in the bootstrap script
    if(!isset($this->options['show-wp-errors']) && strpos($file, 'wp-content') === false && strpos($file, 'wp-config.php') === false) {
      return true;
    }

    $file = str_replace($this->options['wp-root'], '', $file);

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

    $this->options = $this->get_options();

    date_default_timezone_set('UTC');

    $this->request_uri = parse_url($_SERVER['REQUEST_URI']);
    $this->request_path = $this->options['wp-root'] . $this->request_uri['path'];

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
        $this->message("Serving script {$this->request_path}");

        return $this->serve_script();
      }

      // If not, assume it's a static asset
      if(!isset($this->options['no-assets'])) {
        $this->request_message();
        $this->message("Serving asset {$this->request_uri['path']}\n");
      }

      return $this->serve_file();
    }

    // It's not a real file, so it must be a wordpress permalink. Execute index.php
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
    // TODO: I made these up. I'm not sure they're standards compliant.

    header("Date: " . gmdate('D, d-M-Y H:i:s \U\T\C'));
    header("Expires: " . gmdate('D, d-M-Y H:i:s \U\T\C'));
    header("Cache-Control: no-cache");
    header("Server: WordPress Server {$this->version}");
  }

  /**
   * Serves a static file from the disk
   */
  public function serve_file() {
    //
    // Work out what the content type is
    //

    // I don't think we need to send this, because WordPress will set it.
    //$content_type = "text/html; charset=UTF-8";

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
    // Change to the script's directory so that relative includes work
    chdir(dirname($this->request_path));

    $this->serve_headers();

    // Return some code to be executed by the router at global scope
    return 'require "' . $this->request_path . '"; ?> ';
  }

  
  /**
   * Serves a wordpress permalink
   */
  public function serve_wordpress() {
    // Change to index.php's directory
    chdir($this->options['wp-root']);

    $this->serve_headers();

    // Return some code to be executed by the router at global scope
    return 'require "' . $this->options['wp-root'] . '/index.php"; ?> ';
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

    $this->message(Colours::highlight_sql("  " . $query));

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
      "wanted {$want_template}, got {$got_template} (" . str_replace($this->options['wp-root'], '', $template) . ")"
    );

    return $template;
  }

  /** 
   * Runs right at the end of Wordpress's PHP execution
   */

  public function wps_filter_shutdown() {
    global $wpdb;

    $wordpress_time = round(microtime(true) - $this->start_time, 3);

    $query_time = 0;
    $num_queries = 0;

    foreach($wpdb->queries as $query) {
      $query_time += $query[1];
      $num_queries++;
    }

    $query_time = round($query_time, 3);

    $this->message("Completed request in {$wordpress_time}s ({$num_queries} queries took {$query_time}s)");
  }

  /**
   * Emits the details of hooks and filters as they are called, if required
   */
  public function wps_filter_all($hook, $params) {
    global $wp_filter;

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

    $type = '';
    $caller = '';

    $backtrace = debug_backtrace();
    foreach($backtrace as $i => $value) {
      if($value['function'] == 'apply_filters' || $value['function'] == 'do_action') {
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

    $message = Colours::fg('cyan') . "Hook triggered: " . Colours::fg('white') . "{$type} " . Colours::fg('cyan') . "{$hook}" . Colours::fg('white') . " called from function " . Colours::fg('cyan') . "{$caller['function']}";

    if(!empty($caller['file'])) {
      $message .= Colours::fg('brown') . " in {$caller['file']}";
    }

    if(!empty($caller['line'])) {
      $message .= " at line {$caller['line']}";
    }

    $this->message("{$message}" . Colours::fg('white'));

    if(count($wp_filter[$hook])) {
      $this->message("The following callback functions will execute:");

      
      $hooks = $wp_filter[$hook];

      ksort($hooks, SORT_NUMERIC);

      foreach($hooks as $priority => $callbacks) {


        foreach($callbacks as $callback) {

          if(is_array($callback['function'])) {
            $function = $callback['function'][1];
          }
          else{
            $function = $callback['function'];
          }

          $message =  "\t" . Colours::fg('cyan') . "{$function} " .  Colours::fg('white') . " (Priority: {$priority})";

          $file = exec("grep -rn 'function {$function}' {$this->options['wp-root']}/*");

          if(empty($file)) {
            $file = exec("grep -rn 'function {$function}' {$this->options['wp-content']}/*");
          }

          if(!empty($file)) {
            // /home/harry/Code/wpserver/wordpresses/latest/wp-includes/functions.php:function wp_ob_end_flush_all() {


            $file = str_replace($this->options['wp-root'], '', $file);
            $file = str_replace($this->options['wp-content'], '', $file);

            if(preg_match('/^([^:]+):(\d+):/', $file, $matches)) {
              $message .= Colours::fg("brown") . " in {$matches[1]} at line {$matches[2]}";
            }
          }

          $this->message($message);
        }
      }

      $this->message(Colours::fg('white') . "\n");
    }
    else {
      $this->message("No callback functions are defined\n");
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
}
