<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\lookup_data;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'lookup', 'lookup_data.lookup_id', 'lookup.id' );
    $this->add_list_column( 'indicator_list', 'indicator', 'name', $select, $modifier );
  }
}
