<?php
/**
 * qnaire_consent_type_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_consent_type_trigger: record
 */
class qnaire_consent_type_trigger extends qnaire_trigger
{
  /**
   * Executes this trigger for a given response
   * @param database\response $db_response
   */
  public function execute( $db_response )
  {
    $hold_class_name = lib::get_class_name( 'database\hold' );

    // some triggers may be skipped
    if( !$this->check_trigger( $db_response ) ) return;

    $session = lib::create( 'business\session' );
    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();
    $db_effective_site = $session->get_effective_site();
    $db_effective_user = $session->get_effective_user();

    if( $db_qnaire->debug )
    {
      log::info( sprintf(
        'Creating new %s "%s" consent to %s due to question "%s" having the value "%s" (questionnaire "%s")',
        $this->accept ? 'accept' : 'deny',
        $this->get_consent_type()->name,
        $db_participant->uid,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    $db_consent = lib::create( 'database\consent' );
    $db_consent->participant_id = $db_participant->id;
    $db_consent->consent_type_id = $this->consent_type_id;
    $db_consent->accept = $this->accept;
    $db_consent->written = false;
    $db_consent->datetime = util::get_datetime_object( $db_response->last_datetime );
    $db_consent->note = sprintf(
      'Created by Pine after questionnaire "%s" '.
      'was completed by user "%s" '.
      'from site "%s" '.
      'with question "%s" '.
      'having the value "%s"',
      $db_qnaire->name,
      $db_effective_user->name,
      $db_effective_site->name,
      $db_question->name,
      $this->answer_value
    );
    $db_consent->save();

    // if this is a participation consent then set the new hold record's user and site
    if( 'participation' == $db_consent->get_consent_type()->name )
    {
      $db_hold = $hold_class_name::get_unique_record(
        ['participant_id', 'datetime'],
        [$db_participant->id, $db_consent->datetime]
      );
      $db_hold_type = $db_hold->get_hold_type();
      if( 'final' == $db_hold_type->type && 'Withdrawn' == $db_hold_type->name )
      {
        $db_hold->site_id = $db_effective_site->id;
        $db_hold->user_id = $db_effective_user->id;
        $db_hold->save();
      }
    }
  }
}
