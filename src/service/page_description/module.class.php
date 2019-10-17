<?php
/**
 * page_description.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page_description;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \pine\service\base_description_module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
  }
}
