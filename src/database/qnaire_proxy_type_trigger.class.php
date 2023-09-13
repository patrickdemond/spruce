<?php
/**
 * qnaire_proxy_type_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_proxy_type_trigger: record
 */
class qnaire_proxy_type_trigger extends qnaire_trigger
{
  /**
   * Executes this trigger for a given response
   * @param database\response $db_response
   */
  public function execute( $db_response )
  {
    // some triggers may be skipped
    if( !$this->check_trigger( $this ) ) return;

    $session = lib::create( 'business\session' );
    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();
    $db_effective_site = $session->get_effective_site();
    $db_effective_user = $session->get_effective_user();

    if( $db_qnaire->debug )
    {
      $db_proxy_type = $this->get_proxy_type();
      log::info( sprintf(
        'Creating new "%s" proxy due to question "%s" having the value "%s" (questionnaire "%s")',
        is_null( $db_proxy_type ) ? 'empty' : $db_proxy_type->name,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    $db_proxy = lib::create( 'database\proxy' );
    $db_proxy->participant_id = $db_participant->id;
    $db_proxy->proxy_type_id = $this->proxy_type_id;
    $db_proxy->datetime = util::get_datetime_object( $db_response->last_datetime );
    $db_proxy->user_id = $db_effective_user->id;
    $db_proxy->site_id = $db_effective_site->id;
    $db_proxy->role_id = $session->get_role()->id;
    $db_proxy->application_id = $session->get_application()->id;
    $db_proxy->note = sprintf(
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

    // save the proxy file ignoring runtime errors (that denotes a duplicate which we can ignore)
    try { $db_proxy->save(); } catch( \cenozo\exception\runtime $e ) {}
  }
}
