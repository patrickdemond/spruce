<?php
/**
 * respondent.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * respondent: record
 */
class respondent extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    $new = is_null( $this->id );

    // setup new respondents
    if( $new )
    {
      $db_participant = lib::create( 'database\participant', $this->participant_id );
      $this->token = static::generate_token();
    }

    parent::save();

    // send invitation emails if the qnaire requires it
    if( $new )
    {
      $db_qnaire = $this->get_qnaire();
      if( $db_qnaire->email_invitation ) $this->add_mail( 'invitation' );
      if( $db_qnaire->email_reminder )
      {
        $datetime = util::get_datetime_object();
        $datetime->add( new \DateInterval( sprintf(
          'P%s%d%s',
          'hour' == $db_qnaire->email_reminder ? 'T' : '',
          $db_qnaire->email_reminder_offset,
          strtoupper( substr( $db_qnaire->email_reminder, 0, 1 ) )
        ) ) );
        $this->add_mail( 'reminder', $datetime );
      }
    }
  }

  /**
   * Override the parent method
   */
  public function delete()
  {
    $db_invitation_mail = $this->get_invitation_mail();
    $db_reminder_mail = $this->get_reminder_mail();

    parent::delete();

    // also delete any unsent mail
    if( !is_null( $db_invitation_mail ) && is_null( $db_invitation_mail->sent_datetime ) ) $db_invitation_mail->delete();
    if( !is_null( $db_reminder_mail ) && is_null( $db_reminder_mail->sent_datetime ) ) $db_reminder_mail->delete();
  }

  /**
   * TODO: document
   */
  public function get_invitation_mail()
  {
    return is_null( $this->invitation_mail_id ) ?  NULL : lib::create( 'database\mail', $this->invitation_mail_id );
  }

  /**
   * TODO: document
   */
  public function get_reminder_mail()
  {
    return is_null( $this->reminder_mail_id ) ?  NULL : lib::create( 'database\mail', $this->reminder_mail_id );
  }

  /**
   * Returns this respondent's current response record
   * 
   * @return database\response
   * @access public
   */
  public function get_current_response()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query respondent with no primary key.' );
      return NULL;
    }

    $select = lib::create( 'database\select' );
    $select->from( 'respondent_current_response' );
    $select->add_column( 'response_id' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'respondent_id', '=', $this->id );

    $response_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    return $response_id ? lib::create( 'database\response', $response_id ) : NULL;
  }

  /**
   * TODO: document
   */
  public function get_language()
  {
    // set the language to the last response, or the participant default there isn't one
    $db_current_response = $this->get_current_response();
    return is_null( $db_current_response ) ? $this->get_participant()->get_language() : $db_current_response->get_language();
  }

  /**
   * TODO: document
   */
  public function add_mail( $type, $datetime = NULL )
  {
    $db_qnaire = $this->get_qnaire();
    $db_subject_description = $db_qnaire->get_description( sprintf( '%s subject', $type ), $this->get_language() );
    $db_body_description = $db_qnaire->get_description( sprintf( '%s body', $type ), $this->get_language() );
    if( $db_subject_description->value && $db_body_description->value )
    {
      $db_participant = $this->get_participant();
      if( is_null( $datetime ) ) $datetime = util::get_datetime_object();

      if( $db_participant->email && $db_qnaire->email_from_name && $db_qnaire->email_from_address )
      {
        $function_name = sprintf( 'get_%s_mail', $type );
        $db_mail = $this->$function_name();
        if( !is_null( $db_mail ) && !is_null( $db_mail->sent_datetime ) )
        { // don't send an email if it has already been sent
          log::warning( sprintf(
            'Tried to send %s email for participant %s on qnaire "%s" which has already been sent.',
            $type,
            $db_participant->uid,
            $db_qnaire->name
          ) );
        }
        else
        {
          if( is_null( $db_mail ) ) $db_mail = lib::create( 'database\mail' );
          $db_mail->participant_id = $db_participant->id;
          $db_mail->from_name = $db_qnaire->email_from_name;
          $db_mail->from_address = $db_qnaire->email_from_address;
          $db_mail->to_name = $db_participant->get_full_name();
          $db_mail->to_address = $db_participant->email;
          $db_mail->schedule_datetime = $datetime;
          $db_mail->subject = $db_subject_description->get_compiled_value( $this );
          $db_mail->body = $db_body_description->get_compiled_value( $this );
          $db_mail->note = sprintf( 'Automatically added from a Pine questionnaire %s.', $type );
          $db_mail->save();

          // now store the mail in the record
          $column_name = sprintf( '%s_mail_id', $type );
          $this->$column_name = $db_mail->id;
          $this->save();
        }
      }
    }
  }

  /**
   * TODO: document
   */
  public function get_url()
  {
    return sprintf( '%s/respondent/run/%s', ROOT_URL, $this->token );
  }

  /**
   * Creates a unique token to be used for identifying a respondent
   * 
   * @access private
   */
  private static function generate_token()
  {
    $created = false;
    $count = 0;
    while( 100 > $count++ )
    {
      $token = sprintf(
        '%s-%s-%s-%s',
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) )
      );

      // make sure it isn't already in use
      if( null == static::get_unique_record( 'token', $token ) ) return $token;
    }

    // if we get here then something is wrong
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique respondent token.', __METHOD__ );
  }
}
