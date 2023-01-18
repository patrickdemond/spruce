<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\device;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent method
   */
  public function execute()
  {
    $action = $this->get_argument( 'action', NULL );
    if( 'status' == $action )
    {
      // instead of returning the device's details test the connection and return the result
      $db_device = $this->get_leaf_record();
      $this->set_data( util::json_encode( $db_device->get_status() ) );
    }
    else
    {
      parent::execute();
    }
  }
}
