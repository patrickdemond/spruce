<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function setup()
  {
    parent::setup();

    // update the last datetime anytime the response is changed
    $db_response = $this->get_leaf_record();
    $db_response->last_datetime = util::get_datetime_object();
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      if( 'proceed' == $action )
      {
        $db_response = $this->get_leaf_record();
        $db_response->move_to_next_page();
      }
      else if( 'backup' == $action )
      {
        $db_response = $this->get_leaf_record();
        $db_response->move_to_previous_page();
      }
    }
  }
}
