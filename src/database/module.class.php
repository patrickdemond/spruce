<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * module: record
 */
class module extends base_qnaire_part
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'qnaire';

  /**
   * Override parent method
   */
  public static function get_record_from_identifier( $identifier )
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    if( 1 == preg_match( '/^token=([^;\/]+)/', $identifier, $parts ) )
    {
      // return the current module for the provided respondent's current response's page
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
      if( is_null( $db_response ) ) return NULL;
      $db_page = $db_response->get_page();
      return is_null( $db_page ) ? NULL : lib::create( 'database\module', $db_page->module_id );
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * Returns the previous module for a response
   * 
   * This function will return the previous module whose precondition matches the given response, not necessarily the
   * previous module in the qnaire
   * @param database\response $db_response
   * @return database\module
   */
  public function get_previous_for_response( $db_response )
  {
    $expression_manager = lib::create( 'business\expression_manager', $db_response );

    // start by getting the module one rank lower than the current
    $db_previous_module = $this->get_previous();

    // if there is a previous module then make sure to test its precondition if a response is included in the request
    try
    {
      if( !is_null( $db_previous_module ) &&
          !is_null( $db_previous_module->precondition ) &&
          !$expression_manager->evaluate( $db_previous_module->precondition ) )
        $db_previous_module = $db_previous_module->get_previous_for_response( $db_response );
    }
    catch( \cenozo\exception\runtime $e )
    {
      if( is_null( $db_response ) || $db_response->get_qnaire()->debug )
        throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__ );

      // if we're not in debug mode then log it and assume the precondition failed
      log::error( $e->get_raw_message() );
      $db_previous_module = $db_previous_module->get_previous_for_response( $db_response );
    }

    return $db_previous_module;
  }

  /**
   * Returns the next module for a response
   * 
   * This function will return the next module whose precondition matches the given response, not necessarily the
   * next module in the qnaire
   * @param database\response $db_response
   * @return database\module
   */
  public function get_next_for_response( $db_response = NULL )
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $expression_manager = lib::create( 'business\expression_manager', $db_response );

    // start by getting the module one rank higher than the current
    $db_next_module = $this->get_next();

    // if there is a next module then make sure to test its precondition if a response is included in the request
    if( !is_null( $db_next_module ) && !is_null( $db_next_module->precondition ) )
    {
      try
      {
        if( !$expression_manager->evaluate( $db_next_module->precondition ) )
        {
          // before proceeding, delete any answer associated with the skipped module
          foreach( $db_next_module->get_page_object_list() as $db_page )
          {
            $select = lib::create( 'database\select' );
            $select->add_column( 'id' );
            foreach( $db_page->get_question_list( $select ) as $question )
            {
              $db_answer = $answer_class_name::get_unique_record(
                array( 'response_id', 'question_id' ),
                array( $db_response->id, $question['id'] )
              );
              if( !is_null( $db_answer ) ) $db_answer->delete();
            }
          }

          // now advance to the next module
          $db_next_module = $db_next_module->get_next_for_response( $db_response );
        }
      }
      catch( \cenozo\exception\runtime $e )
      {
        if( is_null( $db_response ) || $db_response->get_qnaire()->debug )
          throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__ );

        // if we're not in debug mode then log it and assume the precondition failed
        log::error( $e->get_raw_message() );
        $db_previous_module = $db_previous_module->get_previous_for_response( $db_response );
      }
    }

    return $db_next_module;
  }

  /**
   * Returns the first page in this module
   * @return database\page
   */
  public function get_first_page()
  {
    $page_class_name = lib::get_class_name( 'database\page' );
    return $page_class_name::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * Returns the last page in this module
   * @return database\page
   */
  public function get_last_page()
  {
    $page_class_name = lib::get_class_name( 'database\page' );
    return $page_class_name::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->id, $this->get_page_count() )
    );
  }

  /**
   * Clones another module
   * @param database\module $db_source_module
   */
  public function clone_from( $db_source_module )
  {
    parent::clone_from( $db_source_module );

    // replace all existing module options with those from the clone source
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'module_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM page %s', $modifier->get_sql() ) );

    foreach( $db_source_module->get_page_object_list() as $db_source_page )
    {
      $db_page = lib::create( 'database\page' );
      $db_page->module_id = $this->id;
      $db_page->rank = $db_source_page->rank;
      $db_page->name = $db_source_page->name;
      $db_page->clone_from( $db_source_page );
    }
  }

  /**
   * Recaluclates the average time taken to complete the module
   */
  public static function recalculate_average_time()
  {
    $select = lib::create( 'database\select' );
    $select->from( 'module' );
    $select->add_column( 'id' );
    $select->add_column( 'SUM( time ) / COUNT( DISTINCT page_time.response_id )', 'average_time', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->left_join( 'page', 'module.id', 'page.module_id' );
    $modifier->left_join( 'page_time', 'page.id', 'page_time.page_id' );
    $modifier->where( 'IFNULL( page_time.time, 0 )', '<=', 'IFNULL( page.max_time, 0 )', false );
    $modifier->group( 'module.id' );

    static::db()->execute( sprintf(
      "REPLACE INTO module_average_time( module_id, time )\n%s %s",
      $select->get_sql(),
      $modifier->get_sql()
    ) );
  }
}
