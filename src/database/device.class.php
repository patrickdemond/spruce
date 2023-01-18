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
  public function test_connection()
  {
    $cypress_manager = lib::create( 'business\cypress_manager', $this );
    return $cypress_manager->is_online();
  }

  /**
   * Launches the device by communicating with the cypress service
   * 
   * @argument database\answer $db_answer
   */
  public function launch( $db_answer )
  {
    $cypress_manager = lib::create( 'business\cypress_manager', $this );
    
    // always include the token and language
    $db_response = $db_answer->get_response();
    $data = array(
      'barcode' => $db_response->get_respondent()->token,
      'language' => $db_response->get_language()->code,
      'interviewer' => $db_answer->get_user()->name
    );

    // then include any other data
    foreach( $this->get_device_data_object_list() as $db_device_data )
      $data[$db_device_data->name] = $db_device_data->get_compiled_value( $db_answer );

    return $cypress_manager->launch( $data );
  }
}
