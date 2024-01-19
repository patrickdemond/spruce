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
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      $data = $this->get_file_as_object();
      $action = $this->get_argument( 'action', false );
      if( false !== $action )
      {
        if( !in_array( $action, ['launch', 'pause', 'skip', 'reset'] ) )
        {
          $this->get_status()->set_code( 400 );
        }
        else
        {
          $db_response_stage = $this->get_leaf_record();
          $valid_status_list = [];
          if( 'launch' == $action ) $valid_status_list = ['ready', 'paused', 'completed'];
          else if( 'pause' == $action ) $valid_status_list = ['active'];
          else if( 'reset' == $action ) $valid_status_list = ['paused', 'skipped', 'completed'];
          else if( 'skip' == $action ) $valid_status_list = ['paused', 'ready'];

          if( !in_array( $db_response_stage->status, $valid_status_list ) )
          {
            $db_user = $db_response_stage->get_user();
            $db_current_user = lib::create( 'business\session' )->get_user();
            $who = $db_current_user->id == $db_user->id
                 ? 'your account'
                 : sprintf( '%s %s', $db_user->first_name, $db_user->last_name );

            $this->set_data( sprintf(
              'Cannot %s the stage since the response has been changed by %s in a different browser.',
              $action,
              $who
            ) );
            $this->get_status()->set_code( 409 );
          }
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
