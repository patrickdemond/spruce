<?php
/**
 * equipment_type.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class equipment_type extends \cenozo\database\equipment_type
{
  /**
   * Synchronizes all records with a parent instance
   * @param database\qnaire Restrict equipment types used by a particular qnaire
   */
  public static function sync_with_parent( $db_qnaire = NULL )
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
    $db_site = lib::create( 'business\session' )->get_site();

    $qnaire_name_list = [];
    if( is_null( $db_qnaire ) )
    {
      $qnaire_sel = lib::create( 'database\select' );
      $qnaire_sel->add_column( 'name' );
      foreach( $qnaire_class_name::select( $qnaire_sel ) as $qnaire )
        $qnaire_name_list[] = util::full_urlencode( $qnaire['name'] );
    }
    else
    {
      $qnaire_name_list[] = util::full_urlencode( $db_qnaire->name );
    }

    // update the equipment type list (restricting to a equipment type used by the given, or all qnaires)
    $url_postfix = sprintf(
      '?select={'.
        '"column":['.
          '{"table":"equipment_type","column":"name"},'.
          '{"table":"equipment_type","column":"description"}'.
        '],'.
        '"distinct":true'.
      '}'.
      '&modifier={'.
        '"join":[{'.
          '"table":"question",'.
          '"onleft":"equipment_type.id",'.
          '"onright":"question.equipment_type_id"'.
        '},{'.
          '"table":"page",'.
          '"onleft":"question.page_id",'.
          '"onright":"page.id"'.
        '},{'.
          '"table":"module",'.
          '"onleft":"page.module_id",'.
          '"onright":"module.id"'.
        '},{'.
          '"table":"qnaire",'.
          '"onleft":"module.qnaire_id",'.
          '"onright":"qnaire.id"'.
        '}],'.
        '"where":[{'.
          '"column":"qnaire.name",'.
          '"operator":"IN",'.
          '"value":["%s"]'.
        '}],'.
        '"limit":1000000'.
      '}',
      implode( '","', $qnaire_name_list )
    );

    foreach( util::get_data_from_parent( 'equipment_type', $url_postfix ) as $equipment_type )
    {
      $db_equipment_type = static::get_unique_record( 'name', $equipment_type->name );

      if( is_null( $db_equipment_type ) )
      {
        // create the equipment type if it doesn't already exist
        $db_equipment_type = new static();
        log::info( sprintf( 'Importing new "%s" equipment type from parent instance.', $equipment_type->name ) );
      }
      else
      {
        log::info( sprintf( 'Updating "%s" equipment type from parent instance.', $equipment_type->name ) );
      }

      $db_equipment_type->name = $equipment_type->name;
      $db_equipment_type->description = $equipment_type->description;
      $db_equipment_type->save();

      // update equipment records
      $url_postfix = sprintf(
        '/name=%s/equipment'.
        '?select={'.
          '"column":['.
            '{"table":"equipment","column":"active"},'.
            '{"table":"equipment","column":"serial_number"},'.
            '{"table":"equipment","column":"note"}'.
          ']'.
        '}'.
        '&modifier={"limit":1000000}',
        util::full_urlencode( $db_equipment_type->name )
      );
      $equipment_list = util::get_data_from_parent( 'equipment_type', $url_postfix );

      // convert the items into a CSV list so we can import them using the above ::import_from_array() method
      $data = [['active', 'serial_number', 'site', 'note']];
      foreach( $equipment_list as $equipment )
      {
        $data[] = [
          $equipment->active,
          $equipment->serial_number,
          $db_site->name,
          $equipment->note
        ];
      }

      $result = $db_equipment_type->import_from_array( $data, true );

      log::info( sprintf(
        'Done, imported %d new and %d updated equipment records',
        $result->equipment['new'],
        $result->equipment['update']
      ) );

      if( 0 < count( $result->invalid ) )
      {
        log::info( sprintf(
          "The following errors were detected during the import:\n%s",
          implode( "\n", $result->invalid )
        ) );
      }
    }
  }
}
