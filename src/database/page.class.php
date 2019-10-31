<?php
/**
 * page.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * page: record
 */
class page extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'module';

  /**
   * Override parent method
   */
  public static function get_record_from_identifier( $identifier )
  {
    $response_class_name = lib::get_class_name( 'database\response' );

    if( 1 == preg_match( '/^token=([^;\/]+)/', $identifier, $parts ) )
    {
      // return the current page for the provided response
      $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
      return is_null( $db_response ) ? NULL : lib::create( 'database\page', $db_response->page_id );
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * TODO: document
   */
  public function get_previous_page( $db_response = NULL )
  {
    $expression_manager = lib::create( 'business\expression_manager' );

    // start by getting the page one rank lower than the current
    $db_previous_page = static::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->module_id, $this->rank - 1 )
    );

    if( is_null( $db_previous_page ) )
    {
      // check if there is a previous module
      $db_previous_module = $this->get_module()->get_previous_module( $db_response );
      if( !is_null( $db_previous_module ) ) $db_previous_page = $db_previous_module->get_last_page();
    }

    // if there is a previous page then make sure to test its precondition if a response is included in the request
    return !is_null( $db_previous_page ) &&
           !is_null( $db_response ) &&
           !is_null( $db_previous_page->precondition ) &&
           !$expression_manager->evaluate( $db_response, $db_previous_page->precondition ) ?
      $db_previous_page->get_previous_page( $db_response ) : $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next_page( $db_response = NULL )
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $expression_manager = lib::create( 'business\expression_manager' );

    // start by getting the page one rank higher than the current
    $db_next_page = static::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->module_id, $this->rank + 1 )
    );

    if( is_null( $db_next_page ) )
    {
      // check if there is a next module
      $db_next_module = $this->get_module()->get_next_module( $db_response );
      if( !is_null( $db_next_module ) ) $db_next_page = $db_next_module->get_first_page();
    }

    // if there is a next page then make sure to test its precondition if a response is included in the request
    if( !is_null( $db_next_page ) &&
        !is_null( $db_response ) &&
        !is_null( $db_next_page->precondition ) )
    {
      if( !$expression_manager->evaluate( $db_response, $db_next_page->precondition ) )
      {
        // before proceeding to delete any answer associated with the skipped page
        $select = lib::create( 'database\select' );
        $select->add_column( 'id' );
        foreach( $db_next_page->get_question_list( $select ) as $question )
        {
          $db_answer = $answer_class_name::get_unique_record(
            array( 'response_id', 'question_id' ),
            array( $db_response->id, $question['id'] )
          );
          if( !is_null( $db_answer ) ) $db_answer->delete();
        }

        // now advance to the next page
        $db_next_page = $db_next_page->get_next_page( $db_response );
      }
    }

    return $db_next_page;
  }
}
