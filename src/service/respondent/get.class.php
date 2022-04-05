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

      // We need a semaphore to guard against duplicate responses.  The semaphore is specific to the resource value
      // which in this case is the respondent's token (or id) so that other respondents are not slowed down.
      $semaphore = lib::create( 'business\semaphore', $this->get_resource_value( 0 ) );
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

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $db_respondent = $this->get_leaf_record();
    if( !is_null( $db_respondent ) )
    {
      $db_qnaire = $db_respondent->get_qnaire();
      $expression_manager = lib::create( 'business\expression_manager', $db_qnaire );
      $db_response = $db_respondent->get_current_response();
      $rank = is_null( $db_response ) ? 1 : $db_response->rank;
      $column_values = $db_respondent->get_column_values( $this->select, $this->modifier );

      // Evaluate expressions in the descriptions
      $description_list = array();
      if( array_key_exists( 'introduction_list', $column_values ) ) $description_list['introduction'] = array();
      if( array_key_exists( 'conclusion_list', $column_values ) ) $description_list['conclusion'] = array();
      if( array_key_exists( 'closed_list', $column_values ) ) $description_list['closed'] = array();
      if( 0 < count( $description_list ) )
      {
        $qnaire_description_mod = lib::create( 'database\modifier' );
        $qnaire_description_mod->where( 'type', 'IN', array_keys( $description_list ) );

        foreach( $db_qnaire->get_qnaire_description_object_list( $qnaire_description_mod ) as $db_qnaire_description )
        {
          $description_list[$db_qnaire_description->type][] = sprintf(
            '%s`%s',
            $db_qnaire_description->get_language()->code,
            $db_qnaire_description->get_compiled_value( $db_respondent, $rank )
          );
        }

        foreach( $description_list as $type => $description )
          $column_values[sprintf( '%s_list', $type )] = implode( '`', $description );
        $this->set_data( $column_values );
      }
    }
  }
}
