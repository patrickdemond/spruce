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
   * Override parent method
   */
  public function get_file_as_array()
  {
    $user_class_name = lib::get_class_name( 'database\user' );

    // replace username with a user_id (if the user exists)
    $patch_array = parent::get_file_as_array();

    if( array_key_exists( 'username', $patch_array ) )
    {
      $db_user = $user_class_name::get_unique_record( 'name', $patch_array['username'] );
      unset( $patch_array['username'] );
      if( !is_null( $db_user ) ) $patch_array['user_id'] = $db_user->id;
    }

    return $patch_array;
  }

  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      if( 'launch_device' ==  $this->get_argument( 'action', NULL ) )
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

    $db_answer = $this->get_leaf_record();
    $filename = $this->get_argument( 'filename', NULL );

    if( !is_null( $filename ) )
    {
      $directory = $db_answer->get_data_directory();
      $local_filename = sprintf( '%s/%s', $directory, $filename );
      $file_contents = $this->get_file_as_raw();

      if( is_null( $file_contents ) || !$file_contents )
      {
        // remove the file instead of writing it
        if( file_exists( $local_filename ) ) unlink( $local_filename );

        // remove empty directories
        if( file_exists( $directory ) && 0 == count( $db_answer->get_data_files() ) ) rmdir( $directory );
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
      $db_response_device = $this->get_leaf_record()->launch_device();
      $this->set_data( [
        'uuid' => $db_response_device->uuid,
        'status' => $db_response_device->status
      ] );
    }
  }

  /**
   * Used to track metadata about the user providing answer data
   * @var array;
   * @access protected
   */
  protected $user_metadata = [];
}
