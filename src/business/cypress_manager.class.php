<?php
/**
 * cypress_manager.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Manages communication with Cypress services for communication with medical devices
 */
class cypress_manager extends \cenozo\base_object
{
  /**
   * Constructor.
   * 
   * @param database\device $db_device
   * @access protected
   */
  public function __construct( $db_device )
  {
    if( is_string( $db_device ) )
    {
      $device_class_name = lib::get_class_name( 'database\device' );
      $db_device = $device_class_name::get_unique_record( 'name', $db_device );
    }

    $this->db_device = $db_device;
  }

  /**
   * Returns the Cypress server's status (NULL
   * @return object (NULL if server is unreachable)
   * @access public
   */
  public function get_status()
  {
    $status = NULL;
    if( !is_null( $this->db_device ) )
    {
      try
      {
        // call the base of the URL to test if Cypress is online
        $status = $this->send( sprintf( '%s/status', $this->db_device->url ) );
      }
      catch ( \cenozo\exception\runtime $e )
      {
        // ignore errors
      }
    }

    return $status;
  }

  /**
   * Attempts to launch a device by sending a POST request to the cypress service
   * 
   * @param array $data An associative array of data to send to Cypress
   * @param database\response $db_answer The answer that the device is to return it's data to
   * @return database\response_device The resulting response_device record
   * @access public
   */
  public function launch( $data, $db_answer )
  {
    $response_device_class_name = lib::get_class_name( 'database\response_device' );

    // send a post request to cypress to start the device, it should respond with a UUID
    $uuid = $this->send( $this->db_device->url, 'POST', $data );
    if( !$uuid )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Invalid UUID returned from Cypress while launching %s', $this->db_device->name ),
        __METHOD__
      );
    }

    // Cypress will respond with a UUID that will be used to refer to this respondent/device in the future
    $db_response = $db_answer->get_response();
    $db_response_device = $response_device_class_name::get_unique_record( 'uuid', $uuid );

    if( is_null( $db_response_device ) )
    {
      $db_response_device = lib::create( 'database\response_device' );
      $db_response_device->response_id = $db_response->id;
      $db_response_device->device_id = $this->db_device->id;
      $db_response_device->uuid = $uuid;
    }
    else
    {
      if( $db_response_device->response_id != $db_response->id ||
          $db_response_device->device_id != $this->db_device->id )
      {
        throw lib::create( 'exception\runtime',
          sprintf(
            'Cypress responded with conflicting UUID "%s" while launching device "%s" for participant "%s"',
            $uuid,
            $this->db_device->name,
            $db_response->get_respondent()->get_participant()->uid
          ),
          __METHOD__
        );
      }
    }

    return $db_response_device;
  }

  /**
   * Requests that the device aborts a particular run by UUID
   * 
   * @param string $uuid The UUID of the exam that should be aborted
   * @access public
   */
  public function abort( $uuid )
  {
    try
    {
      // send a delete request to cypress to abort the device
      $this->send( sprintf( '%s/%s', $this->db_device->url, $uuid ), 'DELETE', $data );
    }
    catch ( \cenozo\exception\runtime $e )
    {
      // ignore 404, it just means the UUID has already been cancelled
      if( !preg_match( sprintf( '/Got response code 404/', $code ), $e->get_raw_message() ) )
      {
        // report other errors to the log but otherwise ignore them
        log::error( $e->get_raw_message() );
      }
    }
  }

  /**
   * Sends curl requests
   * 
   * @param string $api_path The internal cenozo path (not including base url)
   * @return varies
   * @access public
   */
  private function send( $api_path, $method = 'GET', $data = NULL )
  {
    $setting_manager = lib::create( 'business\setting_manager' );
    $user = $setting_manager->get_setting( 'utility', 'username' );
    $pass = $setting_manager->get_setting( 'utility', 'password' );
    $header_list = array( sprintf( 'Authorization: Basic %s', base64_encode( sprintf( '%s:%s', $user, $pass ) ) ) );

    $code = 0;

    // set URL and other appropriate options
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $api_path );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, $this->timeout );

    if( 'POST' == $method )
    {
      curl_setopt( $curl, CURLOPT_POST, true );
    }
    else if( 'GET' != $method )
    {
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
    }

    if( !is_null( $data ) )
    {
      $header_list[] = 'Content-Type: application/json';
      curl_setopt( $curl, CURLOPT_POSTFIELDS, util::json_encode( $data ) );
    }

    curl_setopt( $curl, CURLOPT_HTTPHEADER, $header_list );

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when trying %s request to %s.  Message: %s',
                 curl_errno( $curl ),
                 $method,
                 $this->db_device->name,
                 curl_error( $curl ) ),
        __METHOD__ );
    }
    
    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 204 == $code || 300 <= $code )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got response code %s "%s" when trying %s request to %s.',
                 $code,
                 $response,
                 $method,
                 $this->db_device->name ),
        __METHOD__ );
    }

    return util::json_decode( $response );
  }

  /**
   * The device to connect to
   * @var database\device
   * @access protected
   */
  protected $db_device = NULL;

  /**
   * The active device_response record
   * @var database\device_response
   * @access protected
   */
  protected $db_device_response = NULL;

  /**
   * The number of seconds to wait before giving up on connecting to the device
   * @var integer
   * @access protected
   */
  protected $timeout = 5;
}
