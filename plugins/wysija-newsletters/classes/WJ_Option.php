<?php

/**
* Option.
* It creates and modifies Wordpress options.
* $option = new WJ_Option('my_option');
* $option->set('my_value');
* # => true
* $option->get();
* # => 'my_value'
*/
class WJ_Option {
  
  private $prefix = 'wysija_premium_';
  private $option_name;

  function __construct($name) {
    $this->option_name = $this->prefix . $name;
  }

  public function get() {
    return get_option($this->option_name);
  }

  public function set($option_value) {
    return update_option($this->option_name, $option_value);
  }

}
