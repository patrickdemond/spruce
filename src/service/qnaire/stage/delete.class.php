<?php
/**
 * delete.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\stage;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all delete services.
 */
class delete extends \cenozo\service\delete
{
  /**
   * Extends parent method
   */
  protected function execute()
  {
    // update siblings
    $module_class_name = lib::get_class_name( 'database\module' );

    // determine the previous and next stages
    $db_old_stage = $this->get_leaf_record();
    $db_prev_stage = $db_old_stage->get_previous();
    $db_next_stage = $db_old_stage->get_next();

    // adjust existing stage first/last modules to take up this stage's modules
    if( !is_null( $db_prev_stage ) )
    {
      $db_prev_stage->last_module_id = $db_old_stage->last_module_id;
      $db_prev_stage->save();
    }
    else if( !is_null( $db_next_stage ) )
    {
      $db_next_stage->first_module_id = $db_old_stage->first_module_id;
      $db_next_stage->save();
    }

    parent::execute();
  }
}
