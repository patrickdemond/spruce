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
class module extends \cenozo\database\has_rank
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
    $response_class_name = lib::get_class_name( 'database\response' );

    if( preg_match( '/^token=/', $identifier ) )
    {
      // tokens MUST be for groups of for hex numbers delimited by dashes: hhhh-hhhh-hhhh-hhhh
      if( !preg_match( '/^token=([0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4})/', $identifier, $parts ) )
      {
        return NULL;
      }
      else
      {
        // return the current module for the provided response's page
        $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
        if( is_null( $db_response ) ) return NULL;
        $db_page = $db_response->get_page();
        return is_null( $db_page ) ? NULL : lib::create( 'database\module', $db_page->module_id );
      }
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * TODO: document
   */
  public function get_previous_module()
  {
    return static::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $this->rank - 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_next_module()
  {
    return static::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $this->rank + 1 )
    );
  }

  /**
   * TODO: document
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
   * TODO: document
   */
  public function get_last_page()
  {
    $page_class_name = lib::get_class_name( 'database\page' );
    return $page_class_name::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->id, $this->get_page_count() )
    );
  }
}
