<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\module\page;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    $list = parent::get_record_list();

    $db_response = NULL;
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
    }

    // process any hidden text
    $expression_manager = lib::create(
      'business\expression_manager',
      is_null( $db_response ) ? $this->get_parent_record()->get_qnaire() : $db_response
    );
    foreach( $list as $index => $record ) $expression_manager->process_hidden_text( $record );

    return $list;
  }
}
