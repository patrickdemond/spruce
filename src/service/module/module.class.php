<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\module;
use cenozo\lib, cenozo\log, linden\util;

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
  }
}
