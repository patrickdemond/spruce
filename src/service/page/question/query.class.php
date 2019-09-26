<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\page\question;
use cenozo\lib, cenozo\log, linden\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function setup()
  {
    parent::setup();

    // if we got the question from a response then add the response answers to the record
    if( 1 == preg_match( '/^response=([0-9]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $response_id = $parts[1];
      
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'question.id', '=', 'answer.question_id', false );
      $join_mod->where( 'answer.response_id', '=', $response_id );
      $this->modifier->join_modifier( 'answer', $join_mod, 'left' );

      $this->select->add_table_column( 'answer', 'dkna', NULL, true, 'boolean' );
      $this->select->add_table_column( 'answer', 'refuse', NULL, true, 'boolean' );
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
    }
  }
}
