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
class qnaire_proxy_type_trigger extends \cenozo\database\record
{
  /**
   * Creates a qnaire_proxy_type_trigger from an object
   * @param object $qnaire_proxy_type_trigger
   * @param database\question $db_question The question to associate the qnaire_proxy_type_trigger to
   * @return database\qnaire_proxy_type_trigger
   * @static
   */
  public static function create_from_object( $qnaire_proxy_type_trigger, $db_question )
  {
    $proxy_type_class_name = lib::get_class_name( 'database\proxy_type' );
    $db_proxy_type = $proxy_type_class_name::get_unique_record(
      'name',
      $qnaire_proxy_type_trigger->proxy_type_name
    );

    $db_qnaire_proxy_type_trigger = new static();
    $db_qnaire_proxy_type_trigger->qnaire_id = $db_question->get_qnaire()->id;
    $db_qnaire_proxy_type_trigger->proxy_type_id = is_null( $db_proxy_type ) ? NULL : $db_proxy_type->id;
    $db_qnaire_proxy_type_trigger->question_id = $db_question->id;
    $db_qnaire_proxy_type_trigger->answer_value = $qnaire_proxy_type_trigger->answer_value;
    $db_qnaire_proxy_type_trigger->save();

    return $db_qnaire_proxy_type_trigger;
  }
}
