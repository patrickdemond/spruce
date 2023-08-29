<?php
/**
 * proxy_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class proxy_type extends \cenozo\database\proxy_type
{
  /**
   * Synchronizes all records with a parent instance
   */
  public static function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $role_class_name = lib::get_class_name( 'database\role' );

    // update the proxy type list
    $url_postfix =
      '?select={"column":["name","description","prompt","role_list"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'proxy_type', $url_postfix ) as $proxy_type )
    {
      // see if the proxy type exists and create it if it doesn't
      $db_proxy_type = static::get_unique_record( 'name', $proxy_type->name );
      if( is_null( $db_proxy_type ) )
      {
        $db_proxy_type = new static();
        log::info( sprintf( 'Importing new "%s" proxy type from parent instance.', $proxy_type->name ) );
      }

      $db_proxy_type->name = $proxy_type->name;
      $db_proxy_type->description = $proxy_type->description;
      $db_proxy_type->prompt = $proxy_type->prompt;
      $db_proxy_type->save();

      // replace all role access
      $db_proxy_type->remove_role( NULL );
      if( !is_null( $proxy_type->role_list ) )
      {
        foreach( preg_split( '/, */', $proxy_type->role_list ) as $role )
        {
          $db_role = $role_class_name::get_unique_record( 'name', $role );
          if( !is_null( $db_role ) ) $db_proxy_type->add_role( $db_role->id );
        }
      }
    }
  }
}
