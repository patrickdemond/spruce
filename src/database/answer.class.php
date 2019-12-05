<?php
/**
 * answer.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * answer: record
 * 
 * Note that the value column of this record is a JSON value with the following example values:
 * dkna: { "dkna": true }
 * refuse: { "refuse": true }
 * boolean: true
 * number: 1
 * string: "value"
 * list: [ 1, 2, { "id":3, "value":"rawr"}, { "id":12, "value": ["one", "two", "three"] } ]
 */
class answer extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // always set the language to whatever the response's current language is
    $db_response = lib::create( 'database\response', $this->response_id );
    $this->language_id = $db_response->language_id;

    parent::save();
  }

  /**
   * Override parent method
   */
  public static function get_unique_record( $column, $value )
  {
    $record = NULL;

    // convert token column to a response_id
    if( is_array( $column ) && in_array( 'token', $column ) )
    {
      $index = array_search( 'token', $column );
      if( false !== $index )
      {
        $response_class_name = lib::get_class_name( 'database\response' );
        $db_response = $response_class_name::get_unique_record( 'token', $value[$index] );
        $column[$index] = 'response_id';
        $value[$index] = is_null( $db_response ) ? 0 : $db_response->id;
      }
    }

    return parent::get_unique_record( $column, $value );
  }

  /**
   * TODO: document
   */
  public function is_complete()
  {
    // everything checks out, so the answer is complete
    // TODONEXT: rewrite this using the new JSON value column
    return true;
  }

  /**
   * TODO: document
   */
  public function remove_empty_answer_values()
  {
    $select = lib::create( 'database\select' );
    $select->add_column( 'JSON_SEARCH( value, "all", "null" )', 'search', false );
    $select->from( 'answer' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'id', '=', $this->id );

    $json_path = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    if( !is_null( $json_path ) )
    {
      $matches = util::json_decode( $json_path );
      if( !is_array( $matches ) ) $matches = array( $matches );
      foreach( $matches as $match )
      {
        static::db()->execute( sprintf(
          'UPDATE answer SET value = JSON_REMOVE( value, "%s" ) WHERE id = %d',
          $match,
          $this->id
        ) );
      }
    }
  }
}
