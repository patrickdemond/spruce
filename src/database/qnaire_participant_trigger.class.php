<?php
/**
 * qnaire_participant_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_participant_trigger: record
 */
class qnaire_participant_trigger extends qnaire_trigger
{
  /**
   * Executes this trigger for a given response
   * @param database\response $db_response
   */
  public function execute( $db_response )
  {
    // some triggers may be skipped
    if( !$this->check_trigger( $db_response ) ) return;

    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();

    if( $db_qnaire->debug )
    {
      log::info( sprintf(
        'Updating participant.%s to %s due to question "%s" having the value "%s" (questionnaire "%s")',
        $this->column_name,
        $this->value,
        $db_question->name,
        $this->answer_value,
        $db_qnaire->name
      ) );
    }

    // this is safe because the column_name is an enum type, so dangerous column names can't exist here
    $column_name = $this->column_name;
    // currently only boolean columns are supported
    $db_participant->$column_name = 'true' == $this->value;
    $db_participant->save();
  }
}
