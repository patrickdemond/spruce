<?php
/**
 * study.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class study extends \cenozo\database\study
{
  /**
   * Synchronizes all records with a parent instance
   */
  public static function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    // update the study list
    $url_postfix =
      '?select={"column":["name","description"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'study', $url_postfix ) as $study )
    {
      // see if the study exists and create it if it doesn't
      $db_study = static::get_unique_record( 'name', $study->name );
      if( is_null( $db_study ) )
      {
        $db_study = new static();
        log::info( sprintf( 'Importing new "%s" study from parent instance.', $study->name ) );
      }

      $db_study->name = $study->name;
      $db_study->description = $study->description;
      $db_study->save();
    }
  }
}
