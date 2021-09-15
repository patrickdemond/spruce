<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( 300 > $this->get_status()->get_code() )
    {
      $action = $this->get_argument( 'action', NULL );
      if( 'launch_device' == $action )
      {
        // make sure a device exists and is online
        $db_device = $this->get_leaf_record()->get_device();
        if( is_null( $db_device ) )
        {
          $this->set_data( 'Cannot launch since the question has not been associated with any device.' );
          $this->get_status()->set_code( 306 );
        }
        else if( !$db_device->test_connection() )
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
    $action = $this->get_argument( 'action', NULL );
    if( 'launch_device' == $action )
    {
      // instead of returning the question's details launch the associated device
      $db_device = $this->get_leaf_record()->get_device();
      $this->set_data( $db_device->launch() );
    }
    else
    {
      parent::execute();
    }
  }
}
