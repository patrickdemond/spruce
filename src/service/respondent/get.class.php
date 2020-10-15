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

      // we need a semaphore to guard against duplicate responses
      $semaphore = lib::create( 'business\semaphore' );
      $semaphore->acquire();

      $db_respondent = $this->get_leaf_record();
      $db_qnaire = $db_respondent->get_qnaire();
      $db_response = $db_respondent->get_current_response();

      // make sure there is a response
      if( is_null( $db_response ) )
      {
        // always create the first response
        $db_response = lib::create( 'database\response' );
        $db_response->respondent_id = $db_respondent->id;
        $db_response->save();
      }
      else if( !is_null( $db_qnaire->repeated ) )
      {
        $response_class_name = lib::get_class_name( 'database\response' );
        $respondent_mail_class_name = lib::get_class_name( 'database\respondent_mail' );

        // create all missing responses based on the repeat type and when the invitation went out
        $db_respondent_mail = $respondent_mail_class_name::get_unique_record(
          array( 'respondent_id', 'reminder_id', 'rank' ),
          array( $db_respondent->id, NULL, 1 )
        );

        $diff = is_null( $db_respondent_mail )
              ? $db_respondent->start_datetime->diff( util::get_datetime_object() )
              : $db_respondent_mail->get_mail()->schedule_datetime->diff( util::get_datetime_object() );

        $count = 0;
        if( 'hour' == $db_qnaire->repeated ) $count = 24*$diff->days + $diff->h;
        else if( 'day' == $db_qnaire->repeated ) $count = $diff->days;
        else if( 'week' == $db_qnaire->repeated ) $count = floor( $diff->days / 7 );
        else if( 'month' == $db_qnaire->repeated ) $count = 12 * $diff->y + $diff->m;

        // limit the total number of new responses to create
        $total_responses = floor( $count / $db_qnaire->repeat_offset ) + 1;
        if( $total_responses > $db_qnaire->max_responses ) $total_responses = $db_qnaire->max_responses;

        for( $i = $db_response->rank + 1; $i <= $total_responses; $i++ )
        {
          $db_response = lib::create( 'database\response' );
          $db_response->respondent_id = $db_respondent->id;
          $db_response->save();
        }
      }

      // finally, save whether we're showing hidden elements or not
      $db_response->show_hidden = $this->get_argument( 'show_hidden', false );
      $db_response->save();

      $semaphore->release();
    }
  }
}
