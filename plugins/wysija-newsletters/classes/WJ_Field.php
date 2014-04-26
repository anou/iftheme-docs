<?php

/*
A custom field.
It represents a custom field that
can be added to a form.
Example:
$custom_field = new WJ_Field();
$custom_field->set(array(
	'name' => 'Fruits',
	'type' => 'select',
	'required' => false,
	'settings' => array(
		'label' => 'Select a fruit:',
		'values' => array(
			'Apple', 'Orange', 'Banana'
		),
		'default_value' => 'Orange'
	)
));
$custom_field->save();
*/

class WJ_Field {

	// The custom fields DB table.
	private $table;
	// The Users table.
	private $user_table;
	// The ID of the custom field.
	public $id;
	// The name of the custom field.
	public $name;
	// input, textarea, checkbox, radio, select
	public $type;
	// true | false
	public $required;
	// Settings.
	public $settings = array();

	/*
	Just set the correct tables on creation.
	$custom_field = new WJ_Field();
	*/
	function __construct() {
		$this->table      = WJ_Settings::db_prefix( 'custom_field' );
		$this->user_table = WJ_Settings::db_prefix( 'user' );
	}

	/*
	Get the table name.
	Useful for statistics and counts on Custom Fields.
	Generally when we want to run DB queries.
	*/
	public function get_table_name() {
		return $this->table;
	}

	/*
	Static method to get a Custom field by id.
	WJ_Field::get(id);
	# => WJCustomField Object
	*/
	public static function get( $id ) {
		$object = new self();
		global $wpdb;
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * from {$object->table} WHERE id = %d", $id ), ARRAY_A );
		if ( $result != null ) {
			$result['settings'] = unserialize( $result['settings'] );
			$result['required'] = WJ_Utils::to_bool( $result['required'] );
			$object->set( $result );
			return $object;
		} else {
			return null;
		}
	}

	/*
	Get all custom fields.
	WJ_Field::get_all();
	# => Array of WJ_Field
	*/
	public static function get_all( $options = array() ) {
		$object = new self();
		global $wpdb;

		// default order by
		$order_by = 'id ASC';
		if ( isset($options['order_by'] ) ){
			$order_by = $options['order_by'];
		}

		// fetch rows from db
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$object->table} ORDER BY %s", $order_by ), ARRAY_A );

		if ( $results != null ) {
			$collection = array();
			foreach ( $results as $result ) {
				$result['settings'] = unserialize( $result['settings'] );
				$result['required'] = WJ_Utils::to_bool( $result['required'] );
				$field = new self();
				$field->set( $result );
				$collection[] = $field;
			}
			return $collection;
		} else {
			return null;
		}
	}


	/*
	Get a custom fields names list.
	WJ_Field::get_names_list();
	# => array(1 => 'Address', 2 => 'Gender')
	*/
	public static function get_all_names() {
		$fields = self::get_all();
		$fields_list = array();
		if ( isset( $fields ) ) {
			foreach ( $fields as $field ) {
				$fields_list[$field->id] = $field->name;
			}
		}
		return $fields_list;
	}

	/*
	Set all object properties.
	$custom_field->set(array(
			'name' => 'First Name',
			'type' => 'text',
			'required' => true
	));
	*/
	public function set( $args ) {
		if ( isset( $args['id'] ) ) {
			$this->id = $args['id'];
		}
		$this->name     = $args['name'];
		$this->type     = $args['type'];
		$this->required = $args['required'];
		$this->settings = $args['settings'];
	}

	/*
	Store Custom Field in DB.
	If already stored, updates it.
	$custom_field->save();
	*/
	public function save() {
		// Check if it's a new object or an update.
		if ( isset( $this->id ) ) {
			$this->update();
		} else {
			$this->create();
		}
	}

	// Delete custom field from DB.
	public function delete() {
		global $wpdb;
		$result = $wpdb->delete( $this->table, array( 'id' => $this->id ), array( '%d' ) );
		$this->delete_user_col( $this->id );
		return $result;
	}

	/*
	Generates user column name;
	$custom_field->user_column_name();
	# => 'cf_1'
	*/
	public function user_column_name() {
		$column_name = 'cf_' . $this->id;
		return $column_name;
	}

	/*
	Creates the Custom Field.
	It also creates the user column, depending on type.
	*/
	private function create() {
		global $wpdb;
		$required = WJ_Utils::to_int( $this->required );
		$wpdb->insert(
			$this->table,
			array(
				'name' => $this->name,
				'type' => $this->type,
				'required' => $required,
				'settings' => serialize( $this->settings ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		// ! No id in user col?
		if ( $wpdb->insert_id ) {
			$this->id = $wpdb->insert_id;
			$this->create_user_col();
		} else {
			return false;
		}
	}

	/*
	Updates the value of the custom field.
	$custom_field->update('New address');
	*/
	private function update() {
		global $wpdb;
		$required = WJ_Utils::to_int( $this->required );
		$result   = $wpdb->update(
			$this->table,
			array(
				'name' => $this->name,
				'type' => $this->type,
				'required' => $required,
				'settings' => serialize( $this->settings ),
			),
			array( 'id' => $this->id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
		return $result;
	}

	/*
	Creates the correct user columnn, named cf_x, in the
	user table. x is the ID if the custom field.
	*/
	private function create_user_col() {
		global $wpdb;
		$column_name = $this->user_column_name();
		$column_type = $this->generate_column_type();
		$result = $wpdb->query(
			"ALTER TABLE $this->user_table ADD COLUMN $column_name $column_type"
		);
		return $result;
	}

	/*
	Calculates the correct column type, based on custom field type.
	$custom_field->generate_column_type();
	# => 'VARCHAR(100)'
	*/
	private function generate_column_type() {
		switch ( $this->type ) {
			case 'input':
				$column_type = 'VARCHAR(100)';
				break;
			case 'textarea':
				$column_type = 'VARCHAR(255)';
				break;
			case 'checkbox':
				$column_type = 'TINYINT(1)';
				break;
			case 'radio':
				$column_type = 'VARCHAR(255)';
				break;
			case 'select':
				$column_type = 'VARCHAR(255)';
				break;
			case 'date':
				$column_type = 'INT(10) UNSIGNED';
			break;
			default:
				$column_type = 'VARCHAR(255)';
				break;
		}
		return $column_type;
	}

	/*
	Deletes the user column in the user table.
	Needed when we remove a custom field.
	*/
	private function delete_user_col( $custom_field_id ) {
		global $wpdb;
		$cf_column = 'cf_' . $custom_field_id;
		$result    = $wpdb->query( "ALTER TABLE $this->user_table DROP COLUMN $cf_column" );
		return $result;
	}

}
