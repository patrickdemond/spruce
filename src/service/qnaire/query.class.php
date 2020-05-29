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
    if( $this->get_argument( 'recalculate_average_times', false ) )
    {
      $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
      $qnaire_class_name::recalculate_average_time();

      $module_class_name = lib::get_class_name( 'database\module' );
      $module_class_name::recalculate_average_time();
      
      $page_class_name = lib::get_class_name( 'database\page' );
      $page_class_name::recalculate_average_time();
    }
    else
    {
      parent::execute();
    }
  }
}
