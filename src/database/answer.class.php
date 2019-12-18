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
    $expression_manager = lib::create( 'business\expression_manager' );
    $value = util::json_decode( $this->value );
    $db_response = $this->get_response();
    $db_question = $this->get_question();
    
    // comment questions are always complete
    if( 'comment' == $db_question->type ) return true;

    // hidden questions are always complete
    if( !$expression_manager->evaluate( $db_response, $db_question->precondition ) ) return true;

    // null values are never complete
    if( is_null( $value ) ) return false;

    // dkna/refused questions are always complete
    if( is_object( $value ) )
    {
      $dkna = array_key_exists( 'dkna', $value ) && $value->dkna;
      $refuse = array_key_exists( 'refuse', $value ) && $value->refuse;
      if( $dkna || $refuse ) return true;
    }

    if( 'list' == $db_question->type )
    {
      // get the list of all preconditions for all options belonging to this question
      $question_option_sel = lib::create( 'database\select' );
      $question_option_sel->add_column( 'id' );
      $question_option_sel->add_column( 'precondition' );
      $precondition_list = array();
      foreach( $db_question->get_question_option_list( $question_option_sel ) as $question_option )
        $precondition_list[$question_option['id']] = $question_option['precondition'];

      // make sure that any selected item with extra data has provided that data
      foreach( $value as $selected_option )
      {
        $selected_option_id = is_object( $selected_option ) ? $selected_option->id : $selected_option;
        if( is_object( $selected_option ) ) {
          if( $expression_manager->evaluate( $db_response, $precondition_list[$selected_option_id] ) && (
              ( is_array( $selected_option->value ) && 0 == count( $selected_option->value ) ) ||
              is_null( $selected_option->value )
          ) ) return false;
        }
      }

      // make sure there is at least one selected option
      foreach( $value as $selected_option )
      {
        $selected_option_id = is_object( $selected_option ) ? $selected_option->id : $selected_option;
        if( $expression_manager->evaluate( $db_response, $precondition_list[$selected_option_id] ) )
        {
          if( is_object( $selected_option ) )
          {
            if( is_array( $selected_option->value ) )
            {
              // make sure there is at least one option value
              foreach( $selected_option->value as $selected_option_value ) if( !is_null( $selected_option_value ) ) return true;
            }
            else if( !is_null( $selected_option->value ) ) return true;
          }
          else if( !is_null( $selected_option ) ) return true;
        }
      }

      return false;
    }

    return true;
  }

  /**
   * TODO: document
   */
  public function remove_answer_value_by_option_id( $option_id )
  {
    $select = lib::create( 'database\select' );
    $select->add_column( sprintf( 'JSON_SEARCH( value, "one", %d )', $option_id ), 'search', false );
    $select->from( 'answer' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'id', '=', $this->id );

    $json_path = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    if( !is_null( $json_path ) && false === strpos( $json_path, '.value' ) )
    {
      static::db()->execute( sprintf(
        'UPDATE answer SET value = JSON_REMOVE( value, %s ) WHERE id = %d',
        str_replace( '.id', '', $json_path ),
        $this->id
      ) );
    }
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
    if( !is_null( $json_path ) && '"$"' != $json_path )
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
