<?php
/**
 * delete.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\module;
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
    $stage_class_name = lib::get_class_name( 'database\stage' );

    // if the module is set as a stage's first/last module then move to the neighbour before we delete this module
    $db_old_module = $this->get_leaf_record();

    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'first_module_id', '=', $db_old_module->id );
    $modifier->or_where( 'last_module_id', '=', $db_old_module->id );
    foreach( $stage_class_name::select_objects( $modifier ) as $db_stage )
    {
      if( $db_stage->first_module_id == $db_old_module->id )
      {
        $db_next_module = $db_old_module->get_next();
        if( !is_null( $db_next_module ) )
        {
          $db_stage->first_module_id = $db_next_module->id;
          $db_stage->save();
        }
      }
      else if( $db_stage->last_module_id == $db_old_module->id )
      {
        $db_prev_module = $db_old_module->get_previous();
        if( !is_null( $db_prev_module ) )
        {
          $db_stage->last_module_id = $db_prev_module->id;
          $db_stage->save();
        }
      }
    }

    parent::execute();
  }
}
