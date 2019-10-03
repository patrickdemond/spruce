<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\module;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    if( $select->has_column( 'has_precondition' ) ) $select->add_column( 'precondition IS NOT NULL', 'has_precondition' );

    $db_module = $this->get_resource();
    if( !is_null( $db_module ) )
    {
      // module details
      if( $select->has_column( 'previous_module_id' ) )
      {
        $db_previous_module = $db_module->get_previous_module();
        $select->add_constant( is_null( $db_previous_module ) ? NULL : $db_previous_module->id, 'previous_module_id', 'integer' );
      }
      if( $select->has_column( 'next_module_id' ) )
      {
        $db_next_module = $db_module->get_next_module();
        $select->add_constant( is_null( $db_next_module ) ? NULL : $db_next_module->id, 'next_module_id', 'integer' );
      }
    }
  }
}
