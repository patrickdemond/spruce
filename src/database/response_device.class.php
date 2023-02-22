<?php
/**
 * response_device.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * response_device: record
 */ 
class response_device extends \cenozo\database\record
{
  /**
   * Extend parent method
   */
  public function delete()
  {
    $db_device = $this->get_device();
    $uuid = $this->uuid;

    parent::delete();

    // abort the device
    $db_device->abort( $uuid );
  }
}
