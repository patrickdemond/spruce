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
      $db_response = $this->get_leaf_record();
      if( 'proceed' == $action ) $db_response->move_to_next_page();
      else if( 'backup' == $action ) $db_response->move_to_previous_page();
      else if( 'set_language' == $action )
      {
        // set the response's new language
        $language_class_name = lib::get_class_name( 'database\language' );
        $db_language = $language_class_name::get_unique_record( 'code', $this->get_argument( 'code' ) );
        $db_response->set_language( $db_language );
      }
    }
  }
}
