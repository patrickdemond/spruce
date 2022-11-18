<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\lookup;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $this->add_count_column( 'indicator_count', 'indicator', $select, $modifier );
    $this->add_count_column( 'lookup_item_count', 'lookup_item', $select, $modifier );
  }
}
