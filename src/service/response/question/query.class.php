<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response\question;
use cenozo\lib, cenozo\log, pine\util;

/**
 * NOTE: This service is used by the response "display" feature where all of a response's questions are displayed in one page
 */
class query extends \cenozo\service\query
{
  /**
   * Replace parent method
   */
  protected function prepare()
  {
    // do not run the parent method
    $read_class_name = lib::get_class_name( 'service\read' );
    $read_class_name::prepare();
  }

  /**
   * Replace parent method
   */
  protected function get_record_count()
  {
    $db_response = $this->get_parent_record();
    $modifier = clone $this->modifier;
    $modifier->join( 'question', 'answer.question_id', 'question.id', '', NULL, true );
    return $db_response->get_answer_count( $modifier );
  }

  /**
   * Replace parent method
   */
  protected function get_record_list()
  {
    $db_response = $this->get_parent_record();
    $modifier = clone $this->modifier;
    $modifier->join( 'language', 'answer.language_id', 'language.id' );
    $modifier->join( 'question', 'answer.question_id', 'question.id', '', NULL, true );

    foreach( $this->select->get_alias_list() as $alias )
    {
      if( '' === $this->select->get_alias_table( $alias ) )
      {
        $details = $this->select->get_alias_details( $alias );
        if( $details['table_prefix'] )
        {
          $this->select->remove_column_by_alias( $alias );
          $this->select->add_table_column( 'question', $details['column'], $alias, true, $details['type'], $details['format'] );
        }
      }
    }
    return $db_response->get_answer_list( $this->select, $modifier );
  }
}
