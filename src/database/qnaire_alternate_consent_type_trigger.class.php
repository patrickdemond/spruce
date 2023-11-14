<?php
/**
 * qnaire_alternate_consent_type_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_alternate_consent_type_trigger: record
 */
class qnaire_alternate_consent_type_trigger extends qnaire_trigger
{
  /**
   * Executes this trigger for a given response
   * @param database\response $db_response
   */
  public function execute( $db_response )
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );

    // some triggers may be skipped
    if( !$this->check_trigger( $db_response ) ) return;

    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();
    $db_answer = $answer_class_name::get_unique_record(
      array( 'response_id', 'question_id' ),
      array( $db_response->id, $db_question->id )
    );

    if( $db_qnaire->debug )
    {
      log::info( sprintf(
        'Creating new %s "%s" alternate consent to %s due to question "%s" '.
        'having the value "%s" (questionnaire "%s")',
        $this->accept ? 'accept' : 'deny',
        $this->get_alternate_consent_type()->name,
        $db_participant->uid,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    if( is_null( $db_answer->alternate_id ) )
    {
      log::warning( sprintf(
        'Alternate consent trigger cannot create record since answer was '.
        'provided by a participant and not an alternate. (response=%d, question=%d)',
        $db_response->id,
        $db_answer->question_id
      ) );
    }
    else
    {
      $session = lib::create( 'business\session' );
      $db_effective_site = $session->get_effective_site();
      $db_effective_user = $session->get_effective_user();

      $db_alternate_consent = lib::create( 'database\alternate_consent' );
      $db_alternate_consent->alternate_id = $db_answer->alternate_id;
      $db_alternate_consent->alternate_consent_type_id = $this->alternate_consent_type_id;
      $db_alternate_consent->accept = $this->accept;
      $db_alternate_consent->written = false;
      $db_alternate_consent->datetime = util::get_datetime_object( $db_response->last_datetime );
      $db_alternate_consent->note = sprintf(
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
      $db_alternate_consent->save();
    }
  }
}
