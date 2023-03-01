<?php
/**
 * deviation_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * deviation_type: record
 */
class deviation_type extends \cenozo\database\record
{
  /**
   * Creates a deviation_type from an object
   * @param object $deviation_type
   * @param database\qnaire $db_qnaire The qnaire to associate the deviation_type to
   * @return database\deviation_type
   * @static
   */
  public static function create_from_object( $deviation_type, $db_qnaire )
  {
    $db_deviation_type = new static();
    $db_deviation_type->qnaire_id = $db_qnaire->id;
    $db_deviation_type->type = $deviation_type->type;
    $db_deviation_type->name = $deviation_type->name;
    $db_deviation_type->save();

    return $db_deviation_type;
  }
}
