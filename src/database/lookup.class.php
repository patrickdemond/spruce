<?php
/**
 * lookup.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * lookup: record
 */
class lookup extends \cenozo\database\record
{
  /**
   * Applies data from a CSV file which defines lookup items and their indicators
   * @param array $data An array of lookup data with columns:
   *                    identifier (mandatory)
   *                    name (mandatory)
   *                    description (may be blank)
   *                    indicators delimited by a semicolon (may be blank)
   * @param boolean $apply Whether to apply or evaluate the patch
   * @return stdObject
   */
  public function import_from_array( $data, $apply = false )
  {
    ini_set( 'memory_limit', '-1' );
    set_time_limit( 900 ); // 15 minutes max

    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    $result_data = [
      'lookup_item' => ['exists' => 0, 'created' => 0],
      'indicator_list' => []
    ];

    // process all rows to create new lookup items and build list of add/remove operations for indicators
    foreach( $data as $index => $row )
    {
      // skip the header row
      if( 0 == $index && 'identifier' == $row[0] ) continue;

      $identifier = $row[0];
      $name = util::utf8_encode( $row[1] );
      $description = util::utf8_encode( $row[2] );
      $new_indicator_list = [];
      if( !is_null( $row[3] ) && 'NULL' != $row[3] && 0 < strlen( $row[3] ) )
      {
        $new_indicator_list = explode( ';', $row[3] );
        foreach( $new_indicator_list as $i => $indicator )
          $new_indicator_list[$i] = util::utf8_encode( trim( $indicator ) );
      }

      $db_lookup_item = $lookup_item_class_name::get_unique_record(
        array( 'lookup_id', 'identifier' ),
        array( $this->id, $identifier )
      );

      if( is_null( $db_lookup_item ) )
      {
        $result_data['lookup_item']['created']++;

        if( $apply )
        {
          $db_lookup_item = lib::create( 'database\lookup_item' );
          $db_lookup_item->lookup_id = $this->id;
          $db_lookup_item->identifier = $identifier;
          $db_lookup_item->name = $name;
          $db_lookup_item->description = $description;
          $db_lookup_item->save();
        }
      }
      else
      {
        $result_data['lookup_item']['exists']++;
      }

      // get a list of the lookup item's current indicators
      $indicator_sel = lib::create( 'database\select' );
      $indicator_sel->add_table_column( 'indicator', 'name' );
      $old_indicator_list = [];
      if( !is_null( $db_lookup_item ) )
        foreach( $db_lookup_item->get_indicator_list() as $row ) $old_indicator_list[] = $row['name'];

      // indicators that are being added
      $created_array = array_diff( $new_indicator_list, $old_indicator_list );
      foreach( $created_array as $indicator )
      {
        if( !array_key_exists( $indicator, $result_data['indicator_list'] ) )
          $result_data['indicator_list'][$indicator] = ['add' => [], 'remove' => []];
        $result_data['indicator_list'][$indicator]['add'][] =
          is_null( $db_lookup_item ) ? $identifier : $db_lookup_item->id;
      }

      // indicators that are being removed
      $removed_array = array_diff( $old_indicator_list, $new_indicator_list );
      foreach( $removed_array as $indicator )
      {
        if( !array_key_exists( $indicator, $result_data['indicator_list'] ) )
          $result_data['indicator_list'][$indicator] = ['add' => [], 'remove' => []];
        $result_data['indicator_list'][$indicator]['remove'][] =
          is_null( $db_lookup_item ) ? $identifier : $db_lookup_item->id;
      }
    }

    // now update the indicator lookup-item lists
    foreach( $result_data['indicator_list'] as $indicator => $op )
    {
      // create the indicator if it doesn't exist already
      $db_indicator = $indicator_class_name::get_unique_record(
        array( 'lookup_id', 'name' ),
        array( $this->id, $indicator )
      );

      // rebuild the result data array for the UI
      $result_data['indicator_list'][$indicator] = [
        'new' => is_null( $db_indicator ),
        'created' => count( $op['add'] ),
        'removed' => count( $op['remove'] )
      ];

      if( $apply )
      {
        if( is_null( $db_indicator ) )
        {
          $db_indicator = lib::create( 'database\indicator' );
          $db_indicator->lookup_id = $this->id;
          $db_indicator->name = $indicator;
          $db_indicator->save();
        }

        if( 0 < count( $op['remove'] ) ) $db_indicator->remove_lookup_item( $op['remove'] );
        if( 0 < count( $op['add'] ) ) $db_indicator->add_lookup_item( $op['add'] );
      }
    }

    return (object)$result_data;
  }

  /**
   * Creates a lookup from an object
   * @param object $lookup
   * @return database\lookup
   * @static
   */
  public static function create_from_object( $lookup )
  {
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    $db_lookup = new static();
    $db_lookup->name = $lookup->name;
    $db_lookup->version = $lookup->version;
    $db_lookup->description = $lookup->description;
    $db_lookup->save();

    foreach( $lookup->indicator_list as $indicator )
    {
      $db_indicator = lib::create( 'database\indicator' );
      $db_indicator->lookup_id = $db_lookup->id;
      $db_indicator->name = $indicator->name;
      $db_indicator->save();
    }

    foreach( $lookup->lookup_item_list as $lookup_item )
    {
      $db_lookup_item = lib::create( 'database\lookup_item' );
      $db_lookup_item->lookup_id = $db_lookup->id;
      $db_lookup_item->identifier = $lookup_item->identifier;
      $db_lookup_item->name = $lookup_item->name;
      $db_lookup_item->description = $lookup_item->description;
      $db_lookup_item->save();

      // associate with indicators
      $indicator_id_list = [];
      foreach( $lookup_item->indicator_list as $indicator )
      {
        $db_indicator = $indicator_class_name::get_unique_record(
          ['lookup_id', 'name'],
          [$db_lookup->id, $indicator]
        );
        $indicator_id_list[] = $db_indicator->id;
      }
      if( 0 < count( $indicator_id_list ) ) $db_lookup_item->add_indicator( $indicator_id_list );
    }

    return $db_lookup;
  }

  /**
   * Synchronizes all records with a parent instance
   * @param database\qnaire $db_qnaire Which questionnaire are we updating for
   */
  public static function sync_with_parent( $db_qnaire = NULL )
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    log::info( 'Synchronizing lookups' );

    // get a list of all lookups on the remote server
    $url_postfix =
      '?select={'.
        '"column":['.
          '{"table":"lookup","column":"name"},'.
          '{"table":"lookup","column":"version"},'.
          '{"table":"lookup","column":"description"}'.
        '],'.
        '"distinct":true'.
      '}'.
      '&modifier={"limit":1000000}';

    foreach( util::get_data_from_parent( 'lookup', $url_postfix, $db_qnaire ) as $lookup )
    {
      $db_lookup = static::get_unique_record( 'name', $lookup->name );

      if( is_null( $db_lookup ) )
      {
        // create the lookup if it doesn't already exist
        $db_lookup = new static();
        log::info( sprintf( 'Importing new "%s" lookup from parent instance.', $lookup->name ) );
      }
      else if( $db_lookup->version == $lookup->version )
      {
        // don't proceed if the version hasn't changed
        continue;
      }
      else
      {
        log::info( sprintf(
          'Updating "%s" lookup from parent instance (version %s to %s).',
          $lookup->name,
          $db_lookup->version,
          $lookup->version
        ) );
      }

      $db_lookup->name = $lookup->name;
      $db_lookup->version = $lookup->version;
      $db_lookup->description = $lookup->description;
      $db_lookup->save();

      // re-write all indicators
      $url_postfix = sprintf(
        '/name=%s/indicator'.
        '?select={"column":[{"table":"indicator","column":"name"}]}'.
        '&modifier={"limit":1000000}',
        util::full_urlencode( $db_lookup->name )
      );
      $indicator_list = util::get_data_from_parent( 'lookup', $url_postfix, $db_qnaire );

      $indicator_mod = lib::create( 'database\modifier' );
      $indicator_mod->where( 'lookup_id', '=', $db_lookup->id );
      static::db()->execute( sprintf(
        'DELETE FROM indicator %s',
        $indicator_mod->get_sql()
      ) );

      foreach( $indicator_list as $indicator )
      {
        $db_indicator = lib::create( 'database\indicator' );
        $db_indicator->lookup_id = $db_lookup->id;
        $db_indicator->name = $indicator->name;
        $db_indicator->save();
      }

      // re-write all lookup items
      $url_postfix = sprintf(
        '/name=%s/lookup_item'.
        '?select={'.
          '"column":['.
            '{"table":"lookup_item","column":"identifier"},'.
            '{"table":"lookup_item","column":"name"},'.
            '{"table":"lookup_item","column":"description"},'.
            '"indicator_list"'.
          ']'.
        '}'.
        '&modifier={"limit":1000000}',
        util::full_urlencode( $db_lookup->name )
      );
      $lookup_item_list = util::get_data_from_parent( 'lookup', $url_postfix, $db_qnaire );

      // convert the items into a CSV list so we can import them using the above ::import_from_array() method
      $data = [];
      foreach( $lookup_item_list as $lookup_item )
      {
        $data[] = [
          $lookup_item->identifier,
          $lookup_item->name,
          $lookup_item->description,
          $lookup_item->indicator_list
        ];
      }

      $lookup_item_mod = lib::create( 'database\modifier' );
      $lookup_item_mod->where( 'lookup_id', '=', $db_lookup->id );
      static::db()->execute( sprintf(
        'DELETE FROM lookup_item %s',
        $lookup_item_mod->get_sql()
      ) );

      $db_lookup->import_from_array( $data, true );

      log::info( sprintf(
        'Done, lookup now has %d indicators and %d items',
        count( $indicator_list ),
        count( $data )
      ) );
    }
  }
}
