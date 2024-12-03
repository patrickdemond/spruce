<?php
/**
 * alternate_consent_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class alternate_consent_type extends \cenozo\database\alternate_consent_type
{
  /**
   * Synchronizes all records with a parent instance
   */
  public static function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $role_class_name = lib::get_class_name( 'database\role' );

    log::info( 'Synchronizing alternate consent types' );

    // update the alternate consent type list
    $url_postfix =
      '?select={"column":["name","description","role_list"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'alternate_consent_type', $url_postfix ) as $alternate_consent_type )
    {
      // see if the alternate_consent type exists and create it if it doesn't
      $db_aconsent_type = static::get_unique_record( 'name', $alternate_consent_type->name );
      if( is_null( $db_aconsent_type ) )
      {
        $db_aconsent_type = new static();
        log::info( sprintf(
          'Importing new "%s" alternate consent type from parent instance.',
          $alternate_consent_type->name
        ) );
      }

      $db_aconsent_type->name = $alternate_consent_type->name;
      $db_aconsent_type->description = $alternate_consent_type->description;
      $db_aconsent_type->save();

      // replace all role access
      $db_aconsent_type->remove_role( NULL );
      if( !is_null( $alternate_consent_type->role_list ) )
      {
        foreach( preg_split( '/, */', $alternate_consent_type->role_list ) as $role )
        {
          $db_role = $role_class_name::get_unique_record( 'name', $role );
          if( !is_null( $db_role ) ) $db_aconsent_type->add_role( $db_role->id );
        }
      }
    }
  }
}
