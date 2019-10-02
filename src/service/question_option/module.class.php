<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace spruce\service\question_option;
use cenozo\lib, cenozo\log, spruce\util;

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
    if( $select->has_column( 'has_precondition' ) ) $select->add_column( 'precondition IS NOT NULL', 'has_precondition' );
  }
}
