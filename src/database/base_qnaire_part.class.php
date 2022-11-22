<?php
/**
 * base_qnaire_part.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * base_qnaire_part: abstract class for module, page, question and question_option
 */
abstract class base_qnaire_part extends \cenozo\database\has_rank
{
  /**
   * Overrides the parent class
   */
  public function save()
  {
    if( $this->get_qnaire()->readonly ) throw lib::create( 'exception\notice',
      'You cannot make changes to this questionnaire because it is in read-only mode.',
      __METHOD__
    );

    $column_name = sprintf( '%s_id', static::$rank_parent );

    // if we've changed the rank parent id then re-order all other objects in the old parent which came after this record
    $old_rank_parent_id = NULL;
    $old_rank = NULL;
    if( $this->has_column_changed( $column_name ) )
    {
      $old_rank_parent_id = $this->get_passive_column_value( $column_name );
      $old_rank = $this->get_passive_column_value( 'rank' );
    }

    // make room in the ranks of the new parent
    if( !is_null( $old_rank_parent_id ) && !is_null( $old_rank ) )
    {
      $sql = sprintf(
        'UPDATE %s '.
        'SET rank = rank + 1 '.
        'WHERE %s = %d '.
        'AND rank >= %d '.
        'ORDER by rank DESC',
        static::get_table_name(),
        $column_name,
        $this->$column_name,
        $this->rank
      );
      static::db()->execute( $sql );
    }

    parent::save();

    // reorder the ranks in the old parent
    if( !is_null( $old_rank_parent_id ) && !is_null( $old_rank ) )
    {
      $sql = sprintf(
        'UPDATE %s '.
        'SET rank = rank - 1 '.
        'WHERE %s = %d '.
        'AND rank > %d',
        static::get_table_name(),
        $column_name,
        $old_rank_parent_id,
        $old_rank
      );
      static::db()->execute( $sql );
    }
  }

  /**
   * Returns the qnaire-part's description by type and language
   * @param string $type Either "prompt" for visible text, or "popup" for text that pops up when hovering over the prompt
   * @param database\language $db_language
   * @return string
   */
  public function get_description( $type, $db_language )
  {
    $subject = $this->get_table_name();
    $description_class_name = lib::get_class_name( sprintf( 'database\%s_description', $subject ) );
    return $description_class_name::get_unique_record(
      array( sprintf( '%s_id', $subject ), 'language_id', 'type' ),
      array( $this->id, $db_language->id, $type )
    );
  }

  /**
   * Returns the previous qnaire-part
   * @return database\qnaire_part
   */
  public function get_previous()
  {
    $column_name = sprintf( '%s_id', static::$rank_parent );
    return static::get_unique_record(
      array( $column_name, 'rank' ),
      array( $this->$column_name, $this->rank - 1 )
    );
  }

  /**
   * Returns the next qnaire-part
   * @return database\qnaire_part
   */
  public function get_next()
  {
    $column_name = sprintf( '%s_id', static::$rank_parent );
    return static::get_unique_record(
      array( $column_name, 'rank' ),
      array( $this->$column_name, $this->rank + 1 )
    );
  }

  /**
   * Clones another qnaire-part
   * @param database\qnaire_part $db_source
   */
  public function clone_from( $db_source )
  {
    $ignore_columns = array( 'id', 'update_timestamp', 'create_timestamp', static::$rank_parent.'_id', 'qnaire_id', 'rank', 'name' );
    foreach( $this->get_column_names() as $column_name )
      if( !in_array( $column_name, $ignore_columns ) )
        $this->$column_name = $db_source->$column_name;
    $this->save();

    // now copy the descriptions
    $subject = $this->get_table_name();
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( sprintf( 'destination.%s_id', $subject ), '=', $this->id );
    $modifier->where( sprintf( 'source.%s_id', $subject ), '=', $db_source->id );
    $sql = sprintf(
      'UPDATE %s_description AS destination '.
      'JOIN %s_description AS source ON destination.language_id = source.language_id AND destination.type = source.type '.
      'SET destination.value = source.value %s',
      $subject,
      $subject,
      $modifier->get_sql()
    );
    static::db()->execute( $sql );
  }

  /**
   * Applies a patch file to the qnaire-part and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param string $name_suffix A temporary string used to prevent name collisions
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $name_suffix, $apply = false )
  {
    $subject = $this->get_table_name();
    if( 'module' == $subject ) $child_subject = 'page';
    else if( 'page' == $subject ) $child_subject = 'question';
    else if( 'question' == $subject ) $child_subject = 'question_option';
    else $child_subject = NULL;

    $language_class_name = lib::get_class_name( 'database\language' );
    $device_class_name = lib::get_class_name( 'database\device' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $description_name = sprintf( 'database\%s_description', $subject );
    $description_class_name = lib::get_class_name( $description_name );
    $child_name = sprintf( 'database\%s', $child_subject );
    $child_class_name = is_null( $child_subject ) ? NULL : lib::get_class_name( $child_name );
    $description_list_name = sprintf( '%s_description_list', $subject );
    $foreign_key_name = sprintf( '%s_id', $subject );
    $child_list_name = is_null( $child_subject ) ? NULL : sprintf( '%s_list', $child_subject );

    $difference_list = array();

    foreach( $patch_object as $property => $value )
    {
      if( $description_list_name == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->$description_list_name as $description )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $description->language );
          $db_description = $description_class_name::get_unique_record(
            array( $foreign_key_name, 'language_id', 'type' ),
            array( $this->id, $db_language->id, $description->type )
          );

          if( is_null( $db_description ) )
          {
            if( $apply )
            {
              $db_description = lib::create( $description_name );
              $db_description->$foreign_key_name = $this->id;
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
            $diff = array();
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
        $remove_list = array();
        $function_name = sprintf( 'get_%s_description_object_list', $subject );
        foreach( $this->$function_name() as $db_description )
        {
          $found = false;
          foreach( $patch_object->$description_list_name as $description )
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list[$description_list_name] = $diff_list;
      }
      else if( $child_list_name == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->$child_list_name as $child )
        {
          // match child by name or rank
          $db_child = $child_class_name::get_unique_record( array( $foreign_key_name, 'name' ), array( $this->id, $child->name ) );
          if( is_null( $db_child ) )
          {
            // we may have renamed the child, so see if it exists exactly the same under the same rank
            $db_child = $child_class_name::get_unique_record( array( $foreign_key_name, 'rank' ), array( $this->id, $child->rank ) );
            if( !is_null( $db_child ) )
            {
              // confirm that the name is the only thing that has changed
              $properties = array_keys( get_object_vars( $db_child->process_patch( $child, $name_suffix, false ) ) );
              if( 1 != count( $properties ) || 'name' != current( $properties ) ) $db_child = NULL;
            }
          }

          if( is_null( $db_child ) )
          {
            if( $apply )
            {
              $db_child = lib::create( $child_name );
              $db_child->$foreign_key_name = $this->id;
              $db_child->rank = $child->rank; // + $rank_offset;
              $db_child->name = sprintf( '%s_%s', $child->name, $name_suffix );
              if( 'question' == $child_subject )
              {
                $db_child->type = $child->type;
                // NOTE: if this isn't set now then new questions which have dkna or refuse = false won't be patched correctly
                $db_child->dkna_allowed = true;
                $db_child->refuse_allowed = true;
              }
              $db_child->save();

              $db_child->process_patch( $child, $name_suffix, $apply );
            }
            else $add_list[] = $child;
          }
          else
          {
            // find and add all differences
            $diff = $db_child->process_patch( $child, $name_suffix, $apply );
            if( !is_null( $diff ) )
            {
              // the process_patch() function above applies any changes so we don't have to do it here
              if( !$apply ) $change_list[$db_child->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        $child_mod = lib::create( 'database\modifier' );
        $child_mod->order( sprintf( '%s.rank', $child_subject ) );
        $function_name = sprintf( 'get_%s_object_list', $child_subject );
        foreach( $this->$function_name( $child_mod ) as $db_child )
        {
          $found = false;
          foreach( $patch_object->$child_list_name as $child )
          {
            // see if the child exists in the patch or if we're already changing the child
            $name = $apply ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_child->name ) : $db_child->name;
            if( $name == $child->name || in_array( $name, array_keys( $change_list ) ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_child->delete();
            else $remove_list[] = $db_child->name;
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list[$child_list_name] = $diff_list;
      }
      else if( 'device_name' == $property )
      {
        // questions may link to a device
        if( is_null( $patch_object->device_name ) )
        {
          $this->device_id = NULL;
        }
        else
        {
          $db_device = $device_class_name::get_unique_record(
            array( 'qnaire_id', 'name' ),
            array( $this->get_qnaire()->id, $patch_object->device_name )
          );
          $this->device_id = $db_device->id;
        }
      }
      else if( 'lookup_name' == $property )
      {
        // questions may link to a lookup
        if( is_null( $patch_object->lookup_name ) )
        {
          $this->lookup_id = NULL;
        }
        else
        {
          $db_lookup = $lookup_class_name::get_unique_record( 'name', $patch_object->lookup_name );
          $this->lookup_id = $db_lookup->id;
        }
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
