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
   * Gets a list of all dependent qnaire records based on this object's name
   * 
   * @param string $regex The search regular expression to use when matching qnaire records
   * @return associative array
   */
  protected function get_qnaire_dependent_records( $regex )
  {
    $dependencies = [];

    // Stages
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire', 'stage.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'stage', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'stage', $dependencies ) ) $dependencies['stage'] = [];
      $dependencies['stage'] = array_merge( $dependencies['stage'], $deps );
    }

    // Modules
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'module', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'module', $dependencies ) ) $dependencies['module'] = [];
      $dependencies['module'] = array_merge( $dependencies['module'], $deps );
    }
    $deps = $this->get_table_dependencies( 'module_description', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'module', $dependencies ) ) $dependencies['module'] = [];
      $dependencies['module'] = array_merge( $dependencies['module'], $deps );
    }

    // Pages
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'page', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'page', $dependencies ) ) $dependencies['page'] = [];
      $dependencies['page'] = array_merge( $dependencies['page'], $deps );
    }
    $deps = $this->get_table_dependencies( 'page_description', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'page', $dependencies ) ) $dependencies['page'] = [];
      $dependencies['page'] = array_merge( $dependencies['page'], $deps );
    }

    // Questions
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'page', 'question.page_id', 'page.id' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'question', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'question', $dependencies ) ) $dependencies['question'] = [];
      $dependencies['question'] = array_merge( $dependencies['question'], $deps );
    }
    $deps = $this->get_table_dependencies( 'question_description', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'question', $dependencies ) ) $dependencies['question'] = [];
      $dependencies['question'] = array_merge( $dependencies['question'], $deps );
    }
    
    // Questions options
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'question', 'question_option.question_id', 'question.id' );
    $join_mod->join( 'page', 'question.page_id', 'page.id' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'question_option', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'question_option', $dependencies ) ) $dependencies['question_option'] = [];
      $dependencies['question_option'] = array_merge( $dependencies['question_option'], $deps );
    }
    $deps = $this->get_table_dependencies( 'question_option_description', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'question_option', $dependencies ) ) $dependencies['question_option'] = [];
      $dependencies['question_option'] = array_merge( $dependencies['question_option'], $deps );
    }

    // Report data
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire_report', 'qnaire_report_data.qnaire_report_id', 'qnaire_report.id' );
    $join_mod->join( 'qnaire', 'qnaire_report.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'qnaire_report_data', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'report_data', $dependencies ) ) $dependencies['report_data'] = [];
      $dependencies['report_data'] = array_merge( $dependencies['report_data'], $deps );
    }

    // Device data
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'device', 'device_data.device_id', 'device.id' );
    $join_mod->join( 'qnaire', 'device.qnaire_id', 'qnaire.id' );
    $deps = $this->get_table_dependencies( 'device_data', $regex, $join_mod );
    if( 0 < count( $deps ) )
    {
      if( !array_key_exists( 'device_data', $dependencies ) ) $dependencies['device_data'] = [];
      $dependencies['device_data'] = array_merge( $dependencies['device_data'], $deps );
    }

    return $dependencies;
  }

  /**
   * Gets a list of records (by table) dependent on this qnaire-part's name
   * 
   * @param string $table_name The table to update
   * @param string $regex The regex to match required changes
   * @param database\modifier $join_mod Any joins needed to refer back to the qnaire table
   * @return associative array
   */
  private function get_table_dependencies( $table_name, $regex, $join_mod )
  {
    $dependencies = [];
    $qnaire_id = $this->get_qnaire()->id;

    $matches = [];
    $column_name_list = [];
    $parent_table_name = $table_name;
    if( preg_match( '/_data$/', $table_name ) ) $column_name_list[] = 'code';
    else if( preg_match( '/(.+)_description$/', $table_name, $matches ) )
    {
      $column_name_list[] = 'value';

      // also join to the description table's parent table
      $parent_table_name = $matches[1];
      $join_mod->join(
        $parent_table_name,
        sprintf( '%s.%s_id', $table_name, $parent_table_name ),
        sprintf( '%s.id', $parent_table_name ),
        '', // straight join
        NULL, // no alias
        true // prepend so that any joins to the parent table will work
      );
    }
    else $column_name_list[] = 'precondition';

    // make sure to add the default_answer column when updating the question table
    if( 'question' == $table_name ) $column_name_list[] = 'default_answer';

    foreach( $column_name_list as $column_name )
    {
      $full_column_name = sprintf( '%s.%s', $table_name, $column_name );
      $dependency_name = 'value' == $column_name ? 'description' : $column_name;
      $select = lib::create( 'database\select' );
      $select->from( $table_name );
      $select->set_distinct( true );
      $select->add_table_column( $parent_table_name, 'name' );
      $modifier = lib::create( 'database\modifier' );
      $modifier->merge( $join_mod );
      $modifier->where( $full_column_name, 'RLIKE', $regex );
      $modifier->where( 'qnaire.id', '=', $qnaire_id );
      $sql = sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() );
      foreach( static::db()->get_col( $sql ) as $name )
      {
        if( !array_key_exists( $dependency_name, $dependencies ) ) $dependencies[$dependency_name] = [];
        $dependencies[$dependency_name][] = $name;
      }
    }

    return $dependencies;
  }

  /**
   * Updates all variable references to a question to a new name
   * 
   * @param string $regex The search regular expression to use when matching qnaire records
   * @param array $replace_list A list of replacements to make
   * @param string $old_name The old name to change from
   * @param string $new_name The new name to change to
   */
  protected function replace_in_qnaire( $regex, $replace_list, $old_name, $new_name )
  {
    // sanity checking
    if( !is_string( $regex ) || 0 == strlen( $regex ) )
      throw lib::create( 'exception\argument', 'regex', $regex, __METHOD__ );
    if( !is_array( $replace_list ) || 0 == count( $replace_list ) )
      throw lib::create( 'exception\argument', 'replace_list', $replace_list, __METHOD__ );
    if( !is_string( $old_name ) || 0 == strlen( $regex ) )
      throw lib::create( 'exception\argument', 'old_name', $regex, __METHOD__ );
    if( !is_string( $new_name ) || 0 == strlen( $regex ) )
      throw lib::create( 'exception\argument', 'new_name', $regex, __METHOD__ );

    $qnaire_id = $this->get_qnaire()->id;

    // descriptions
    $replace_sql = '<COLUMN>';
    foreach( $replace_list as $replace )
    {
      $replace_sql = sprintf(
        'REPLACE( %s, "%s", "%s" )',
        $replace_sql,
        sprintf( $replace, $old_name ),
        sprintf( $replace, $new_name )
      );
    }

    // Stages
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire', 'stage.qnaire_id', 'qnaire.id' );
    $this->update_table( 'stage', $regex, $replace_sql, $join_mod );

    // Modules
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $this->update_table( 'module', $regex, $replace_sql, $join_mod );
    $this->update_table( 'module_description', $regex, $replace_sql, $join_mod );

    // Pages
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $this->update_table( 'page', $regex, $replace_sql, $join_mod );
    $this->update_table( 'page_description', $regex, $replace_sql, $join_mod );

    // Questions
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'page', 'question.page_id', 'page.id' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $this->update_table( 'question', $regex, $replace_sql, $join_mod );
    $this->update_table( 'question_description', $regex, $replace_sql, $join_mod );
    
    // Questions options
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'question', 'question_option.question_id', 'question.id' );
    $join_mod->join( 'page', 'question.page_id', 'page.id' );
    $join_mod->join( 'module', 'page.module_id', 'module.id' );
    $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $this->update_table( 'question_option', $regex, $replace_sql, $join_mod );
    $this->update_table( 'question_option_description', $regex, $replace_sql, $join_mod );

    // Report data
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'qnaire_report', 'qnaire_report_data.qnaire_report_id', 'qnaire_report.id' );
    $join_mod->join( 'qnaire', 'qnaire_report.qnaire_id', 'qnaire.id' );
    $this->update_table( 'qnaire_report_data', $regex, $replace_sql, $join_mod );

    // Device data
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'device', 'device_data.device_id', 'device.id' );
    $join_mod->join( 'qnaire', 'device.qnaire_id', 'qnaire.id' );
    $this->update_table( 'device_data', $regex, $replace_sql, $join_mod );
  }

  /**
   * Updates table values based on changing a qnaire-part's name
   * 
   * @param string $table_name The table to update
   * @param string $regex The regex to match required changes
   * @param string $replace_sql The REPLACE sql used to replace column values
   * @param database\modifier $join_mod Any joins needed to refer back to the qnaire table
   */
  private function update_table( $table_name, $regex, $replace_sql, $join_mod )
  {
    $qnaire_id = $this->get_qnaire()->id;

    $matches = [];
    $column_name_list = [];
    if( preg_match( '/_data$/', $table_name ) ) $column_name_list[] = 'code';
    else if( preg_match( '/(.+)_description$/', $table_name, $matches ) )
    {
      $column_name_list[] = 'value';

      // also join to the description table's parent table
      $parent_table_name = $matches[1];
      $join_mod->join(
        $parent_table_name,
        sprintf( '%s.%s_id', $table_name, $parent_table_name ),
        sprintf( '%s.id', $parent_table_name ),
        '', // straight join
        NULL, // no alias
        true // prepend so that any joins to the parent table will work
      );
    }
    else $column_name_list[] = 'precondition';

    // make sure to add the default_answer column when updating the question table
    if( 'question' == $table_name ) $column_name_list[] = 'default_answer';

    foreach( $column_name_list as $column_name )
    {
      $full_column_name = sprintf( '%s.%s', $table_name, $column_name );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( $full_column_name, 'RLIKE', $regex );
      $where_mod->where( 'qnaire.id', '=', $qnaire_id );
      $sql = sprintf(
        'UPDATE %s %s SET %s = %s %s',
        $table_name,
        $join_mod->get_sql(),
        $full_column_name,
        str_replace( '<COLUMN>', $full_column_name, $replace_sql ),
        $where_mod->get_sql()
      );
      static::db()->execute( $sql );
    }
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
    $equipment_type_class_name = lib::get_class_name( 'database\equipment_type' );
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
        $db_current_device = $this->get_device();
        if(
          ( !is_null( $db_current_device ) && $patch_object->device_name != $db_current_device->name ) ||
          ( is_null( $db_current_device ) && $patch_object->device_name )
        ) {
          if( $apply )
          {
            // questions may link to a device
            if( is_null( $patch_object->device_name ) )
            {
              $this->device_id = NULL;
            }
            else
            {
              $db_device = $device_class_name::get_unique_record(
                ['qnaire_id', 'name'],
                [$this->get_qnaire()->id, $patch_object->device_name]
              );
              if( !is_null( $db_device ) ) $this->device_id = $db_device->id;
            }
          }
          else
          {
            $difference_list['device_name'] = $patch_object->device_name;
          }
        }
      }
      else if( 'equipment_type_name' == $property )
      {
        $db_current_equipment_type = $this->get_equipment_type();
        if(
          (
            !is_null( $db_current_equipment_type ) &&
            $patch_object->equipment_type_name != $db_current_equipment_type->name
          ) ||
          ( is_null( $db_current_equipment_type ) && $patch_object->equipment_type_name )
        ) {
          if( $apply )
          {
            // questions may link to an equipment_type
            if( is_null( $patch_object->equipment_type_name ) )
            {
              $this->equipment_type_id = NULL;
            }
            else
            {
              $db_equipment_type = $equipment_type_class_name::get_unique_record(
                'name',
                $patch_object->equipment_type_name
              );
              if( !is_null( $db_equipment_type ) ) $this->equipment_type_id = $db_equipment_type->id;
            }
          }
          else
          {
            $difference_list['equipment_type_name'] = $patch_object->equipment_type_name;
          }
        }
      }
      else if( 'lookup_name' == $property )
      {
        $db_current_lookup = $this->get_lookup();
        if(
          ( !is_null( $db_current_lookup ) && $patch_object->lookup_name != $db_current_lookup->name ) ||
          ( is_null( $db_current_lookup ) && $patch_object->lookup_name )
        ) {
          if( $apply )
          {
            // questions may link to a lookup
            if( is_null( $patch_object->lookup_name ) )
            {
              $this->lookup_id = NULL;
            }
            else
            {
              $db_lookup = $lookup_class_name::get_unique_record( 'name', $patch_object->lookup_name );
              if( !is_null( $db_lookup ) ) $this->lookup_id = $db_lookup->id;
            }
          }
          else
          {
            $difference_list['lookup_name'] = $patch_object->lookup_name;
          }
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
