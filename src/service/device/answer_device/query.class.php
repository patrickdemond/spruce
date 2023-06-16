<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\device\answer_device;
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
    $db_device = $this->get_parent_record();

    // count all answer_devices belonging to this device
    $answer_device_class_name = lib::get_class_name( 'database\answer_device' );
    $modifier = clone $this->modifier;
    $modifier->where( 'question.device_id', '=', $db_device->id );
    $count = $answer_device_class_name::count( $modifier );

    return $count;
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $db_device = $this->get_parent_record();

    // list all answer_devices belonging to this device
    $answer_device_class_name = lib::get_class_name( 'database\answer_device' );
    $modifier = clone $this->modifier;
    $modifier->where( 'question.device_id', '=', $db_device->id );
    $list = $answer_device_class_name::select( $this->select, $modifier );

    return $list;
  }
}
