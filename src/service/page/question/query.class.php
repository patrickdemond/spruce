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
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
      
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'question.id', '=', 'answer.question_id', false );
      $join_mod->where( 'answer.response_id', '=', $db_response->id );
      $this->modifier->join_modifier( 'answer', $join_mod, 'left' );
      $this->modifier->join( 'language', 'answer.language_id', 'language.id' );

      $this->select->add_table_column( 'language', 'code', 'language' );
      $this->select->add_table_column( 'answer', 'id', 'answer_id' );
      $this->select->add_table_column( 'answer', 'value' );
    }
  }
}
