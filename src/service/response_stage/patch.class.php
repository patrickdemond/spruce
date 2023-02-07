<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response_stage;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      $db_response_stage = $this->get_leaf_record();

      if( in_array( $action, ['launch', 'pause', 'skip', 'reset'] ) )
      {
        // run response_stage launch(), skip() or reset()
        try { $db_response_stage->$action(); }
        catch( \cenozo\exception\runtime $e )
        {
          throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__, $e );
        }

        // update the last datetime anytime the response is changed
        $db_response = $db_response_stage->get_response();
        $db_response->last_datetime = util::get_datetime_object();
        $db_response->save();
      }
      else throw lib::create( 'exception\argument', 'action', $action, __METHOD__ );
    }
  }
}
