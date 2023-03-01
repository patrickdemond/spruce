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
   * Launches the device by communicating with the cypress service
   * 
   * @argument database\answer $db_answer
   * @return database/response_device The resulting response_device object referring to the launch request
   */
  public function launch( $db_answer )
  {
    $cypress_manager = lib::create( 'business\cypress_manager', $this );

    // always include the token and language
    $db_response = $db_answer->get_response();
    $data = array(
      'answer_id' => $this->id,
      'barcode' => $db_response->get_respondent()->token,
      'language' => $db_response->get_language()->code,
      'interviewer' => $db_answer->get_user()->name
    );

    // then include any other data
    foreach( $this->get_device_data_object_list() as $db_device_data )
      $data[$db_device_data->name] = $db_device_data->get_compiled_value( $db_answer );

    // if a response device already exists for this response/device, then a response_device record will be found
    $db_response_device = $db_answer->get_response_device();
    if( is_null( $db_response_device ) )
    {
      if( $this->emulate )
      {
        // emulate a response from cypress
        $db_response_device = lib::create( 'database\response_device' );
        $db_response_device->response_id = $db_response->id;
        $db_response_device->device_id = $this->id;
        $db_response_device->uuid = str_replace( '.', '-', uniqid( 'emulate.', true ) );
        $db_response_device->save();
      }
      else
      {
        $db_response_device = $cypress_manager->launch( $data, $db_response );
      }
    }

    $db_response_device->start_datetime = util::get_datetime_object();
    $db_response_device->status = 'in progress';
    $db_response_device->save();

    return $db_response_device;
  }

  /**
   * Aborts the device for a particular UUID
   * 
   * @argument string $uuid
   */
  public function abort( $uuid )
  {
    if( !$this->emulate )
    {
      $cypress_manager = lib::create( 'business\cypress_manager', $this );
      $cypress_manager->abort( $uuid );
    }
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
