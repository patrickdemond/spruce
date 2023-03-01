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
class qnaire_participant_trigger extends \cenozo\database\record
{
  /**
   * Creates a qnaire_participant_trigger from an object
   * @param object $qnaire_participant_trigger
   * @param database\question $db_question The question to associate the qnaire_participant_trigger to
   * @return database\qnaire_participant_trigger
   * @static
   */
  public static function create_from_object( $qnaire_participant_trigger, $db_question )
  {
    $db_qnaire_participant_trigger = new static();
    $db_qnaire_participant_trigger->qnaire_id = $db_question->get_qnaire()->id;
    $db_qnaire_participant_trigger->question_id = $db_question->id;
    $db_qnaire_participant_trigger->answer_value = $qnaire_participant_trigger->answer_value;
    $db_qnaire_participant_trigger->column_name = $qnaire_participant_trigger->column_name;
    $db_qnaire_participant_trigger->value = $qnaire_participant_trigger->value;
    $db_qnaire_participant_trigger->save();

    return $db_qnaire_participant_trigger;
  }
}
