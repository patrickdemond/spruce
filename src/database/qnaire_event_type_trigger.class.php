<?php
/**
 * qnaire_event_type_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_event_type_trigger: record
 */
class qnaire_event_type_trigger extends qnaire_trigger
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
        'Creating new "%s" event to %s due to question "%s" having the value "%s" (questionnaire "%s")',
        $this->get_event_type()->name,
        $db_participant->uid,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    $db_event = lib::create( 'database\event' );
    $db_event->participant_id = $db_participant->id;
    $db_event->event_type_id = $this->event_type_id;
    $db_event->site_id = $db_effective_site->id;
    $db_event->user_id = $db_effective_user->id;
    $db_event->datetime = util::get_datetime_object( $db_response->last_datetime );
    $db_event->save();
  }
}
