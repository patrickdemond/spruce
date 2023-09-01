<?php
/**
 * qnaire_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_trigger: record
 */
abstract class qnaire_trigger extends \cenozo\database\record
{
  /**
   * Creates a qnaire_trigger record from an object
   * @param object $qnaire_trigger
   * @param database\question $db_question The question to associate the qnaire_trigger to
   * @return database\qnaire_trigger
   * @static
   */
  public static function create_from_object( $qnaire_trigger, $db_question )
  {
    $db_qnaire_trigger = new static();

    foreach( static::db()->get_column_names( static::get_table_name() ) as $column_name )
    {
      // ignore timestamp columns
      if( in_array( $column_name, array( 'id', 'update_timestamp', 'create_timestamp' ) ) ) continue;

      $matches = NULL;
      if( 'qnaire_id' == $column_name )
      {
        $db_qnaire_trigger->qnaire_id = $db_question->get_qnaire()->id;
      }
      else if( 'question_id' == $column_name )
      {
        $db_qnaire_trigger->question_id = $db_question->id;
      }
      else if( preg_match( '/(.*)_id/', $column_name, $matches ) )
      {
        $class_name = lib::get_class_name( sprintf( 'database\%s', $matches[1] ) );
        $property_name = sprintf( '%s_name', $matches[1] );
        $record = $class_name::get_unique_record( 'name', $qnaire_trigger->$property_name );
        $db_qnaire_trigger->$column_name = $record->id;
      }
      else
      {
        $db_qnaire_trigger->$column_name = $qnaire_trigger->$column_name;
      }
    }

    // now save and return the new qnaire_trigger object
    $db_qnaire_trigger->save();
    return $db_qnaire_trigger;
  }

  /**
   * Child classes must implement how the trigger is executed on a response
   * @param database\response $db_response
   */
  abstract public function execute( $db_response );

  /**
   * Checks if the trigger should be executed (assumes the trigger has a column named answer_value)
   * @param database\response $db_response
   * @return boolean
   */
  protected function check_trigger( $db_response )
  {
    // If the answer_value column doesn't exist then it is meaningless to check the trigger in this context.
    // Override this method if there is a need to check for a trigger using something other than the answer_value
    // column.
    if( !$this->column_exists( 'answer_value' ) ) return true;

    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );

    $create = false;
    $db_question = $this->get_question();
    $db_answer = $answer_class_name::get_unique_record(
      array( 'response_id', 'question_id' ),
      array( $db_response->id, $db_question->id )
    );

    if( !is_null( $db_answer ) )
    {
      $answer_value = util::json_decode( $db_answer->value );
      $refuse_check = str_replace( ' ', '', $this->answer_value );
      if( in_array( $refuse_check, [ '{"dkna":true}', '{"refuse":true}' ] ) )
      {
        $create = $refuse_check == $db_answer->value;
      }
      else if( '{"dkna":true}' != $db_answer->value && '{"refuse":true}' != $db_answer->value )
      {
        if( 'boolean' == $db_question->type )
        {
          $create = ( 'true' === $this->answer_value && true === $answer_value ) ||
                    ( 'false' === $this->answer_value && false === $answer_value );
        }
        else if( 'list' == $db_question->type )
        {
          $db_question_option = $question_option_class_name::get_unique_record(
            array( 'question_id', 'name' ),
            array( $db_question->id, $this->answer_value )
          );
          $create = !is_null( $db_question_option ) && in_array( $db_question_option->id, $answer_value );
        }
        else if( 'number' == $db_question->type )
        {
          $create = (float)$this->answer_value == $answer_value;
        }
        else // all the rest need a simple comparison
        {
          $create = $this->answer_value === $answer_value;
        }
      }
    }

    return $create;
  }
}
