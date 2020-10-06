<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

abstract class base_qnaire_part_query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function get_record_list()
  {
    $list = parent::get_record_list();
    return $list;
  }
}
