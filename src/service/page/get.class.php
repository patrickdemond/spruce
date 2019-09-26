<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\page;
use cenozo\lib, cenozo\log, linden\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent method
   */
  public function finish()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );

    parent::finish();

    // if we're asking for the page based on a response then make sure that all answers have been created
    if( 1 == preg_match( '/^response=([0-9]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_response = lib::create( 'database\response', $parts[1] );
      $db_page = $db_response->get_page();

      $question_sel = lib::create( 'database\select' );
      $question_sel->add_column( 'id' );
      foreach( $db_page->get_question_list( $question_sel ) as $question )
      {
        if( is_null( $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $db_response->id, $question['id'] )
        ) ) )
        {
          $db_answer = lib::create( 'database\answer' );
          $db_answer->response_id = $db_response->id;
          $db_answer->question_id = $question['id'];
          $db_answer->save();
        }
      }
    }
  }
}
