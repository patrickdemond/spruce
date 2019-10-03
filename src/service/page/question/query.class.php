<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page\question;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function setup()
  {
    $response_class_name = lib::get_class_name( 'database\response' );

    parent::setup();

    // if we got the question from a response then add the response answers to the record
    if( 1 == preg_match( '/^token=([0-9a-f-]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
      
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'question.id', '=', 'answer.question_id', false );
      $join_mod->where( 'answer.response_id', '=', $db_response->id );
      $this->modifier->join_modifier( 'answer', $join_mod, 'left' );
      $this->modifier->left_join( 'answer_has_question_option', 'answer.id', 'answer_has_question_option.answer_id' );
      $this->modifier->group( 'question.id' );

      $this->select->add_table_column( 'answer', 'dkna', NULL, true, 'boolean' );
      $this->select->add_table_column( 'answer', 'refuse', NULL, true, 'boolean' );
      $this->select->add_table_column( 'answer', 'value_boolean' );
      $this->select->add_table_column( 'answer', 'value_number' );
      $this->select->add_table_column( 'answer', 'value_string' );
      $this->select->add_table_column( 'answer', 'value_text' );
      $this->select->add_column(
        'CASE type '.
          'WHEN "boolean" THEN value_boolean '.
          'WHEN "number" THEN value_number '.
          'WHEN "string" THEN value_string '.
          'WHEN "text" THEN value_text '.
        'END',
        'value',
        false
      );

      $this->select->add_column(
        'GROUP_CONCAT( answer_has_question_option.question_option_id )',
        'question_option_list',
        false
      );
    }
  }
}
