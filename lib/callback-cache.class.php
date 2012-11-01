<?php

class CallbackCache {

  /* Used to store details of cached callbacks
  */
  protected $cache = array();

  /* Cache filename
  */
  protected $cache_file = '';

  /* Used to remember whether hashes are still equal, so that the same file isn't
   * hashed more than once during a single request.
   */
  protected $hash_valid = array();

  /* A copy of Whippet's options array
   */
  function __construct($options) {
    $this->options = $options;
  }

  function load($cache_file) {
    $this->cache_file = $cache_file;

    // Is there a cache file?
    if(file_exists($this->cache_file) && filesize($this->cache_file)) {
      // Yes. Try to load it.
      $this->cache = unserialize(file_get_contents($this->cache_file));

      if(!$this->cache || !is_array($this->cache)) {
        return false;
      }
    }
    else {
      // No. Try to create it.
      if(!file_exists($this->cache_file)) {
        if(!file_exists(dirname($this->cache_file)) && !mkdir(dirname($this->cache_file), 0644, true)) {
          return false;
        }
      }
      return touch($this->cache_file);
    }

    return true;
  }

  function save() {
    // Save the file
    return file_put_contents($this->cache_file, serialize($this->cache));
  }

  function remove($function) {
    unset($this->cache[$function]);
    return $this->save();
  }

  function add($function, $file, $line) {
    $callback_data = array();

    // Cache it
    $callback_data['file'] = $file;
    $callback_data['line'] = $line;
    $callback_data['hash'] = md5_file($file);

    // If we're adding it, we don't need to check if it's changed if it's looked up again during this request
    $this->hash_valid[$function] = true;

    $this->cache[$function] = $callback_data;

    return $this->save();
  }

  function lookup($function) {
    if(!empty($this->cache[$function])) {
      // Check that the file has not been modified since the cache was saved.
      // If it has been, invalidate the entry. 
      if(!isset($this->hash_valid[$function]) && $this->cache[$function]['hash'] != md5_file($this->cache[$function]['file'])) {
        $this->remove($function);

        return false;
      }

      // We found it, and it's still ok. Flag it as valid so we don't have to hash it
      // again if it's looked up again during this request
      $this->hash_valid[$function] = true;

      return $this->cache[$function];
    }

    return false;
  }
}
