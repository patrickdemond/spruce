<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extends parent method
   */
  protected function execute()
  {
    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $page_class_name = lib::get_class_name( 'database\page' );

    if( $this->get_argument( 'recalculate_max_times', false ) )
    {
      $page_class_name::recalculate_max_time();
    }
    else if( $this->get_argument( 'recalculate_average_times', false ) )
    {
      $qnaire_class_name::recalculate_average_time();
      $module_class_name::recalculate_average_time();
      $page_class_name::recalculate_average_time();
    }
    else
    {
      parent::execute();
    }
  }
}
