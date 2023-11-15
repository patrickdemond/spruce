<?php
/**
 * collection.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class collection extends \cenozo\database\collection
{
  /**
   * Synchronizes all records with a parent instance
   */
  public static function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $user_class_name = lib::get_class_name( 'database\user' );

    // update the collection list
    $url_postfix =
      '?select={"column":["name","description"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'collection', $url_postfix ) as $collection )
    {
      // see if the collection exists and create it if it doesn't
      $db_collection = static::get_unique_record( 'name', $collection->name );
      if( is_null( $db_collection ) )
      {
        $db_collection = new static();
        log::info( sprintf( 'Importing new "%s" collection from parent instance.', $collection->name ) );
      }

      $db_collection->name = $collection->name;
      $db_collection->description = $collection->description;
      $db_collection->save();
    }
  }
}
