<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent method
   */
  public function execute()
  {
    $response_class_name = lib::get_class_name( 'database\response' );

    parent::execute();

    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $this->db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
      $data = $this->data;
      $data['uid'] = $this->db_response->get_participant()->uid;
      $this->set_data( $data );
    }
  }

  /**
   * Extend parent method
   */
  public function finish()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );

    parent::finish();

    // if we're asking for the page based on a response then make sure that all answers have been created
    if( !is_null( $this->db_response ) )
    {
      $db_page = $this->db_response->get_page();

      // create answers for all questions on this page if they don't already exist
      $question_sel = lib::create( 'database\select' );
      $question_sel->add_column( 'id' );
      $question_mod = lib::create( 'database\modifier' );
      $question_mod->where( 'type', '!=', 'comment' ); // comments don't have answers
      foreach( $db_page->get_question_list( $question_sel, $question_mod ) as $question )
      {
        if( is_null( $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $this->db_response->id, $question['id'] )
        ) ) )
        {
          $db_answer = lib::create( 'database\answer' );
          $db_answer->response_id = $this->db_response->id;
          $db_answer->question_id = $question['id'];
          $db_answer->save();
        }
      }
    }
  }

  /**
   * TODO: document
   */
  private $db_response = NULL;
}
