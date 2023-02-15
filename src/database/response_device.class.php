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

  /**
   * Returns the directory that files uploaded for this response_device is stored
   */
  public function get_directory()
  {
    $db_respondent = $this->get_response()->get_respondent();
    return sprintf(
      '%s/%s/%s/%s',
      DEVICE_FILES_PATH,
      $db_respondent->get_qnaire()->name,
      $this->get_device()->name,
      $db_respondent->get_participant()->uid
    );
  }

  /**
   * Returns a list of all files stored in the response_device's directory
   */
  public function get_files()
  {
    return glob( sprintf( '%s/*', $this->get_directory() ) );
  }
}
