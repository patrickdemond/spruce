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
class page extends base_qnaire_part
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
      return is_null( $db_response ) || is_null( $db_response->page_id ) ? NULL : lib::create( 'database\page', $db_response->page_id );
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * TODO: document
   */
  public function get_previous()
  {
    $db_previous_page = parent::get_previous();

    if( is_null( $db_previous_page ) )
    {
      $db_previous_module = $this->get_module()->get_previous();
      if( !is_null( $db_previous_module ) ) $db_previous_page = $db_previous_module->get_last_page();
    }

    return $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next()
  {
    $db_next_page = parent::get_next();

    if( is_null( $db_next_page ) )
    {
      $db_next_module = $this->get_module()->get_next();
      if( !is_null( $db_next_module ) ) $db_next_page = $db_next_module->get_first_page();
    }

    return $db_next_page;
  }

  /**
   * TODO: document
   */
  public function get_previous_for_response( $db_response )
  {
    $expression_manager = lib::create( 'business\expression_manager' );

    // start by getting the page one rank lower than the current
    $db_previous_page = $this->get_previous();

    if( !is_null( $db_previous_page ) )
    {
      // make sure the page's module is valid
      $db_module = $db_previous_page->get_module();
      if( !$expression_manager->evaluate( $db_response, $db_module->precondition ) )
      {
        do { $db_module = $db_module->get_previous(); }
        while( !is_null( $db_module ) && !$expression_manager->evaluate( $db_response, $db_module->precondition ) );
        $db_previous_page = is_null( $db_module ) ? NULL : $db_module->get_last_page();
      }

      // if there is a previous page then make sure to test its precondition if a response is included in the request
      if( !is_null( $db_previous_page ) && !$expression_manager->evaluate( $db_response, $db_previous_page->precondition ) )
        $db_previous_page = $db_previous_page->get_previous_for_response( $db_response );
    }

    return $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next_for_response( $db_response )
  {
    $expression_manager = lib::create( 'business\expression_manager' );

    // start by getting the page one rank lower than the current
    $db_next_page = $this->get_next();

    if( !is_null( $db_next_page ) )
    {
      // make sure the page's module is valid
      $db_module = $db_next_page->get_module();
      if( !$expression_manager->evaluate( $db_response, $db_module->precondition ) )
      {
        do
        {
          // delete any answer associated with the skipped module
          $db_response->delete_answers_in_module( $db_module );
          $db_module = $db_module->get_next();
        }
        while( !is_null( $db_module ) && !$expression_manager->evaluate( $db_response, $db_module->precondition ) );
        $db_next_page = is_null( $db_module ) ? NULL : $db_module->get_first_page();
      }

      // if there is a next page then make sure to test its precondition if a response is included in the request
      if( !is_null( $db_next_page ) && !$expression_manager->evaluate( $db_response, $db_next_page->precondition ) )
      {
        $db_response->delete_answers_in_page( $db_next_page );
        $db_next_page = $db_next_page->get_next_for_response( $db_response );
      }
    }

    return $db_next_page;
  }

  /**
   * TODO: document
   */
  public function get_overall_rank()
  {
    $db_module = $this->get_module();

    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_constant( 'COUNT(*)', 'total', 'integer', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->where( 'module.qnaire_id', '=', $db_module->qnaire_id );
    $modifier->where( 'module.rank', '<', $db_module->rank );
    return static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) ) + $this->rank;
  }

  /**
   * TODO: document
   */
  public function get_first_question()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    return $question_class_name::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_last_question()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    return $question_class_name::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->id, $this->get_question_count() )
    );
  }
}
