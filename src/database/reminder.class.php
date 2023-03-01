<?php
/**
 * reminder.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * reminder: record
 */
class reminder extends \cenozo\database\record
{
  /**
   * Returns one of the reminder's descriptions by language
   * @param string $type The description type to get
   * @param database\language $db_language The language to return the description in.
   * @return string
   */
  public function get_description( $type, $db_language )
  {
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );
    return $reminder_description_class_name::get_unique_record(
      array( 'reminder_id', 'language_id', 'type' ),
      array( $this->id, $db_language->id, $type )
    );
  }

  /**
   * Creates a reminder from an object
   * @param object $reminder
   * @param database\qnaire $db_qnaire The qnaire to associate the deviation_type to
   * @return database\reminder
   * @static
   */
  public static function create_from_object( $reminder, $db_qnaire )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );

    $db_reminder = new static();
    $db_reminder->qnaire_id = $db_qnaire->id;
    $db_reminder->offset = $reminder->offset;
    $db_reminder->unit = $reminder->unit;
    $db_reminder->save();

    foreach( $reminder->reminder_description_list as $reminder_description )
    {
      $db_language = $language_class_name::get_unique_record( 'code', $reminder_description->language );
      $db_reminder_description = $db_reminder->get_description( $reminder->type, $db_language );
      $db_reminder_description->value = $reminder_description->value;
      $db_reminder_description->save();
    }

    return $db_reminder;
  }
}
