<?php
/**
 * consent_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class consent_type extends \cenozo\database\consent_type
{
  /**
   * Synchronizes all records with a parent instance
   */
  public static function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $role_class_name = lib::get_class_name( 'database\role' );

    log::info( 'Synchronizing consent types' );

    // update the consent type list
    $url_postfix =
      '?select={"column":["name","description","role_list"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'consent_type', $url_postfix ) as $consent_type )
    {
      // see if the consent type exists and create it if it doesn't
      $db_consent_type = static::get_unique_record( 'name', $consent_type->name );
      if( is_null( $db_consent_type ) )
      {
        $db_consent_type = new static();
        log::info( sprintf( 'Importing new "%s" consent type from parent instance.', $consent_type->name ) );
      }

      $db_consent_type->name = $consent_type->name;
      $db_consent_type->description = $consent_type->description;
      $db_consent_type->save();

      // replace all role access
      $db_consent_type->remove_role( NULL );
      if( !is_null( $consent_type->role_list ) )
      {
        foreach( preg_split( '/, */', $consent_type->role_list ) as $role )
        {
          $db_role = $role_class_name::get_unique_record( 'name', $role );
          if( !is_null( $db_role ) ) $db_consent_type->add_role( $db_role->id );
        }
      }
    }
  }
}
