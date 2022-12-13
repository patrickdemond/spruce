<?php
/**
 * util.class.php
 */

namespace pine;
use cenozo\lib, cenozo\log;

/**
 * util: utility class of static methods
 *
 * Extends cenozo's util class with additional functionality.
 */
class util extends \cenozo\util
{
  /**
   * Transforms a JSON-encoded unit-list string into an associative array.
   * 
   * Unit lists are used by the "number with unit" question type and question option extra types.
   * Data is stored in a JSON-encoded string using multiple different formats, for example:
   *   [ "mg", "IU" ]
   *   [ { "MG": "mg" }, { "IU": { "en": "IU", "fr": "U. I." } } ]
   *   { "MG": "mg", "IU": { "en": "IU", "fr": "U. I." } }
   * @param string $unit_list A JSON-encoded unit list
   * @param array(database\language) An array of all possible languages
   * @param integer The database ID if the base language
   * @return associative array
   * @static
   * @access public
   */
  public static function get_unit_list_enum( $unit_list, $language_list, $base_lang )
  {
    if( is_null( $unit_list ) ) return NULL;

    $get_name = function( $input, $lang, $base_lang ) {
      $name_list = $input;

      // if a string is provided then convert it to an object
      if( is_string( $name_list ) )
      {
        $name_list = [];
        $name_list[$base_lang] = $input;
      }
      else if( is_object( $name_list ) )
      {
        $name_list = (array) $name_list;
      }

      // get the name for the appropriate language, or the base language as a fall-back
      return (
        array_key_exists( $lang, $name_list ) ? $name_list[$lang] : (
          array_key_exists( $base_lang, $name_list ) ? $name_list[$base_lang] : NULL
        )
      );
    };

    $data = util::json_decode( $unit_list );

    $unit_list_enum = [];
    foreach( $language_list as $db_language )
    {
      // make sure every language has an array
      $unit_list_enum[$db_language->code] = [];

      if( is_array( $data ) )
      {
        foreach( $data as $item )
        {
          if( is_string( $item ) )
          {
            // if only a string is provided then use it as the key and value for all languages
            $unit_list_enum[$db_language->code][$item] = $item;
          }
          else if( is_object( $item ) )
          {
            foreach( $item as $key => $value )
            {
              $name = $get_name( $value, $db_language->code, $base_lang );
              if( !is_null( $name ) ) $unit_list_enum[$db_language->code][$key] = $name;
            }
          }
        }
      }
      else if( is_object( $data ) )
      {
        foreach( $data as $key => $value )
        {
          $name = $get_name( $value, $db_language->code, $base_lang );
          if( !is_null( $name ) ) $unit_list_enum[$db_language->code][$key] = $name;
        }
      }
    }

    return $unit_list_enum;
  }
}
