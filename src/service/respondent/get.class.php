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
      $this->db_response = $db_respondent->get_current_response( true );

      // always set the show hidden property as it may have changed since the response record was created
      $this->db_response->show_hidden = $this->get_argument( 'show_hidden', false );
      $this->db_response->save();

      if( !is_null( $db_qnaire->repeated ) )
      {
        // TODO: need to re-implement the following code
        throw lib::create( 'exception\notice',
          'Repeated questionnaires are currently dissabled. '.
          'Please contact the development team to re-enable this feature.',
          __METHOD__
        );

        /*
        // NOTE: Since this was disabled the code above (creating the response record, setting show_hidden)
        //       has changed so the commented out block may no longer be valid

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

        for( $rank = $this->db_response->rank + 1; $rank <= $total_responses; $rank++ )
        {
          $db_response = lib::create( 'database\response' );
          $db_response->respondent_id = $db_respondent->id;
          $db_response->save();
        }
        */
      }

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
      $column_values = $db_respondent->get_column_values( $this->select, $this->modifier );

      // Evaluate expressions in the descriptions
      $description_list = array();
      if( array_key_exists( 'introduction_list', $column_values ) ) $description_list['introduction'] = array();
      if( array_key_exists( 'conclusion_list', $column_values ) ) $description_list['conclusion'] = array();
      if( array_key_exists( 'closed_list', $column_values ) ) $description_list['closed'] = array();
      if( 0 < count( $description_list ) )
      {
        $rank = is_null( $this->db_response ) ? 1 : $this->db_response->rank;
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

  /**
   * Extend parent method
   */
  public function finish()
  {
    parent::finish();

    // show any attribute errors as a notice when in debug mode
    if( !is_null( $this->db_response ) )
    {
      if( $this->db_response->get_qnaire()->debug )
      {
        $error_list = lib::create( 'business\session' )->attribute_error_list;
        if( 0 < count( $error_list ) )
        {
          $notice = "The following errors occurred while creating the participant's attribute values:\n\n";
          foreach( $error_list as $name => $error ) $notice .= sprintf( "\"%s\": %s\n", $name, $error );
          $notice .=
            "\n".
            'Empty values have been created so you can proceed with the questionnaire by reloading the page.'.
            "\n\n";
          throw lib::create( 'exception\notice', $notice, __METHOD__ );
        }
      }

      // NOTE: There is currently a bug in the out-of-sync logic that will set a response to be in
      // stage-selection mode despite a stage currently being active (ie: response.page_id is null
      // and active response_stage.page_id is not null).
      // In this situation we must immediately correct the response and update the data with the
      // newly corrected values.
      if(
        array_key_exists( 'response_stage_id', $this->data ) &&
        !is_null( $this->data['response_stage_id'] ) &&
        array_key_exists( 'page_id', $this->data ) &&
        is_null( $this->data['page_id'] )
      ){
        $db_respondent = $this->get_leaf_record();
        $db_participant = $db_respondent->get_participant();
        $db_response_stage = lib::create( 'database\response_stage', $this->data['response_stage_id'] );

        // warn that we're going to fix the response record
        log::warning( sprintf(
          'Response/stage page mismatch detected and corrected for %s in %s stage.',
          is_null( $db_participant ) ?
            $db_respondent->token :
            sprintf( '%s (%s)', $db_participant->uid, $db_respondent->token ),
            $db_response_stage->get_stage()->name
        ) );

        // update the response record
        $this->db_response->page_id = $db_response_stage->page_id;
        $this->db_response->stage_selection = false;
        $this->db_response->save();

        // update the service data
        $this->data['page_id'] = $db_response_stage->page_id;
        $this->data['stage_selection'] = 0;
        $this->set_data( $this->data );
      }
    }
  }

  /**
   * A cache of the current response record (sometimes created by this service)
   * If the qnaire is repeated then only the first record is stored
   * @var database\response $db_response
   */
  protected $db_response = NULL;
}
