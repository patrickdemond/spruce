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
}
