<?php
/**
 * device.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * device: record
 */
class device extends \cenozo\database\record
{
  /**
   * Test a detached instance's connection to the parent beartooth and pine servers
   */
  public function get_status()
  {
    // when in emulate mode the status will always be 
    if( $this->emulate ) return util::json_decode( '{ "status": "emulation mode" }' );

    $cypress_manager = lib::create( 'business\cypress_manager', $this );
    return $cypress_manager->get_status();
  }

  /**
   * Creates a device from an object
   * @param object $device
   * @param database\qnaire $db_qnaire The qnaire to associate the device to
   * @return database\device
   * @static
   */
  public static function create_from_object( $device, $db_qnaire )
  {
    $db_device = new static();
    $db_device->qnaire_id = $db_qnaire->id;
    $db_device->name = $device->name;
    $db_device->url = $device->url;
    $db_device->save();

    // add all device data
    foreach( $device->device_data_list as $device_data )
    {
      $db_device_data = lib::create( 'database\device_data' );
      $db_device_data->device_id = $db_device->id;
      $db_device_data->name = $device_data->name;
      $db_device_data->code = $device_data->code;
      $db_device_data->save();
    }

    return $db_device;
  }
}
