<?php
/**
 * reminder.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * reminder: record
 */
class reminder extends \cenozo\database\record
{
  /**
   * Returns one of the reminder's descriptions by language
   * @param string $type The description type to get
   * @param database\language $db_language The language to return the description in.
   * @return string
   */
  public function get_description( $type, $db_language )
  {
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );
    return $reminder_description_class_name::get_unique_record(
      array( 'reminder_id', 'language_id', 'type' ),
      array( $this->id, $db_language->id, $type )
    );
  }

  /**
   * Creates a reminder from an object
   * @param object $reminder
   * @param database\qnaire $db_qnaire The qnaire to associate the deviation_type to
   * @return database\reminder
   * @static
   */
  public static function create_from_object( $reminder, $db_qnaire )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );

    $db_reminder = new static();
    $db_reminder->qnaire_id = $db_qnaire->id;
    $db_reminder->delay_offset = $reminder->delay_offset;
    $db_reminder->delay_unit = $reminder->delay_unit;
    $db_reminder->save();

    foreach( $reminder->reminder_description_list as $reminder_description )
    {
      $db_language = $language_class_name::get_unique_record( 'code', $reminder_description->language );
      $db_reminder_description = $db_reminder->get_description( $reminder->type, $db_language );
      $db_reminder_description->value = $reminder_description->value;
      $db_reminder_description->save();
    }

    return $db_reminder;
  }

  /**
   * Applies a patch file to the reminder and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $apply = false )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $description_class_name = lib::get_class_name( 'database\reminder_description' );

    $difference_list = [];

    foreach( $patch_object as $property => $value )
    {
      if( 'reminder_description_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->reminder_description_list as $description )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $description->language );
          $db_description = $description_class_name::get_unique_record(
            [ 'reminder_id', 'language_id', 'type' ],
            [ $this->id, $db_language->id, $description->type ]
          );

          if( is_null( $db_description ) )
          {
            if( $apply )
            {
              $db_description = lib::create( 'database\reminder_description' );
              $db_description->reminder_id = $this->id;
              $db_description->language_id = $db_language->id;
              $db_description->type = $description->type;
              $db_description->value = $description->value;
              $db_description->save();
            }
            else $add_list[] = $description;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $description as $property => $value )
              if( 'language' != $property && $db_description->$property != $description->$property )
                $diff[$property] = $description->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_description->value = $description->value;
                $db_description->save();
              }
              else
              {
                $index = sprintf( '%s [%s]', $description->type, $db_language->code );
                $change_list[$index] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_reminder_description_object_list() as $db_description )
        {
          $found = false;
          foreach( $patch_object->reminder_description_list as $description )
          {
            if( $db_description->get_language()->code == $description->language &&
                $db_description->type == $description->type )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_description->delete();
            else
            {
              $index = sprintf( '%s [%s]', $db_description->type, $db_description->get_language()->code );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['reminder_description_list'] = $diff_list;
      }
      else
      {
        if( $patch_object->$property != $this->$property )
        {
          if( $apply )
          {
            $this->$property = 'name' == $property
                             ? sprintf( '%s_%s', $patch_object->$property, $name_suffix )
                             : $patch_object->$property;
          }
          else $difference_list[$property] = $patch_object->$property;
        }
      }
    }

    if( $apply )
    {
      $this->save();
      return null;
    }
    else return 0 == count( $difference_list ) ? NULL : (object)$difference_list;
  }
}
