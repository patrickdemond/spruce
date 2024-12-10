<?php
/**
 * identifier.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class identifier extends \cenozo\database\identifier
{
  /**
   * Synchronizes all records with a parent instance
   * @param database\qnaire $db_qnaire Which questionnaire are we updating for
   */
  public static function sync_with_parent( $db_qnaire = NULL )
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    log::info( 'Synchronizing identifiers' );

    // update the identifier list
    $url_postfix =
      '?select={"column":["name","locked","regex","description"]}'.
      '&modifier={"limit":1000000}';
    foreach( util::get_data_from_parent( 'identifier', $url_postfix, $db_qnaire ) as $identifier )
    {
      // see if the identifier exists and create it if it doesn't
      $db_identifier = static::get_unique_record( 'name', $identifier->name );
      if( is_null( $db_identifier ) )
      {
        $db_identifier = new static();
        log::info( sprintf( 'Importing new "%s" identifier from parent instance.', $identifier->name ) );
      }

      $db_identifier->name = $identifier->name;
      $db_identifier->locked = $identifier->locked;
      $db_identifier->regex = $identifier->regex;
      $db_identifier->description = $identifier->description;
      $db_identifier->save();
    }
  }
}
