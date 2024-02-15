<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\lookup_item;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'lookup', 'lookup_item.lookup_id', 'lookup.id' );
    $this->add_list_column( 'indicator_list', 'indicator', 'name', $select, $modifier, NULL, NULL, 'name', '; ' );
  }
}
