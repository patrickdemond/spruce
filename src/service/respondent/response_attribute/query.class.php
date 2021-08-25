<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent\response_attribute;
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
    // count all response_attributes belonging to the current response
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $modifier = clone $this->modifier;
    $modifier->where( 'response_id', '=', $this->get_parent_record()->get_current_response()->id );
    return $response_attribute_class_name::count( $modifier );
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    // list all response_attributes belonging to the current response
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $modifier = clone $this->modifier;
    $modifier->where( 'response_id', '=', $this->get_parent_record()->get_current_response()->id );
    return $response_attribute_class_name::select( $this->select, $modifier );
  }
}
