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
class qnaire_consent_type_trigger extends \cenozo\database\record
{
  /**
   * Creates a qnaire_consent_type_trigger from an object
   * @param object $qnaire_consent_type_trigger
   * @param database\question $db_question The question to associate the qnaire_consent_type_trigger to
   * @return database\qnaire_consent_type_trigger
   * @static
   */
  public static function create_from_object( $qnaire_consent_type_trigger, $db_question )
  {
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $db_consent_type = $consent_type_class_name::get_unique_record(
      'name',
      $qnaire_consent_type_trigger->consent_type_name
    );

    $db_qnaire_consent_type_trigger = new static();
    $db_qnaire_consent_type_trigger->qnaire_id = $db_question->get_qnaire()->id;
    $db_qnaire_consent_type_trigger->consent_type_id = $db_consent_type->id;
    $db_qnaire_consent_type_trigger->question_id = $db_question->id;
    $db_qnaire_consent_type_trigger->answer_value = $qnaire_consent_type_trigger->answer_value;
    $db_qnaire_consent_type_trigger->accept = $qnaire_consent_type_trigger->accept;
    $db_qnaire_consent_type_trigger->save();

    return $db_qnaire_consent_type_trigger;
  }
}
