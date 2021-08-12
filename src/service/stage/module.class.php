<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\stage;
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
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'qnaire', 'stage.qnaire_id', 'qnaire.id' );
    $modifier->join( 'module', 'stage.first_module_id', 'first_module.id', '', 'first_module' );
    $modifier->join( 'module', 'stage.last_module_id', 'last_module.id', '', 'last_module' );

    if( $select->has_column( 'module_count' ) )
      $select->add_column( 'last_module.rank - first_module.rank + 1', 'module_count', false );
    if( $select->has_column( 'has_precondition' ) )
      $select->add_column( 'precondition IS NOT NULL', 'has_precondition' );
  }
}
