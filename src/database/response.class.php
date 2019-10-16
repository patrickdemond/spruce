<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * response: record
 */
class response extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // setup new responses
    if( is_null( $this->id ) )
    {
      $db_qnaire = lib::create( 'database\qnaire', $this->qnaire_id );
      $this->page_id = $db_qnaire->get_first_module()->get_first_page()->id;
      $this->token = static::generate_token();
      $this->start_datetime = util::get_datetime_object();
    }

    parent::save();
  }

  /**
   * Moves the response to the next valid page
   * 
   * TODO: page restriction logic still needs to be applied here
   * @access public
   */
  public function move_to_next_page()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );

    // start by making sure that the current page is complete
    $complete = true;
    $db_page = $this->get_page();
    foreach( $db_page->get_question_object_list() as $db_question )
    {
      $db_answer = $answer_class_name::get_unique_record(
        array( 'response_id', 'question_id' ),
        array( $this->id, $db_question->id )
      );

      if( !$db_answer->is_complete() )
      {
        log::warning( sprintf(
          'Tried to advance response for %s to the next page but the current page "%s" is incomplete.',
          $this->get_participant()->uid,
          $db_page->name
        ) );

        $complete = false;
        break;
      }
    }

    if( $complete )
    {
      $db_next_page = $db_page->get_next_page( $this );
      $this->page_id = is_null( $db_next_page ) ? NULL : $db_next_page->id;
      $this->save();
    }
  }

  /**
   * Moves the response to the previous valid page
   * 
   * TODO: page restriction logic still needs to be applied here
   * @access public
   */
   public function move_to_previous_page()
   {
     $db_previous_page = $this->get_page()->get_previous_page( $this );
     if( !is_null( $db_previous_page ) )
     {
       $this->page_id = $db_previous_page->id;
       $this->save();
     }
   }

  /**
   * Creates a unique token to be used for identifying a response
   * 
   * @access private
   */
  private static function generate_token()
  {
    $created = false;
    $count = 0;
    while( 100 > $count++ )
    {
      $token = sprintf(
        '%s-%s-%s-%s',
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) )
      );

      // make sure it isn't already in use
      if( null == static::get_unique_record( 'token', $token ) ) return $token;
    }

    // if we get here then something is wrong
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique response token.', __METHOD__ );
  }

  public function create_attributes()
  {
    $db_participant = $this->get_participant();

    foreach( $this->get_qnaire()->get_attribute_object_list() as $db_attribute )
    {
      $db_response_attribute = lib::create( 'database\response_attribute' );
      $db_response_attribute->response_id = $this->id;
      $db_response_attribute->attribute_id = $db_attribute->id;
      $db_response_attribute->value = $db_attribute->get_participant_value( $db_participant );
      $db_response_attribute->save();
    }
  }
}
