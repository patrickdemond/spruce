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
      if( 'import' != $action )
      {
        // make sure the qnaire has beartooth credentials
        if( is_null( $db_qnaire->beartooth_url ) ||
            is_null( $db_qnaire->beartooth_username ) ||
            is_null( $db_qnaire->beartooth_password ) )
        {
          $this->status->set_code( 306 );
          $this->set_data(
            'You cannot get respondents without first defining the URL, username and password of an instance of Beartooth '.
            'to get appointments from.'
          );
        }
      }
    }
    else if( $this->may_continue() )
    {
      // make sure the participant is enrolled and not in a final hold
      $post_array = $this->get_file_as_array();
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
    $action = $this->get_argument( 'action', NULL );
    if( 'get_respondents' == $action )
    {
      $db_qnaire = $this->get_parent_record();
      $db_qnaire->sync_with_parent();
      $db_qnaire->get_respondents_from_beartooth();
    }
    else if( 'import' == $action )
    {
      $db_qnaire = $this->get_parent_record();
      $db_qnaire->import_response_data( $this->get_file_as_object() );
    }
    else if( 'export' == $action )
    {
      $db_qnaire = $this->get_parent_record();
      $db_qnaire->sync_with_parent();
      $this->set_data( $db_qnaire->export_respondent_data() ); // set the list of exported UIDs as the returned data
    }
    else
    {
      parent::execute();
    }
  }
}
