<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\respondent;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function validate()
  {
    parent::validate();

    $db_qnaire = $this->get_parent_record();
    $action = $this->get_argument( 'action', NULL );
    if( !is_null( $action ) )
    {
      if( 'import' == $action )
      {
        // the "import" action was used in old versions only
        $this->status->set_code( 306 );
        $this->set_data( 'Your software is out of date.  Please update and try again.' );
      }
      else if( !in_array( $action, ['import_responses', 'import_files'] ) )
      {
        // make sure the qnaire has beartooth credentials
        $setting_manager = lib::create( 'business\setting_manager' );
        if(
          !$setting_manager->get_setting( 'general', 'detached' ) ||
          is_null( PARENT_INSTANCE_URL ) ||
          is_null( $db_qnaire->parent_beartooth_url ) ||
          is_null( $db_qnaire->parent_username ) ||
          is_null( $db_qnaire->parent_password )
        ) {
          $this->status->set_code( 306 );
          $this->set_data(
            'This instance has not been properly setup to receive respondents from an instance of Beartooth. '.
            'Please check the settings file and try again.'
          );
        }
      }
      else if( 'import_responses' == $action )
      {
        $participant_class_name = lib::get_class_name( 'database\participant' );
        $respondent_class_name = lib::get_class_name( 'database\respondent' );

        // check for conflicting tokens
        $notice_list = [];
        foreach( $this->get_file_as_object() as $respondent )
        {
          $db_participant = $participant_class_name::get_unique_record( 'uid', $respondent->uid );

          if( is_null( $db_participant ) )
          {
            $notice_list[] = sprintf( 'Invalid UID "%s" cannot be imported.', $respondent->uid );
          }
          else
          {
            $db_respondent = $respondent_class_name::get_unique_record( 'token', $respondent->token );
            if( !is_null( $db_respondent ) && $db_respondent->participant_id != $db_participant->id )
            {
              $notice_list[] = sprintf(
                'Token "%s" cannot be used for participant "%s" as it is already in use by "%s".',
                $respondent->token,
                $respondent->uid,
                $db_respondent->get_participant()->uid
              );
            }
          }
        }

        if( 0 < count( $notice_list ) )
        {
          $this->status->set_code( 306 );
          $this->set_data(
            'Unable to proceed with export due to the following error(s):'."\n".
            implode( "\n", $notice_list )
          );
        }
      }
    }
    else if( $this->may_continue() )
    {
      $post_array = $this->get_file_as_array();
      if( array_key_exists( 'participant_id', $post_array ) )
      {
        // make sure the participant is enrolled and not in a final hold
        $db_participant = lib::create( 'database\participant', $post_array['participant_id'] );
        $db_last_hold = $db_participant->get_last_hold();
        $db_last_hold_type = is_null( $db_last_hold ) ? NULL : $db_last_hold->get_hold_type();
        $final_hold = !is_null( $db_last_hold_type ) && 'final' == $db_last_hold_type->type;
        if( !is_null( $db_participant->exclusion_id ) )
        {
          $this->status->set_code( 306 );
          $this->set_data( 'Only enrolled participants may be added to a questionnaire.' );
        }
        else if( $final_hold && !$db_qnaire->allow_in_hold )
        {
          $this->status->set_code( 306 );
          $this->set_data( 'This questionniare does not allow participants who in a final hold to be interviewed.' );
        }
      }
      else
      {
        // make sure that only anonymous qnaires can have anonymous respondents
        if( !$db_qnaire->anonymous )
        {
          $this->status->set_code( 306 );
          $this->set_data(
            'Only anonymous questionnaires may have respondents not associated with any participant.  '.
            'Please provide the associated participant.'
          );
        }
      }
    }
  }

  /**
   * Extends parent method
   */
  protected function setup()
  {
    if( is_null( $this->get_argument( 'action', NULL ) ) )
    {
      parent::setup();

      if( $this->get_argument( 'no_mail', false ) )
      {
        $db_respondent = $this->get_leaf_record();
        $db_respondent->do_not_send_mail();
      }
    }
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    $db_qnaire = $this->get_parent_record();
    $action = $this->get_argument( 'action', NULL );
    if( 'get_respondents' == $action )
    {
      $study_class_name = lib::get_class_name( 'database\study' );
      $identifier_class_name = lib::get_class_name( 'database\identifier' );
      $collection_class_name = lib::get_class_name( 'database\collection' );
      $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
      $event_type_class_name = lib::get_class_name( 'database\event_type' );
      $alternate_consent_type_class_name = lib::get_class_name( 'database\alternate_consent_type' );
      $proxy_type_class_name = lib::get_class_name( 'database\proxy_type' );
      $lookup_class_name = lib::get_class_name( 'database\lookup' );
      $equipment_type_class_name = lib::get_class_name( 'database\equipment_type' );

      // Note: always sync study first (it will check that the parent Pine version matches)
      $study_class_name::sync_with_parent();
      $identifier_class_name::sync_with_parent();
      $collection_class_name::sync_with_parent();
      $consent_type_class_name::sync_with_parent();
      $event_type_class_name::sync_with_parent();
      $alternate_consent_type_class_name::sync_with_parent();
      $proxy_type_class_name::sync_with_parent();
      $lookup_class_name::sync_with_parent();
      $equipment_type_class_name::sync_with_parent();

      $db_qnaire->sync_with_parent();
      $result = $db_qnaire->get_respondents_from_beartooth();
      $result['qnaire'] = $db_qnaire->name;
      $this->set_data( [$result] );
    }
    else if( 'import_responses' == $action )
    {
      $this->set_data( $db_qnaire->import_response_data( $this->get_file_as_object() ) );
    }
    else if( 'import_files' == $action )
    {
      $this->set_data( $db_qnaire->import_device_files( $this->get_file_as_object() ) );
    }
    else if( 'export' == $action )
    {
      $db_qnaire->sync_with_parent();
      $this->set_data( $db_qnaire->export_respondent_data() );
    }
    else
    {
      parent::execute();
    }
  }
}
