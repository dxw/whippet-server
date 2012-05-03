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
   * Note: This is not always sane. For a request for a real resource (like a 
   * css file) it should be a path to an existing file. 
   *
   * For a request for a WordPress page, it will not be.
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

    if(file_exists(dirname(__FILE__) . "/.wpserver-arguments")) {
      $options = file_get_contents(dirname(__FILE__) . "/.wpserver-arguments");
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

      file_put_contents(dirname(__FILE__) . "/.wpserver-arguments", $options);
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
  public function message($string) {
    if($string[0] == "\n") {
      $string = substr($string, 1);
      file_put_contents("php://stdout", "\n");
    }

    file_put_contents("php://stdout", 
      Colours::fg('dark_grey') . "[" . date("Y-m-d H:i:s") . "]" .
      Colours::fg('white') . " {$string}\n");
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

    //
    // What sort of request is this?
    //

    // Save the start time
    $this->start_time = microtime(true);

    // Is it a real file, other than the root of the site?
    if($this->request_uri['path'] != '/' && file_exists($this->request_path)) {
      
      // If so, is it PHP that we need to execute?
      if(preg_match('/\.php$/', $this->request_path)) {
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

    // TODO: is it safe to assume UTF-8?
    $content_type = "text/html; charset=UTF-8";

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

    $this->message(Colours::fg('yellow') . "Template load: " . Colours::fg('white')  .  str_replace($this->options['wp-root'], '', $template));

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
