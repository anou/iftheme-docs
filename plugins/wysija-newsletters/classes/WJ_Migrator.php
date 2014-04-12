<?php

// We need the dbDelta migrator from Wordpress.
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/**
Migrator.
A class that uses Wordpress dbDelta function
to handle incremental migrations.
Beware that deltas have specific syntax, not
identical to SQL syntax.
$migrator = new WJ_Migrator('2.1.5');
$migrator->setup();
*/
class WJ_Migrator {

  private $db_prefix;
  private $new_version;

  function __construct($new_version) {
    // Global Wordpress db object.
    $this->db_prefix = WJ_Settings::db_prefix();
    $this->new_version = $new_version;
  }

  /*
  Add here your specific version number case.
  It will be executed on version change.
  */
  public function setup() {

    $sql = '';

    switch ($this->new_version) {
      case '2.5.2.1':
        $table_name = $this->db_prefix . 'custom_field';
        $sql = "CREATE TABLE $table_name (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          name tinytext NOT NULL,
          type tinytext NOT NULL,
          required tinyint(1) DEFAULT '0' NOT NULL,
          default_value text,
          field_values text,
          PRIMARY KEY (id)
          );";
        break;
    }

    return dbDelta($sql);

  }

}
