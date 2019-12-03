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
   * Override parent method
   */
  public function add_question_option( $ids )
  {
    // deal with exclusive answers
    if( !is_array( $ids ) )
    {
      $db_question_option = lib::create( 'database\question_option', $ids );
      if( $db_question_option->exclusive )
      { // if the question option is exclusive then we need to remove all other options
        // note that answer_extra data is automatically deleted by triggers
        $modifier = lib::create( 'database\modifier' );
        $modifier->where( 'answer_id', '=', $this->id );
        static::db()->execute( sprintf( 'DELETE FROM answer_has_question_option %s', $modifier->get_sql() ) );

        // and clean out the extra text
        $this->value_number = NULL;
        $this->value_string = NULL;
        $this->value_text = NULL;
        $this->save();
      }
      else
      { // if the question option isn't exclusive then remove any exclusive options
        $question_option_sel = lib::create( 'database\select' );
        $question_option_sel->add_column( 'id' );
        $question_option_sel->add_column( 'extra' );
        $question_option_mod = lib::create( 'database\modifier' );
        $question_option_mod->where( 'exclusive', '=', true );
        $exclusive_question_option_list = [];
        foreach( $this->get_question_option_list( $question_option_sel, $question_option_mod ) as $exclusive_question_option )
        {
          $exclusive_question_option_list[] = $exclusive_question_option['id'];

          // clear the extra values while we're at it
          if( !is_null( $exclusive_question_option['extra'] ) && 'list' != $exclusive_question_option['extra'] )
          {
            $column = 'value_'.$exclusive_question_option['extra'];
            $this->$column = null;
          }
        }
        $this->save();

        $modifier = lib::create( 'database\modifier' );
        $modifier->where( 'answer_id', '=', $this->id );
        $modifier->where( 'question_option_id', 'IN', $exclusive_question_option_list );
        static::db()->execute( sprintf( 'DELETE FROM answer_has_question_option %s', $modifier->get_sql() ) );
      }
    }

    parent::add_question_option( $ids );
  }

  /**
   * Override parent method
   */
  public function remove_question_option( $ids )
  {
    // remove extra details when an "extra" option is removed
    if( !is_array( $ids ) )
    {
      $db_question_option = lib::create( 'database\question_option', $ids );
      if( $db_question_option->extra && 'list' != $db_question_option->extra )
      { // if the question option is exclusive then we need to remove all other options
        $column = 'value_'.$db_question_option->extra;
        $this->$column = NULL;
        $this->save();
      }
    }

    parent::remove_question_option( $ids );
  }
}
