<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent\problem_report;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function get_leaf_parent_relationship()
  {
    $relationship_class_name = lib::get_class_name( 'database\relationship' );
    return $relationship_class_name::MANY_TO_MANY;

  }

  /**
   * Extend parent method
   */
  protected function get_record_count()
  {
    $db_current_response = $this->get_parent_record()->get_current_response();

    $count = 0;
    if( !is_null( $db_current_response ) )
    {
      // count all problem_reports belonging to the current response
      $problem_report_class_name = lib::get_class_name( 'database\problem_report' );
      $modifier = clone $this->modifier;
      $modifier->where( 'response_id', '=', $this->get_parent_record()->get_current_response()->id );
      $count = $problem_report_class_name::count( $modifier );
    }

    return $count;
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $db_current_response = $this->get_parent_record()->get_current_response();

    $list = array();
    if( !is_null( $db_current_response ) )
    {
      // list all problem_reports belonging to the current response
      $problem_report_class_name = lib::get_class_name( 'database\problem_report' );
      $modifier = clone $this->modifier;
      $modifier->where( 'response_id', '=', $this->get_parent_record()->get_current_response()->id );
      $list = $problem_report_class_name::select( $this->select, $modifier );
    }

    return $list;
  }
}
