<?php
/**
 * qnaire_consent_type_confirm.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_consent_type_confirm: record
 */
class qnaire_consent_type_confirm extends \cenozo\database\record
{
  /**
   * Creates a qnaire_consent_type_confirm from an object
   * @param object $qnaire_consent_type_confirm
   * @param database\qnaire $db_qnaire The qnaire to associate the qnaire_consent_type_confirm to
   * @return database\qnaire_consent_type_confirm
   * @static
   */
  public static function create_from_object( $qnaire_consent_type_confirm, $db_qnaire )
  {
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $db_consent_type = $consent_type_class_name::get_unique_record(
      'name',
      $qnaire_consent_type_confirm->consent_type_name
    );

    $db_qnaire_consent_type_confirm = new static();
    $db_qnaire_consent_type_confirm->qnaire_id = $db_qnaire->id;
    $db_qnaire_consent_type_confirm->consent_type_id = $qnaire_consent_type_confirm->consent_type_id;
    $db_qnaire_consent_type_confirm->save();

    return $db_qnaire_consent_type_confirm;
  }
}
