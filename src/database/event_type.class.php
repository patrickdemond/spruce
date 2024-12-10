<?php
/**
 * event_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class event_type extends \cenozo\database\event_type
{
  /**
   * Synchronizes all records with a parent instance
   * @param database\qnaire $db_qnaire Which questionnaire are we updating for
   */
  public static function sync_with_parent( $db_qnaire = NULL )
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $role_class_name = lib::get_class_name( 'database\role' );

    log::info( 'Synchronizing event types' );

    // update the event type list
    $url_postfix =
      '?select={"column":["name","description","role_list"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'event_type', $url_postfix, $db_qnaire ) as $event_type )
    {
      // see if the event type exists and create it if it doesn't
      $db_event_type = static::get_unique_record( 'name', $event_type->name );
      if( is_null( $db_event_type ) )
      {
        $db_event_type = new static();
        log::info( sprintf( 'Importing new "%s" event type from parent instance.', $event_type->name ) );
      }

      $db_event_type->name = $event_type->name;
      $db_event_type->description = $event_type->description;
      $db_event_type->save();

      // replace all role access
      $db_event_type->remove_role( NULL );
      if( !is_null( $event_type->role_list ) )
      {
        foreach( preg_split( '/, */', $event_type->role_list ) as $role )
        {
          $db_role = $role_class_name::get_unique_record( 'name', $role );
          if( !is_null( $db_role ) ) $db_event_type->add_role( $db_role->id );
        }
      }
    }
  }
}
