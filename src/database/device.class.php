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
    $juniper_manager = lib::create( 'business\juniper_manager', $this );
    return $juniper_manager->is_online();
  }

  /**
   * Launches the device by communicating with the Juniper service
   */
  public function launch()
  {
    $juniper_manager = lib::create( 'business\juniper_manager', $this );
    return $juniper_manager->launch();
  }
}
