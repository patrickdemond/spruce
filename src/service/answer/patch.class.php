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
  public function get_file_as_object()
  {
    $user_class_name = lib::get_class_name( 'database\user' );
    $language_class_name = lib::get_class_name( 'database\language' );

    // if the object has no user_id and there's a referring user then use it
    $patch_object = parent::get_file_as_object();

    if( !property_exists( $patch_object, 'user_id' ) )
    {
      $db_referring_user = lib::create( 'business\session' )->get_referring_user();
      if( !is_null( $db_referring_user ) ) $patch_object->user_id = $db_referring_user->id;
    }

    if( !property_exists( $patch_object, 'alternate_id' ) )
    {
      $db_referring_alternate = lib::create( 'business\session' )->get_referring_alternate();
      if( !is_null( $db_referring_alternate ) ) $patch_object->alternate_id = $db_referring_alternate->id;
    }

    if( property_exists( $patch_object, 'language' ) )
    {
      $db_language = $language_class_name::get_unique_record( 'code', $patch_object->language );
      unset( $patch_object->language );
      if( !is_null( $db_language ) ) $patch_object->language_id = $db_language->id;
    }

    if( property_exists( $patch_object, 'value' ) && is_object( $patch_object->value ) )
    {
      $patch_object->value = util::json_encode( $patch_object->value );
    }

    return $patch_object;
  }

  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      $db_answer = $this->get_leaf_record();
      if( 'launch_device' ==  $this->get_argument( 'action', NULL ) )
      {
        $db_device = $db_answer->get_question()->get_device();

        if( is_null( $db_device ) )
        {
          $this->set_data( 'Cannot launch since the question has not been associated with any device.' );
          $this->get_status()->set_code( 306 );
        }
        else
        {
          $out_of_sync = $db_answer->get_response()->get_out_of_sync( 'launch device', $db_answer );
          if( !is_null( $out_of_sync ) )
          {
            $this->set_data( $out_of_sync );
            $this->get_status()->set_code( 409 );
          }
          // make sure a device exists and is online
          else if( !$db_device->get_status() )
          {
            $this->set_data( 'Cannot launch since the device service is not responding.' );
            $this->get_status()->set_code( 306 );
          }
        }
      }
      else
      {
        $out_of_sync = $db_answer->get_response()->get_out_of_sync( 'set answer', $db_answer );
        if( !is_null( $out_of_sync ) )
        {
          $this->set_data( $out_of_sync );
          $this->get_status()->set_code( 409 );
        }
      }
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    $site_class_name = lib::get_class_name( 'database\site' );

    parent::execute();

    $db_answer = $this->get_leaf_record();
    $filename = $this->get_argument( 'filename', NULL );

    // possibly set or change the response's site
    $db_response = $db_answer->get_response();
    if( is_null( $db_response->site_id ) )
    {
      $db_response->site_id = lib::create( 'business\session' )->get_effective_site()->id;
      $db_response->save();
    }

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
      $db_answer_device = $this->get_leaf_record()->get_answer_device();
      $db_answer_device->launch();
      $this->set_data( [
        'uuid' => $db_answer_device->uuid,
        'status' => $db_answer_device->status
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
