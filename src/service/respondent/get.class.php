<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent method
   */
  public function setup()
  {
    parent::setup();

    if( $this->get_argument( 'assert_response', false ) )
    {
      $create_new_response = false;

      $db_respondent = $this->get_leaf_record();
      $db_qnaire = $db_respondent->get_qnaire();
      $db_response = $db_respondent->get_current_response();

      // make sure there is a response
      if( is_null( $db_response ) || is_null( $db_response->start_datetime ) )
      {
        // always create the first response
        $create_new_response = true;
      }
      else if( !is_null( $db_qnaire->repeated ) )
      {
        // create a new response if the repeat span has passed since the last response
        $diff = $db_response->start_datetime->diff( util::get_datetime_object() );
        if( 'hour' == $db_qnaire->repeated )
        {
          // count hours
          $hours = 24 * $diff->days + $diff->h;
          $create_new_response = $hours >= $db_qnaire->repeat_offset;
        }
        else if( 'day' == $db_qnaire->repeated )
        {
          $create_new_response = $diff->days >= $db_qnaire->repeat_offset;
        }
        else if( 'week' == $db_qnaire->repeated )
        {
          $create_new_response = $diff->days >= ( 7 * $db_qnaire->repeat_offset );
        }
        else if( 'month' == $db_qnaire->repeated )
        {
          // count months
          $months = 12 * $diff->y + $diff->m;
          $create_new_response = $months >= $db_qnaire->repeat_offset;
        }
      }

      if( $create_new_response )
      {
        $db_response = lib::create( 'database\response' );
        $db_response->respondent_id = $db_respondent->id;
        $db_response->save();
      }
    }
  }
}
