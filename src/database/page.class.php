<?php
/**
 * page.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\database;
use cenozo\lib, cenozo\log, linden\util;

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
    if( 1 == preg_match( '/^response=([0-9]+)/', $identifier, $parts ) )
    {
      // return the current page for the provided response
      $db_response = lib::create( 'database\response', $parts[1] );
      return lib::create( 'database\page', $db_response->page_id );
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * TODO: document
   */
  public function get_previous_page()
  {
    $db_previous_page = static::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->module_id, $this->rank - 1 )
    );

    if( is_null( $db_previous_page ) )
    {
      // check if there is a previous module
      $db_previous_module = $this->get_module()->get_previous_module();
      if( !is_null( $db_previous_module ) ) $db_previous_page = $db_previous_module->get_last_page();
    }

    return $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next_page()
  {
    $db_next_page = static::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->module_id, $this->rank + 1 )
    );

    if( is_null( $db_next_page ) )
    {
      // check if there is a next module
      $db_next_module = $this->get_module()->get_next_module();
      if( !is_null( $db_next_module ) ) $db_next_page = $db_next_module->get_first_page();
    }

    return $db_next_page;
  }
}
