<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\answer;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      $filename = $this->get_argument( 'filename', NULL );
      if( !is_null( $filename ) )
      {
        // make sure the response_device record exists
        $this->db_response_device = $this->get_leaf_record()->get_response_device();
        if( is_null( $this->db_response_device ) )
        {
          $this->get_status()->set_code( 404 );
        }
      }
      else if( 'launch_device' ==  $this->get_argument( 'action', NULL ) )
      {
        // make sure a device exists and is online
        $db_device = $this->get_leaf_record()->get_question()->get_device();
        if( is_null( $db_device ) )
        {
          $this->set_data( 'Cannot launch since the question has not been associated with any device.' );
          $this->get_status()->set_code( 306 );
        }
        else if( !$db_device->get_status() )
        {
          $this->set_data( 'Cannot launch since the device service is not responding.' );
          $this->get_status()->set_code( 306 );
        }
      }
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $filename = $this->get_argument( 'filename', NULL );
    if( !is_null( $filename ) )
    {
      $directory = $this->db_response_device->get_directory();
      $local_filename = sprintf( '%s/%s', $directory, $filename );
      $file_contents = $this->get_file_as_raw();

      if( is_null( $file_contents ) || !$file_contents )
      {
        // remove the file instead of writing it
        if( file_exists( $local_filename ) ) unlink( $local_filename );
      }
      else
      {
        // make sure the directory exists before writing the file
        if( !file_exists( $directory ) ) mkdir( $directory, 0755, true );
        file_put_contents( $local_filename, $this->get_file_as_raw() );
      }
    }
    else if( 'launch_device' == $this->get_argument( 'action', NULL ) )
    {
      // launch the associated device
      $this->db_response_device = $this->get_leaf_record()->launch_device();
      $this->set_data( [
        'uuid' => $this->db_response_device->uuid,
        'status' => $this->db_response_device->status
      ] );
    }
  }

  /**
   * The response_device associated with the request (only set when required)
   * @var database\response_device
   * @private
   */
  private $db_response_device = NULL;
}
