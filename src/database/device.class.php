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
   * Test the connection to the device
   */
  public function get_status()
  {
    // when in emulate mode the status will always be "emulation mode"
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
    $db_device->emulate = $device->emulate;
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

  /**
   * Applies a patch file to the device and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $apply = false )
  {
    $device_data_class_name = lib::get_class_name( 'database\device_data' );

    $difference_list = [];

    foreach( $patch_object as $property => $value )
    {
      if( 'device_data_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->device_data_list as $device )
        {
          $db_device_data = $device_data_class_name::get_unique_record(
            [ 'device_id', 'name' ],
            [ $this->id, $device->name ]
          );

          if( is_null( $db_device_data ) )
          {
            if( $apply )
            {
              $db_device_data = lib::create( 'database\device_data' );
              $db_device_data->device_id = $this->id;
              $db_device_data->name = $device->name;
              $db_device_data->code = $device->code;
              $db_device_data->save();
            }
            else $add_list[] = $device;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $device as $property => $value )
              if( $db_device_data->$property != $device->$property )
                $diff[$property] = $device->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_device_data->code = $device->code;
                $db_device_data->save();
              }
              else
              {
                $change_list[$device->name] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_device_data_object_list() as $db_device_data )
        {
          $found = false;
          foreach( $patch_object->device_data_list as $device )
          {
            if( $db_device_data->name == $device->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_device_data->delete();
            else $remove_list[] = $db_device_data->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['device_data_list'] = $diff_list;
      }
      else
      {
        if( $patch_object->$property != $this->$property )
        {
          if( $apply ) $this->$property = $patch_object->$property;
          else $difference_list[$property] = $patch_object->$property;
        }
      }
    }

    if( $apply )
    {
      $this->save();
      return null;
    }
    else return 0 == count( $difference_list ) ? NULL : (object)$difference_list;
  }
}
