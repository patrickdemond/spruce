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
    ini_set( 'memory_limit', '1G' );
    set_time_limit( 900 ); // 15 minutes max

    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    $result_data = array(
      'lookup_item' => array( 'exists' => 0, 'created' => 0 ),
      'indicator_list' => array()
    );

    foreach( $data as $index => $row )
    {
      // skip the header row
      if( 0 == $index && 'identifier' == $row[0] ) continue;

      $identifier = $row[0];
      $name = $row[1];
      $description = $row[2];
      $indicators = $row[3];

      $lookup_item_is_new = false;

      $db_lookup_item = $lookup_item_class_name::get_unique_record(
        array( 'lookup_id', 'identifier' ),
        array( $this->id, $identifier )
      );

      if( is_null( $db_lookup_item ) )
      {
        $lookup_item_is_new = true;
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

      // process all indicators
      if( !is_null( $indicators ) && 'NULL' != $indicators && 0 < strlen( $indicators ) )
      {
        $indicator_id_list = array();
        foreach( explode( ';', $indicators ) as $indicator )
        {
          if( !array_key_exists( $indicator, $result_data['indicator_list'] ) )
          {
            $result_data['indicator_list'][$indicator] = array(
              'new' => false,
              'exists' => 0,
              'created' => 0
            );
          }
          $result_data['indicator_list'][$indicator][$lookup_item_is_new ? 'created' : 'exists']++;

          $db_indicator = $indicator_class_name::get_unique_record(
            array( 'lookup_id', 'name' ),
            array( $this->id, $indicator )
          );

          if( is_null( $db_indicator ) )
          {
            $result_data['indicator_list'][$indicator]['new'] = true;

            if( $apply )
            {
              $db_indicator = lib::create( 'database\indicator' );
              $db_indicator->lookup_id = $this->id;
              $db_indicator->name = $indicator;
              $db_indicator->save();
            }
          }

          if( $apply ) $indicator_id_list[] = $db_indicator->id;
        }

        if( $apply && 0 < count( $indicator_id_list ) )
        {
          $db_lookup_item->add_indicator( $indicator_id_list );
        }
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
   * @param database\qnaire Restrict lookups used by a particular qnaire
   */
  public static function sync_with_parent( $db_qnaire = NULL )
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    $qnaire_name_list = [];
    if( is_null( $db_qnaire ) )
    {
      $qnaire_sel = lib::create( 'database\select' );
      $qnaire_sel->add_column( 'name' );
      foreach( $qnaire_class_name::select( $qnaire_sel ) as $qnaire )
        $qnaire_name_list[] = $qnaire['name'];
    }
    else
    {
      $qnaire_name_list[] = $db_qnaire->name;
    }

    // update the lookup list (restricting to a lookup used by the given, or all qnaires)
    $url_postfix = sprintf(
      '?select={'.
        '"column":['.
          '{"table":"lookup","column":"name"},'.
          '{"table":"lookup","column":"version"},'.
          '{"table":"lookup","column":"description"}'.
        '],'.
        '"distinct":true'.
      '}'.
      '&modifier={'.
        '"join":[{'.
          '"table":"question",'.
          '"onleft":"lookup.id",'.
          '"onright":"question.lookup_id"'.
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

    foreach( util::get_data_from_parent( 'lookup', $url_postfix ) as $lookup )
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
        $db_lookup->name
      );
      $indicator_list = util::get_data_from_parent( 'lookup', $url_postfix );

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
        $db_lookup->name
      );
      $lookup_item_list = util::get_data_from_parent( 'lookup', $url_postfix );

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
