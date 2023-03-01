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
   * Applies data from a CSV file which defines indicators and lookup_item
   * @param array $data An array of lookup data (with columns: indicator, name, description, indication)
   * @param boolean $apply Whether to apply or evaluate the patch
   * @return stdObject
   */
  public function process_data( $data, $apply = false )
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

      $lookup_item_is_new = false;

      $db_lookup_item = $lookup_item_class_name::get_unique_record(
        array( 'lookup_id', 'identifier' ),
        array( $this->id, $row[0] )
      );

      if( is_null( $db_lookup_item ) )
      {
        $lookup_item_is_new = true;
        $result_data['lookup_item']['created']++;

        if( $apply )
        {
          $db_lookup_item = lib::create( 'database\lookup_item' );
          $db_lookup_item->lookup_id = $this->id;
          $db_lookup_item->identifier = $row[0];
          $db_lookup_item->name = $row[1];
          $db_lookup_item->description = $row[2];
          $db_lookup_item->save();
        }
      }
      else
      {
        $result_data['lookup_item']['exists']++;
      }

      // process all indications
      if( 'NULL' != $row[3] )
      {
        $indicator_id_list = array();
        foreach( explode( ';', $row[3] ) as $indicator )
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
}
