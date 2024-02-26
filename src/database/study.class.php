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

    $stratum_class_name = lib::get_class_name( 'database\stratum' );

    // update the study list
    $url_postfix =
      '?select={"column":["name","description","stratum_data"]}'.
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

      // create any missing stratum
      $stratum_list = [];
      if( !is_null( $study->stratum_data ) )
      {
        foreach( explode( '&&', $study->stratum_data ) as $stratum )
        {
          $parts = explode( '$$', $stratum );
          $stratum_list[$parts[0]] = $parts[1];
        }

        // delete any stratum not found in the list
        foreach( $db_study->get_stratum_object_list() as $db_stratum )
          if( !array_key_exists( $db_stratum->name, $stratum_list ) ) $db_stratum->delete();

        // add any missing stratum and update descriptions
        foreach( $stratum_list as $name => $description )
        {
          $db_stratum = $stratum_class_name::get_unique_record( ['study_id', 'name'], [$db_study->id, $name] );

          if( is_null( $db_stratum ) )
          {
            $db_stratum = lib::create( 'database\stratum' );
            $db_stratum->study_id = $db_study->id;
            $db_stratum->name = $name;
          }

          $db_stratum->description = $description;
          $db_stratum->save();
        }
      }
    }
  }
}
