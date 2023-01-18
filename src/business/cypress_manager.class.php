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
   * @param database\device|string $db_device
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
        $status = $this->send( preg_replace( '#/.*#', '', $this->db_device->url ) );
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
   * @return varies
   * @access public
   */
  public function launch( $data = NULL )
  {
    return $this->send( $this->db_device->url, 'POST', $data );
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
    else if( 'PATCH' == $method )
    {
      curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'PATCH' );
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
   * The number of seconds to wait before giving up on connecting to the device
   * @var integer
   * @access protected
   */
  protected $timeout = 5;
}
