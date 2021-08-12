<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\stage;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function execute()
  {
    parent::execute();

    // update siblings
    $stage_class_name = lib::get_class_name( 'database\stage' );
    $module_class_name = lib::get_class_name( 'database\module' );

    // determine the previous and next stages
    $db_new_stage = $this->get_leaf_record();
    $db_prev_stage = $db_new_stage->get_previous();
    $db_next_stage = $db_new_stage->get_next();

    // adjust existing stage first/last modules to make room for this one
    if( !is_null( $db_prev_stage ) )
    {
      $db_module = $db_new_stage->get_first_module()->get_previous();
      if( !is_null( $db_module ) && $db_prev_stage->last_module_id != $db_module->id )
      {
        $db_prev_stage->last_module_id = $db_module->id;
        $db_prev_stage->save();
      }
    }

    if( !is_null( $db_next_stage ) )
    {
      $db_module = $db_new_stage->get_last_module()->get_next();
      if( !is_null( $db_module ) && $db_next_stage->first_module_id != $db_module->id )
      {
        $db_next_stage->first_module_id = $db_module->id;
        $db_next_stage->save();
      }
    }
  }
}
