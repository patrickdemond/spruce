<?php
/**
 * qnaire_collection_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_collection_trigger: record
 */
class qnaire_collection_trigger extends qnaire_trigger
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

    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();
    $db_collection = $this->get_collection();

    if( $db_qnaire->debug )
    {
      log::info( sprintf(
        '%s %s %s "%s" collection due to question "%s" having the value "%s" (questionnaire "%s")',
        $this->add_to ? 'Adding' : 'Removing',
        $db_participant->uid,
        $this->add_to ? 'to' : 'from',
        $this->get_collection()->name,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    if( $this->add_to ) $db_collection->add_participant( $db_participant->id );
    else $db_collection->remove_participant( $db_participant->id );
  }
}
