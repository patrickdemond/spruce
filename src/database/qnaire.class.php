<?php
/**
 * qnaire.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire: record
 */
class qnaire extends \cenozo\database\record
{
  /**
   * Overrides the parent class
   */
  public function save()
  {
    if( $this->readonly )
    {
      // only allow changes to the readonly columns
      if( $this->has_column_changed( 'base_language_id' ) ||
          $this->has_column_changed( 'name' ) ||
          $this->has_column_changed( 'debug' ) ||
          $this->has_column_changed( 'description' ) ||
          $this->has_column_changed( 'note' ) )
      {
        throw lib::create( 'exception\notice',
          'You cannot make changes to this questionnaire because it is in read-only mode.',
          __METHOD__
        );
      }
    }

    if( $this->has_column_changed( 'repeated' ) )
    {
      if( !$this->repeated )
      {
        $this->repeat_offset = NULL;
        $this->max_responses = NULL;
      }
      else
      {
        if( is_null( $this->repeat_offset ) ) $this->repeat_offset = 0;
        if( is_null( $this->max_responses ) ) $this->max_responses = 0;
      }
    }

    parent::save();
  }

  /**
   * Returns the qnaire's first module
   * @return database\module
   */
  public function get_first_module()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to get first module of qnaire with no primary key.' );
      return NULL;
    }

    $module_class_name = lib::get_class_name( 'database\module' );
    return $module_class_name::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * Returns a question belonging to the qnaire by name
   * @param string $name The question's name
   * @return database\question
   */
  public function get_question( $name )
  {
    $select = lib::create( 'database\select' );
    $select->from( 'qnaire' );
    $select->add_table_column( 'question', 'id' );

    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'qnaire.id', 'module.qnaire_id' );
    $modifier->join( 'page', 'module.id', 'page.module_id' );
    $modifier->join( 'question', 'page.id', 'question.page_id' );
    $modifier->where( 'question.name', '=', $name );
    $modifier->where( 'qnaire.id', '=', $this->id );

    $question_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    return is_null( $question_id ) ? NULL : lib::create( 'database\question', $question_id );
  }

  /**
   * Returns the total number of pages in the qnaire
   * @return integer
   */
  public function get_number_of_pages()
  {
    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_constant( 'COUNT(*)', 'total', 'integer', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->where( 'module.qnaire_id', '=', $this->id );
    return static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
  }

  /**
   * Get this participant's base_language record
   * @return base_language
   * @access public
   */
  public function get_base_language()
  {
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to get base language for qnaire with no primary key.' );
      return NULL;
    }

    return is_null( $this->base_language_id ) ? NULL : lib::create( 'database\language', $this->base_language_id );
  }

  /**
   * Clones another qnaire
   * @param database\qnaire $db_source_qnaire
   */
  public function clone_from( $db_source_qnaire )
  {
    $ignore_columns = array( 'id', 'update_timestamp', 'create_timestamp', 'name' );
    foreach( $this->get_column_names() as $column_name )
      if( !in_array( $column_name, $ignore_columns ) )
        $this->$column_name = $db_source_qnaire->$column_name;

    // override readonly, otherwise we can't create it
    $this->readonly = false;

    $this->save();

    // copy all languages
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_table_column( 'language', 'id' );
    $language_id_list = array();
    foreach( $db_source_qnaire->get_language_list( $language_sel ) as $language ) $language_id_list[] = $language['id'];
    $this->add_language( $language_id_list );

    // copy all attributes
    foreach( $db_source_qnaire->get_attribute_object_list() as $db_source_attribute )
    {
      $db_attribute = lib::create( 'database\attribute' );
      $db_attribute->qnaire_id = $this->id;
      $db_attribute->name = $db_source_attribute->name;
      $db_attribute->code = $db_source_attribute->code;
      $db_attribute->note = $db_source_attribute->note;
      $db_attribute->save();
    }

    // replace all existing modules with those from the clone source qnaire
    $delete_mod = lib::create( 'database\modifier' );
    $delete_mod->where( 'qnaire_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM module %s', $delete_mod->get_sql() ) );

    foreach( $db_source_qnaire->get_module_object_list() as $db_source_module )
    {
      $db_module = lib::create( 'database\module' );
      $db_module->qnaire_id = $this->id;
      $db_module->rank = $db_source_module->rank;
      $db_module->name = $db_source_module->name;
      $db_module->clone_from( $db_source_module );
    }

    // now copy the descriptions
    $update_mod = lib::create( 'database\modifier' );
    $update_mod->where( 'destination.qnaire_id', '=', $this->id );
    $update_mod->where( 'source.qnaire_id', '=', $db_source_qnaire->id );
    $sql = sprintf(
      'UPDATE qnaire_description AS destination '.
      'JOIN qnaire_description AS source ON destination.language_id = source.language_id AND destination.type = source.type '.
      'SET destination.value = source.value %s',
      $update_mod->get_sql()
    );
    static::db()->execute( $sql );
  }

  /**
   * Returns one of the qnaire's descriptions by language
   * @param string $type The description type to get
   * @param database\language $db_language The language to return the description in.
   * @return string
   */
  public function get_description( $type, $db_language )
  {
    $qnaire_description_class_name = lib::get_class_name( 'database\qnaire_description' );
    return $qnaire_description_class_name::get_unique_record(
      array( 'qnaire_id', 'language_id', 'type' ),
      array( $this->id, $db_language->id, $type )
    );
  }

  /**
   * Determines whether the qnaire has duplicate respondents (for repeated qnaires)
   * @return boolean
   */
  public function has_duplicates()
  {
    $response_class_name = lib::get_class_name( 'database\response' );

    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $modifier->where( 'respondent.qnaire_id', '=', $this->id );
    $modifier->group( 'respondent.participant_id' );
    $modifier->having( 'COUNT(*)', '>', 1 );

    return 0 < $response_class_name::count( $modifier );
  }

  /**
   * Removes all unsent qnaire mail for the given UID list
   * @param array $uid_list A list of participant UIDs to affect
   */
  public function mass_remove_unsent_mail( $uid_list )
  {
    $select = lib::create( 'database\select' );
    $select->from( 'mail' );
    $select->add_column( 'id' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'respondent_mail', 'mail.id', 'respondent_mail.mail_id' );
    $modifier->join( 'respondent', 'respondent_mail.respondent_id', 'respondent.id' );
    $modifier->join( 'participant', 'respondent.participant_id', 'participant.id' );
    $modifier->where( 'mail.sent_datetime', '=', NULL );
    $modifier->where( 'respondent.qnaire_id', '=', $this->id );
    $modifier->where( 'participant.uid', 'IN', $uid_list );

    static::db()->execute( sprintf(
      "CREATE TEMPORARY TABLE delete_mail\n".
      "%s\n".
      "%s\n",
      $select->get_sql(),
      $modifier->get_sql()
    ) );

    static::db()->execute( 'DELETE FROM mail WHERE id IN ( SELECT id FROM delete_mail )' );
  }

  /**
   * Creates a batch of respondents as a single operation
   * @param array $uid_list A list of participant UIDs to affect
   */
  public function mass_respondent( $uid_list )
  {
    set_time_limit( 900 ); // 15 minutes max

    $participant_class_name = lib::get_class_name( 'database\participant' );

    foreach( $uid_list as $uid )
    {
      $db_participant = $participant_class_name::get_unique_record( 'uid', $uid );
      $db_respondent = lib::create( 'database\respondent' );
      $db_respondent->qnaire_id = $this->id;
      $db_respondent->participant_id = $db_participant->id;
      $db_respondent->save();
    }
  }

  /**
   * Returns an array of all responses to this qnaire
   * @param database\modifier $modifier
   * @return array( 'header', 'data' )
   */
  public function get_response_data( $modifier = NULL )
  {
    set_time_limit( 900 ); // 15 minutes max

    $response_class_name = lib::get_class_name( 'database\response' );

    // first get a list of all columns to include in the data
    $module_mod = lib::create( 'database\modifier' );
    $module_mod->order( 'module.rank' );
    foreach( $this->get_module_object_list( $module_mod ) as $db_module )
    {
      $page_mod = lib::create( 'database\modifier' );
      $page_mod->order( 'page.rank' );
      foreach( $db_module->get_page_object_list( $page_mod ) as $db_page )
      {
        $question_mod = lib::create( 'database\modifier' );
        $question_mod->where( 'type', '!=', 'comment' );
        $question_mod->order( 'question.rank' );
        foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
        {
          // figure out the full variable name in pieces
          $variable_pieces = array( $db_question->name );
          if( !is_null( $this->variable_suffix ) ) $variable_pieces[] = $this->variable_suffix;

          $option_sel = lib::create( 'database\select' );
          $option_sel->add_column( 'id' );
          $option_sel->add_column( 'name' );
          $option_sel->add_column( 'exclusive' );
          $option_sel->add_column( 'extra' );
          $option_sel->add_column( 'multiple_answers' );

          $option_mod = lib::create( 'database\modifier' );
          $option_mod->order( 'question_option.rank' );
          $option_list = $db_question->get_question_option_list( $option_sel, $option_mod );

          // only create a variable for all options if at least one is not exclusive or has extra data
          $all_exclusive = true;
          $no_extra = true;
          if( 'list' == $db_question->type )
          {
            foreach( $option_list as $option )
            {
              if( !$option['exclusive'] ) $all_exclusive = false;
              if( !is_null( $option['extra'] ) ) $no_extra = false;
            }
          }

          if( !$all_exclusive || !$no_extra )
          {
            foreach( $option_list as $option )
            {
              // add the option name in the middle of the variable name pieces
              $pieces = $variable_pieces;
              array_splice( $pieces, 1, 0, $option['name'] );
              $column_name = implode( '_', $pieces );
              $column_list[$column_name] = array(
                'question_id' => $db_question->id,
                'option_id' => $option['id'],
                'extra' => $option['extra'],
                'multiple_answers' => $option['multiple_answers'],
                'all_exclusive' => $all_exclusive,
                'no_extra' => $no_extra
              );
            }
          }
          else
          {
            $column_name = implode( '_', $variable_pieces );
            $column_list[$column_name] = array(
              'question_id' => $db_question->id,
              'type' => $db_question->type
            );

            if( 0 < count( $option_list ) ) $column_list[$column_name]['option_list'] = $option_list;
          }
        }
      }
    }

    // now loop through all responses and fill in the data array
    $data = array();
    $response_mod = lib::create( 'database\modifier' );
    $response_mod->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $response_mod->where( 'respondent.qnaire_id', '=', $this->id );
    $response_mod->order( 'respondent.end_datetime' );

    if( !is_null( $modifier ) )
    {
      $response_mod->merge( $modifier );
      $response_mod->limit( $modifier->get_limit() );
      $response_mod->offset( $modifier->get_offset() );
    }

    foreach( $response_class_name::select_objects( $response_mod ) as $db_response )
    {
      $answer_list = array();
      $answer_sel = lib::create( 'database\select' );
      $answer_sel->add_column( 'question_id' );
      $answer_sel->add_column( 'value' );
      $answer_mod = lib::create( 'database\modifier' );
      $answer_mod->order( 'question_id' );
      foreach( $db_response->get_answer_list( $answer_sel, $answer_mod ) as $answer )
        $answer_list[$answer['question_id']] = $answer['value'];

      $data_row = array(
        $db_response->get_respondent()->get_participant()->uid,
        $db_response->rank,
        $db_response->submitted ? 1 : 0,
        is_null( $db_response->start_datetime ) ? NULL : $db_response->start_datetime->format( 'c' ),
        is_null( $db_response->last_datetime ) ? NULL : $db_response->last_datetime->format( 'c' )
      );
      foreach( $column_list as $column )
      {
        $row_value = NULL;

        if( array_key_exists( $column['question_id'], $answer_list ) )
        {
          $answer = util::json_decode( $answer_list[$column['question_id']] );
          if( is_object( $answer ) && property_exists( $answer, 'dkna' ) && $answer->dkna )
          {
            $row_value = 'DKNA';
          }
          else if( is_object( $answer ) && property_exists( $answer, 'refuse' ) && $answer->refuse )
          {
            $row_value = 'REFUSED';
          }
          else
          {
            if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so every answer is its own variable
              $row_value = !$column['all_exclusive'] ? 'NO' : NULL;
              if( is_array( $answer ) ) foreach( $answer as $a )
              {
                if( ( is_object( $a ) && $column['option_id'] == $a->id ) || ( !is_object( $a ) && $column['option_id'] == $a ) )
                {
                  // use the value if the option asks for extra data
                  $row_value = is_null( $column['extra'] ) ? 'YES' : ( property_exists( $a, 'value' ) ? $a->value : NULL );
                  break;
                }
              }
            }
            else // the question can only have one answer
            {
              if( 'boolean' == $column['type'] )
              {
                $row_value = $answer ? 'YES' : 'NO';
              }
              else if( 'list' == $column['type'] )
              { // this is a "select one option" so set the answer to the option's name
                if( is_array( $answer ) )
                {
                  $option_id = current( $answer );
                  foreach( $column['option_list'] as $option )
                  {
                    if( $option_id == $option['id'] )
                    {
                      $row_value = $option['name'];
                      break;
                    }
                  }
                }
              }
              else // date, number, string and text are all just direct answers
              {
                $row_value = $answer;
              }
            }
          }
        }

        $data_row[] = $row_value;
      }

      $data[] = $data_row;
    }

    $header = array_keys( $column_list );
    array_unshift( $header, 'uid', 'rank', 'submitted', 'start_datetime', 'last_datetime' );
    return array( 'header' => $header, 'data' => $data );
  }

  /**
   * Applies a patch file to the qnaire and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   * @return stdObject
   */
  public function process_patch( $patch_object, $apply = false )
  {
    set_time_limit( 900 ); // 15 minutes max

    $language_class_name = lib::get_class_name( 'database\language' );
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );
    $qnaire_description_class_name = lib::get_class_name( 'database\qnaire_description' );
    $module_class_name = lib::get_class_name( 'database\module' );

    // NOTE: since we want to avoid duplicate unique keys caused by re-naming or re-ordering modules we use the following
    // offset and suffix values when setting rank and name, then after all changes have been made remove the offset/suffix
    $name_suffix = bin2hex( openssl_random_pseudo_bytes( 5 ) );

    $difference_list = array();

    foreach( $patch_object as $property => $value )
    {
      if( 'base_language' == $property )
      {
        $db_language = $language_class_name::get_unique_record( 'code', $patch_object->base_language );
        if( $db_language->id != $this->base_language_id )
        {
          if( $apply ) $this->base_language_id = $db_language->id;
          else $difference_list['base_language'] = $patch_object->base_language;
        }
      }
      else if( 'language_list' == $property )
      {
        // check every item in the patch object for additions
        $add_list = array();
        foreach( $patch_object->language_list as $lang )
        {
          $language_mod = lib::create( 'database\modifier' );
          $language_mod->where( 'language.code', '=', $lang );
          if( 0 == $this->get_language_count( $language_mod ) )
          {
            if( $apply )
            {
              $db_language = $language_class_name::get_unique_record( 'code', $lang );
              $this->add_language( $db_language->id );
            }
            else $add_list[] = $lang;
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        $language_sel = lib::create( 'database\select' );
        $language_sel->add_column( 'id' );
        $language_sel->add_column( 'code' );
        foreach( $this->get_language_list( $language_sel ) as $language )
        {
          $found = false;
          foreach( $patch_object->language_list as $lang ) if( $language['code'] == $lang )
          {
            $found = true;
            break;
          }
          if( !$found )
          {
            if( $apply ) $this->remove_language( $language['id'] );
            else $remove_list[] = $language['code'];
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['language_list'] = $diff_list;
      }
      else if( 'reminder_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->reminder_list as $reminder )
        {
          $reminder_mod = lib::create( 'database\modifier' );
          $reminder_mod->where( 'offset', '=', $reminder->offset );
          $reminder_mod->where( 'unit', '=', $reminder->unit );
          $reminder_list = $this->get_reminder_object_list( $reminder_mod );
          $db_reminder = 0 == count( $reminder_list ) ? NULL : current( $reminder_list );
          if( is_null( $db_reminder ) )
          {
            if( $apply )
            {
              $db_reminder = lib::create( 'database\reminder' );
              $db_reminder->qnaire_id = $this->id;
              $db_reminder->offset = $reminder->offset;
              $db_reminder->unit = $reminder->unit;
              $db_reminder->save();

              foreach( $reminder->reminder_description_list as $reminder_description )
              {
                $db_language = $language_class_name::get_unique_record( 'code', $reminder_description->language );
                $db_reminder_description = $reminder_description_class_name::get_unique_record(
                  array( 'reminder_id', 'language_id', 'type' ),
                  array( $db_reminder->id, $db_language->id, $reminder_description->type )
                );
                $db_reminder_description->value = $reminder_description->value;
                $db_reminder_description->save();
              }
            }
            else $add_list[] = $reminder;
          }
          else
          {
            $diff = array();
            foreach( $reminder as $property => $value )
            {
              if( 'reminder_description_list' == $property )
              {
                $reminder_description_list = $value;

                // check every item in the patch object for additions and changes
                $desc_add_list = array();
                $desc_change_list = array();
                foreach( $reminder_description_list as $description )
                {
                  $db_language = $language_class_name::get_unique_record( 'code', $description->language );
                  $db_reminder_description = $reminder_description_class_name::get_unique_record(
                    array( 'reminder_id', 'language_id', 'type' ),
                    array( $db_reminder->id, $db_language->id, $description->type )
                  );

                  if( is_null( $db_reminder_description ) )
                  {
                    if( $apply )
                    {
                      $db_reminder_description = lib::create( $description_name );
                      $db_reminder_description->reminder_id = $db_reminder->id;
                      $db_reminder_description->language_id = $db_language->id;
                      $db_reminder_description->type = $description->type;
                      $db_reminder_description->value = $description->value;
                      $db_reminder_description->save();
                    }
                    else $desc_add_list[] = $description;
                  }
                  else
                  {
                    // find and add all differences
                    $diff = array();
                    foreach( $description as $property => $value )
                      if( 'language' != $property && $db_reminder_description->$property != $description->$property )
                        $diff[$property] = $description->$property;

                    if( 0 < count( $diff ) )
                    {
                      if( $apply )
                      {
                        $db_reminder_description->value = $description->value;
                        $db_reminder_description->save();
                      }
                      else
                      {
                        $index = sprintf( '%s [%s]', $description->type, $db_language->code );
                        $desc_change_list[$index] = $diff;
                      }
                    }
                  }
                }

                // check every item in this object for removals
                $desc_remove_list = array();
                foreach( $db_reminder->get_reminder_description_object_list() as $db_reminder_description )
                {
                  $found = false;
                  foreach( $patch_object->reminder_description_list as $description )
                  {
                    if( $db_reminder_description->get_language()->code == $description->language &&
                        $db_reminder_description->type == $description->type )
                    {
                      $found = true;
                      break;
                    }
                  }

                  if( !$found )
                  {
                    if( $apply ) $db_reminder_description->delete();
                    else
                    {
                      $index = sprintf( '%s [%s]', $db_reminder_description->type, $db_reminder_description->get_language()->code );
                      $desc_remove_list[] = $index;
                    }
                  }
                }

                $desc_diff_list = array();
                if( 0 < count( $desc_add_list ) ) $desc_diff_list['add'] = $desc_add_list;
                if( 0 < count( $desc_change_list ) ) $desc_diff_list['change'] = $desc_change_list;
                if( 0 < count( $desc_remove_list ) ) $desc_diff_list['remove'] = $desc_remove_list;
                if( 0 < count( $desc_diff_list ) ) $diff_list['reminder_description_list'] = $desc_diff_list;
              }
              else
              {
                if( $db_reminder->$property != $reminder->$property )
                  $diff[$property] = $reminder->$property;
              }
            }

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_reminder->unit = $reminder->unit;
                $db_reminder->offset = $reminder->offset;
                $db_reminder->save();
              }
              else
              {
                $index = sprintf( '%s %s', $reminder->offset, $reminder->unit );
                $change_list[$index] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        foreach( $this->get_reminder_object_list() as $db_reminder )
        {
          $found = false;
          foreach( $patch_object->reminder_list as $reminder )
          {
            if( $db_reminder->offset == $reminder->offset && $db_reminder->unit == $reminder->unit )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_reminder->delete();
            else $remove_list[] = sprintf( '%s %s', $db_reminder->offset, $db_reminder->unit );
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['reminder_list'] = $diff_list;
      }
      else if( 'attribute_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->attribute_list as $attribute )
        {
          $db_attribute = $attribute_class_name::get_unique_record(
            array( 'qnaire_id', 'name' ),
            array( $this->id, $attribute->name )
          );

          if( is_null( $db_attribute ) )
          {
            if( $apply )
            {
              $db_attribute = lib::create( 'database\attribute' );
              $db_attribute->qnaire_id = $this->id;
              $db_attribute->name = $attribute->name;
              $db_attribute->code = $attribute->code;
              $db_attribute->note = $attribute->note;
              $db_attribute->save();
            }
            else $add_list[] = $attribute;
          }
          else
          {
            // find and add all differences
            $diff = array();
            foreach( $attribute as $property => $value )
              if( $db_attribute->$property != $attribute->$property )
                $diff[$property] = $attribute->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_attribute->name = $attribute->name;
                $db_attribute->code = $attribute->code;
                $db_attribute->note = $attribute->note;
                $db_attribute->save();
              }
              else $change_list[$db_attribute->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        foreach( $this->get_attribute_object_list() as $db_attribute )
        {
          $found = false;
          foreach( $patch_object->attribute_list as $attribute )
          {
            if( $db_attribute->name == $attribute->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_attribute->delete();
            else $remove_list[] = $db_attribute->name;
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['attribute_list'] = $diff_list;
      }
      else if( 'qnaire_description_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->qnaire_description_list as $qnaire_description )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $qnaire_description->language );
          $db_qnaire_description = $qnaire_description_class_name::get_unique_record(
            array( 'qnaire_id', 'language_id', 'type' ),
            array( $this->id, $db_language->id, $qnaire_description->type )
          );

          if( is_null( $db_qnaire_description ) )
          {
            if( $apply )
            {
              $db_qnaire_description = lib::create( 'database\qnaire_description' );
              $db_qnaire_description->qnaire_id = $this->id;
              $db_qnaire_description->language_id = $db_language->id;
              $db_qnaire_description->type = $qnaire_description->type;
              $db_qnaire_description->value = $qnaire_description->value;
              $db_qnaire_description->save();
            }
            else $add_list[] = $qnaire_description;
          }
          else
          {
            // find and add all differences
            $diff = array();
            foreach( $qnaire_description as $property => $value )
              if( 'language' != $property && $db_qnaire_description->$property != $qnaire_description->$property )
                $diff[$property] = $qnaire_description->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_qnaire_description->value = $qnaire_description->value;
                $db_qnaire_description->save();
              }
              else
              {
                $index = sprintf( '%s [%s]', $qnaire_description->type, $db_language->code );
                $change_list[$index] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        foreach( $this->get_qnaire_description_object_list() as $db_qnaire_description )
        {
          $found = false;
          foreach( $patch_object->qnaire_description_list as $qnaire_description )
          {
            if( $db_qnaire_description->get_language()->code == $qnaire_description->language &&
                $db_qnaire_description->type == $qnaire_description->type )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_description->delete();
            else
            {
              $index = sprintf( '%s [%s]', $db_qnaire_description->type, $db_qnaire_description->get_language()->code );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_description_list'] = $diff_list;
      }
      else if( 'module_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->module_list as $module )
        {
          // match module by name or rank
          $db_module = $module_class_name::get_unique_record( array( 'qnaire_id', 'name' ), array( $this->id, $module->name ) );
          if( is_null( $db_module ) )
          {
            // we may have renamed the module, so see if it exists exactly the same under the same rank
            $db_module = $module_class_name::get_unique_record( array( 'qnaire_id', 'rank' ), array( $this->id, $module->rank ) );
            if( !is_null( $db_module ) )
            {
              // confirm that the name is the only thing that has changed
              $properties = array_keys( get_object_vars( $db_child->process_patch( $child, $name_suffix, false ) ) );
              if( 1 != count( $properties ) || 'name' != current( $properties ) ) $db_module = NULL;
            }
          }

          if( is_null( $db_module ) )
          {
            if( $apply )
            {
              $db_module = lib::create( 'database\module' );
              $db_module->qnaire_id = $this->id;
              $db_module->rank = $module->rank;
              $db_module->name = sprintf( '%s_%s', $module->name, $name_suffix );
              $db_module->precondition = $module->precondition;
              $db_module->note = $module->note;
              $db_module->save();

              $db_module->process_patch( $module, $name_suffix, $apply );
            }
            else $add_list[] = $module;
          }
          else
          {
            // find and add all differences
            $diff = $db_module->process_patch( $module, $name_suffix, $apply );
            if( !is_null( $diff ) )
            {
              // the process_patch() function above applies any changes so we don't have to do it here
              if( !$apply ) $change_list[$db_module->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        $module_mod = lib::create( 'database\modifier' );
        $module_mod->order( 'rank' );
        foreach( $this->get_module_object_list( $module_mod ) as $db_module )
        {
          $found = false;
          foreach( $patch_object->module_list as $module )
          {
            // see if the module exists in the patch or if we're already changing the module
            $db_module_name = $apply ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_module->name ) : $db_module->name;
            if( $db_module_name == $module->name || in_array( $db_module_name, array_keys( $change_list ) ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_module->delete();
            else $remove_list[] = $db_module->name;
          }
        }

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['module_list'] = $diff_list;
      }
      else
      {
        if( $patch_object->$property != $this->$property )
        {
          if( $apply ) $this->$property = $patch_object->$property;
          else $difference_list[$property] = $patch_object->$property;
        }
      }
    }

    if( $apply )
    {
      // if we want to make the qnaire readonly we have to save that last
      $readonly = $this->readonly;
      $this->readonly = false;
      $this->save();

      // remove the name suffix from all qnaire parts
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( 'qnaire.id', '=', $this->id );
      $where_mod->where( 'module.name', 'LIKE', '%_'.$name_suffix );
      static::db()->execute( sprintf(
        'UPDATE module %s '.
        'SET module.name = REPLACE( module.name, "_%s", "" ) %s',
        $join_mod->get_sql(),
        $name_suffix,
        $where_mod->get_sql()
      ) );

      $join_mod->join( 'module', 'page.module_id', 'module.id', '', NULL, true );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( 'qnaire.id', '=', $this->id );
      $where_mod->where( 'page.name', 'LIKE', '%_'.$name_suffix );
      static::db()->execute( sprintf(
        'UPDATE page %s '.
        'SET page.name = REPLACE( page.name, "_%s", "" ) %s',
        $join_mod->get_sql(),
        $name_suffix,
        $where_mod->get_sql()
      ) );

      $join_mod->join( 'page', 'question.page_id', 'page.id', '', NULL, true );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( 'qnaire.id', '=', $this->id );
      $where_mod->where( 'question.name', 'LIKE', '%_'.$name_suffix );
      static::db()->execute( sprintf(
        'UPDATE question %s '.
        'SET question.name = REPLACE( question.name, "_%s", "" ) %s',
        $join_mod->get_sql(),
        $name_suffix,
        $where_mod->get_sql()
      ) );

      $join_mod->join( 'question', 'question_option.question_id', 'question.id', '', NULL, true );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( 'qnaire.id', '=', $this->id );
      $where_mod->where( 'question_option.name', 'LIKE', '%_'.$name_suffix );
      static::db()->execute( sprintf(
        'UPDATE question_option %s '.
        'SET question_option.name = REPLACE( question_option.name, "_%s", "" ) %s',
        $join_mod->get_sql(),
        $name_suffix,
        $where_mod->get_sql()
      ) );

      if( $readonly )
      {
        $this->readonly = true;
        $this->save();
      }

      return null;
    }
    else return (object)$difference_list;
  }

  /**
   * Exports or prints the qnaire
   * @param string $type One of "export" or "print"
   */
  public function generate( $type = 'export' )
  {
    $qnaire_data = array(
      'base_language' => $this->get_base_language()->code,
      'name' => $this->name,
      'variable_suffix' => $this->variable_suffix,
      'debug' => $this->debug,
      'readonly' => $this->readonly,
      'repeated' => $this->repeated,
      'repeat_offset' => $this->repeat_offset,
      'max_responses' => $this->max_responses,
      'email_from_name' => $this->email_from_name,
      'email_from_address' => $this->email_from_address,
      'email_invitation' => $this->email_invitation,
      'description' => $this->description,
      'note' => $this->note,
      'language_list' => array(),
      'attribute_list' => array(),
      'reminder_list' => array(),
      'qnaire_description_list' => array(),
      'module_list' => array()
    );

    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    foreach( $this->get_language_list( $language_sel ) as $item ) $qnaire_data['language_list'][] = $item['code'];

    $attribute_sel = lib::create( 'database\select' );
    $attribute_sel->add_column( 'name' );
    $attribute_sel->add_column( 'code' );
    $attribute_sel->add_column( 'note' );
    foreach( $this->get_attribute_list( $attribute_sel ) as $item ) $qnaire_data['attribute_list'][] = $item;

    foreach( $this->get_reminder_object_list() as $db_reminder )
    {
      $item = array(
        'offset' => $db_reminder->offset,
        'unit' => $db_reminder->unit,
        'reminder_description_list' => array()
      );

      $reminder_description_sel = lib::create( 'database\select' );
      $reminder_description_sel->add_table_column( 'language', 'code', 'language' );
      $reminder_description_sel->add_column( 'type' );
      $reminder_description_sel->add_column( 'value' );
      $reminder_description_mod = lib::create( 'database\modifier' );
      $reminder_description_mod->join( 'language', 'reminder_description.language_id', 'language.id' );
      $reminder_description_mod->order( 'type' );
      $reminder_description_mod->order( 'language.code' );
      foreach( $db_reminder->get_reminder_description_list( $reminder_description_sel, $reminder_description_mod ) as $item )
        $item['reminder_description_list'][] = $item;

      $qnaire_data['reminder_list'][] = $item;
    }

    $qnaire_description_sel = lib::create( 'database\select' );
    $qnaire_description_sel->add_table_column( 'language', 'code', 'language' );
    $qnaire_description_sel->add_column( 'type' );
    $qnaire_description_sel->add_column( 'value' );
    $qnaire_description_mod = lib::create( 'database\modifier' );
    $qnaire_description_mod->join( 'language', 'qnaire_description.language_id', 'language.id' );
    $qnaire_description_mod->order( 'type' );
    $qnaire_description_mod->order( 'language.code' );
    foreach( $this->get_qnaire_description_list( $qnaire_description_sel, $qnaire_description_mod ) as $item )
      $qnaire_data['qnaire_description_list'][] = $item;

    $module_mod = lib::create( 'database\modifier' );
    $module_mod->order( 'module.rank' );
    foreach( $this->get_module_object_list( $module_mod ) as $db_module )
    {
      $module = array(
        'rank' => $db_module->rank,
        'name' => $db_module->name,
        'precondition' => $db_module->precondition,
        'note' => $db_module->note,
        'module_description_list' => array(),
        'page_list' => array()
      );

      $module_description_sel = lib::create( 'database\select' );
      $module_description_sel->add_table_column( 'language', 'code', 'language' );
      $module_description_sel->add_column( 'type' );
      $module_description_sel->add_column( 'value' );
      $module_description_mod = lib::create( 'database\modifier' );
      $module_description_mod->join( 'language', 'module_description.language_id', 'language.id' );
      $module_description_mod->order( 'type' );
      $module_description_mod->order( 'language.code' );
      foreach( $db_module->get_module_description_list( $module_description_sel, $module_description_mod ) as $item )
        $module['module_description_list'][] = $item;

      $page_mod = lib::create( 'database\modifier' );
      $page_mod->order( 'page.rank' );
      foreach( $db_module->get_page_object_list( $page_mod ) as $db_page )
      {
        $page = array(
          'rank' => $db_page->rank,
          'name' => $db_page->name,
          'precondition' => $db_page->precondition,
          'note' => $db_page->note,
          'page_description_list' => array(),
          'question_list' => array()
        );

        $page_description_sel = lib::create( 'database\select' );
        $page_description_sel->add_table_column( 'language', 'code', 'language' );
        $page_description_sel->add_column( 'type' );
        $page_description_sel->add_column( 'value' );
        $page_description_mod = lib::create( 'database\modifier' );
        $page_description_mod->join( 'language', 'page_description.language_id', 'language.id' );
        $page_description_mod->order( 'type' );
        $page_description_mod->order( 'language.code' );
        foreach( $db_page->get_page_description_list( $page_description_sel, $page_description_mod ) as $item )
          $page['page_description_list'][] = $item;

        $question_mod = lib::create( 'database\modifier' );
        $question_mod->order( 'question.rank' );
        foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
        {
          $question = array(
            'rank' => $db_question->rank,
            'name' => $db_question->name,
            'type' => $db_question->type,
            'mandatory' => $db_question->mandatory,
            'dkna_refuse' => $db_question->dkna_refuse,
            'minimum' => $db_question->minimum,
            'maximum' => $db_question->maximum,
            'default_answer' => $db_question->default_answer,
            'precondition' => $db_question->precondition,
            'note' => $db_question->note,
            'question_description_list' => array(),
            'question_option_list' => array()
          );

          $question_description_sel = lib::create( 'database\select' );
          $question_description_sel->add_table_column( 'language', 'code', 'language' );
          $question_description_sel->add_column( 'type' );
          $question_description_sel->add_column( 'value' );
          $question_description_mod = lib::create( 'database\modifier' );
          $question_description_mod->join( 'language', 'question_description.language_id', 'language.id' );
          $question_description_mod->order( 'type' );
          $question_description_mod->order( 'language.code' );
          foreach( $db_question->get_question_description_list( $question_description_sel, $question_description_mod ) as $item )
            $question['question_description_list'][] = $item;

          $question_option_mod = lib::create( 'database\modifier' );
          $question_option_mod->order( 'question_option.rank' );
          foreach( $db_question->get_question_option_object_list( $question_option_mod ) as $db_question_option )
          {
            $question_option = array(
              'rank' => $db_question_option->rank,
              'name' => $db_question_option->name,
              'exclusive' => $db_question_option->exclusive,
              'extra' => $db_question_option->extra,
              'multiple_answers' => $db_question_option->multiple_answers,
              'minimum' => $db_question_option->minimum,
              'maximum' => $db_question_option->maximum,
              'precondition' => $db_question_option->precondition,
              'question_option_description_list' => array()
            );

            $qod_sel = lib::create( 'database\select' );
            $qod_sel->add_table_column( 'language', 'code', 'language' );
            $qod_sel->add_column( 'type' );
            $qod_sel->add_column( 'value' );
            $qod_mod = lib::create( 'database\modifier' );
            $qod_mod->join( 'language', 'question_option_description.language_id', 'language.id' );
            $qod_mod->order( 'type' );
            $qod_mod->order( 'language.code' );
            foreach( $db_question_option->get_question_option_description_list( $qod_sel, $qod_mod ) as $item )
              $question_option['question_option_description_list'][] = $item;

            $question['question_option_list'][] = $question_option;
          }

          if( 0 == count( $question['question_option_list'] ) ) unset( $question['question_option_list'] );
          $page['question_list'][] = $question;
        }

        $module['page_list'][] = $page;
      }

      $qnaire_data['module_list'][] = $module;
    }

    if( 'export' == $type )
    {
      $filename = sprintf( '%s/%s.json', QNAIRE_EXPORT_PATH, $this->id );
      $contents = util::json_encode( $qnaire_data, JSON_PRETTY_PRINT );
    }
    else // print
    {
      $filename = sprintf( '%s/%s.txt', QNAIRE_PRINT_PATH, $this->id );
      $contents = sprintf( "%s\n", $qnaire_data['name'] )
                . sprintf( "====================================================================================\n\n" );
      if( $qnaire_data['description'] )$contents .= sprintf( "%s\n\n", $qnaire_data['description'] );

      $description = array( 'introduction' => array(), 'conclusion' => array(), 'closed' => array() );
      foreach( $qnaire_data['qnaire_description_list'] as $d )
        if( in_array( $d['type'], ['introduction', 'conclusion', 'closed'] ) ) $description[$d['type']][$d['language']] = $d['value'];

      $contents .= sprintf( "INTRODUCTION\n" )
                 . sprintf( "====================================================================================\n\n" );
      foreach( $description['introduction'] as $language => $value ) $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      $contents .= sprintf( "CONCLUSION\n" )
                 . sprintf( "====================================================================================\n\n" );
      foreach( $description['conclusion'] as $language => $value ) $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      $contents .= sprintf( "CLOSED\n" )
                 . sprintf( "====================================================================================\n\n" );
      foreach( $description['closed'] as $language => $value ) $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      foreach( $qnaire_data['module_list'] as $module )
      {
        $contents .= sprintf(
          "%d) MODULE %s%s\n",
          $module['rank'],
          $module['name'],
          is_null( $module['precondition'] ) ? '' : sprintf( ' (precondition: %s)', $module['precondition'] )
        ) . sprintf( "====================================================================================\n\n" );

        $description = array( 'prompt' => array(), 'popup' => array() );
        foreach( $module['module_description_list'] as $d ) $description[$d['type']][$d['language']] = $d['value'];

        foreach( $description['prompt'] as $language => $value )
        {
          $contents .= sprintf(
            "[%s] %s%s\n",
            $language,
            $value,
            is_null( $description['popup'][$language] ) ? '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
          );
        }
        $contents .= "\n";

        foreach( $module['page_list'] as $page )
        {
          $contents .= sprintf(
            "%d.%d) PAGE %s%s\n",
            $module['rank'],
            $page['rank'],
            $page['name'],
            is_null( $page['precondition'] ) ? '' : sprintf( ' (precondition: %s)', $page['precondition'] )
          ) . sprintf( "====================================================================================\n\n" );

          $description = array( 'prompt' => array(), 'popup' => array() );
          foreach( $page['page_description_list'] as $d ) $description[$d['type']][$d['language']] = $d['value'];

          foreach( $description['prompt'] as $language => $value )
          {
            $contents .= sprintf(
              "[%s] %s%s\n",
              $language,
              $value,
              is_null( $description['popup'][$language] ) ? '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
            );
          }
          $contents .= "\n";

          foreach( $page['question_list'] as $question )
          {
            $contents .= sprintf(
              "%d.%d.%d) QUESTION %s%s\n",
              $module['rank'],
              $page['rank'],
              $question['rank'],
              $question['name'],
              is_null( $question['precondition'] ) ? '' : sprintf( ' (precondition: %s)', $question['precondition'] )
            ) . sprintf( "====================================================================================\n\n" );

            $description = array( 'prompt' => array(), 'popup' => array() );
            foreach( $question['question_description_list'] as $d ) $description[$d['type']][$d['language']] = $d['value'];

            foreach( $description['prompt'] as $language => $value )
            {
              $contents .= sprintf(
                "[%s] %s%s\n",
                $language,
                $value,
                is_null( $description['popup'][$language] ) ? '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
              );
            }
            $contents .= "\n";

            if( 0 < count( $question['question_option_list'] ) )
            {
              foreach( $question['question_option_list'] as $question_option )
              {
                $contents .= sprintf(
                  "OPTION #%d, %s%s:\n",
                  $question_option['rank'],
                  $question_option['name'],
                  is_null( $question_option['precondition'] ? '' : sprintf( ' (precondition: %s)', $question_option['precondition'] ) )
                );

                $description = array( 'prompt' => array(), 'popup' => array() );
                foreach( $question_option['question_option_description_list'] as $d )
                  $description[$d['type']][$d['language']] = $d['value'];

                foreach( $description['prompt'] as $language => $value )
                {
                  $contents .= sprintf(
                    "[%s] %s%s\n",
                    $language,
                    $value,
                    is_null( $description['popup'][$language] ) ? '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
                  );
                }
                $contents .= "\n";
              }
            }
          }
        }
      }
    }

    if( false === file_put_contents( $filename, $contents, LOCK_EX ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf(
          'Failed to generate qnaire %s file "%s" for qnaire %s',
          $type,
          $filename,
          $this->name
        ),
        __METHOD__
      );
    }
  }

  /**
   * Imports a qnaire from an export object and returns the new qnaire's primary ID
   * @param stdClass $qnaire_object
   * @return integer
   */
  public static function import( $qnaire_object )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $default_page_max_time = lib::create( 'business\setting_manager' )->get_setting( 'general', 'default_page_max_time' );

    $db_qnaire = lib::create( 'database\qnaire' );
    $db_qnaire->base_language_id = $language_class_name::get_unique_record( 'code', $qnaire_object->base_language )->id;
    $db_qnaire->name = $qnaire_object->name;
    $db_qnaire->variable_suffix = $qnaire_object->variable_suffix;
    $db_qnaire->debug = $qnaire_object->debug;
    $db_qnaire->repeated = $qnaire_object->repeated;
    $db_qnaire->repeat_offset = $qnaire_object->repeat_offset;
    $db_qnaire->max_responses = $qnaire_object->max_responses;
    $db_qnaire->email_from_name = $qnaire_object->email_from_name;
    $db_qnaire->email_from_address = $qnaire_object->email_from_address;
    $db_qnaire->email_invitation = $qnaire_object->email_invitation;
    $db_qnaire->description = $qnaire_object->description;
    $db_qnaire->note = $qnaire_object->note;
    $db_qnaire->save();

    foreach( $qnaire_object->language_list as $language )
      $db_qnaire->add_language( $language_class_name::get_unique_record( 'code', $language )->id );

    foreach( $qnaire_object->reminder_list as $reminder )
    {
      $db_reminder = lib::create( 'database\reminder' );
      $db_reminder->qnaire_id = $db_qnaire->id;
      $db_reminder->offset = $reminder->offset;
      $db_reminder->unit = $reminder->unit;
      $db_reminder->save();

      foreach( $reminder->reminder_description_list as $reminder_description )
      {
        $db_language = $language_class_name::get_unique_record( 'code', $reminder_description->language );
        $db_reminder_description = $db_reminder->get_description( $reminder_description->type, $db_language );
        $db_reminder_description->value = $reminder_description->value;
        $db_reminder_description->save();
      }
    }

    foreach( $qnaire_object->attribute_list as $attribute )
    {
      $db_attribute = lib::create( 'database\attribute' );
      $db_attribute->qnaire_id = $db_qnaire->id;
      $db_attribute->name = $attribute->name;
      $db_attribute->code = $attribute->code;
      $db_attribute->note = $attribute->note;
      $db_attribute->save();
    }

    foreach( $qnaire_object->qnaire_description_list as $qnaire_description )
    {
      $db_language = $language_class_name::get_unique_record( 'code', $qnaire_description->language );
      $db_qnaire_description = $db_qnaire->get_description( $qnaire_description->type, $db_language );
      $db_qnaire_description->value = $qnaire_description->value;
      $db_qnaire_description->save();
    }

    foreach( $qnaire_object->module_list as $module_object )
    {
      $db_module = lib::create( 'database\module' );
      $db_module->qnaire_id = $db_qnaire->id;
      $db_module->rank = $module_object->rank;
      $db_module->name = $module_object->name;
      $db_module->precondition = $module_object->precondition;
      $db_module->note = $module_object->note;
      $db_module->save();

      foreach( $module_object->module_description_list as $module_description )
      {
        $db_language = $language_class_name::get_unique_record( 'code', $module_description->language );
        $db_module_description = $db_module->get_description( $module_description->type, $db_language );
        $db_module_description->value = $module_description->value;
        $db_module_description->save();
      }

      foreach( $module_object->page_list as $page_object )
      {
        $db_page = lib::create( 'database\page' );
        $db_page->module_id = $db_module->id;
        $db_page->rank = $page_object->rank;
        $db_page->name = $page_object->name;
        $db_page->max_time = $default_page_max_time;

        $db_page->precondition = $page_object->precondition;
        $db_page->note = $page_object->note;
        $db_page->save();

        foreach( $page_object->page_description_list as $page_description )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $page_description->language );
          $db_page_description = $db_page->get_description( $page_description->type, $db_language );
          $db_page_description->value = $page_description->value;
          $db_page_description->save();
        }

        foreach( $page_object->question_list as $question_object )
        {
          $db_question = lib::create( 'database\question' );
          $db_question->page_id = $db_page->id;
          $db_question->rank = $question_object->rank;
          $db_question->name = $question_object->name;
          $db_question->type = $question_object->type;
          $db_question->mandatory = $question_object->mandatory;
          $db_question->dkna_refuse = $question_object->dkna_refuse;
          $db_question->minimum = $question_object->minimum;
          $db_question->maximum = $question_object->maximum;
          $db_question->default_answer = $question_object->default_answer;
          $db_question->precondition = $question_object->precondition;
          $db_question->note = $question_object->note;
          $db_question->save();

          foreach( $question_object->question_description_list as $question_description )
          {
            $db_language = $language_class_name::get_unique_record( 'code', $question_description->language );
            $db_question_description = $db_question->get_description( $question_description->type, $db_language );
            $db_question_description->value = $question_description->value;
            $db_question_description->save();
          }

          if( property_exists( $question_object, 'question_option_list' ) )
          {
            foreach( $question_object->question_option_list as $question_option_object )
            {
              $db_question_option = lib::create( 'database\question_option' );
              $db_question_option->question_id = $db_question->id;
              $db_question_option->rank = $question_option_object->rank;
              $db_question_option->name = $question_option_object->name;
              $db_question_option->exclusive = $question_option_object->exclusive;
              $db_question_option->extra = $question_option_object->extra;
              $db_question_option->multiple_answers = $question_option_object->multiple_answers;
              $db_question_option->minimum = $question_option_object->minimum;
              $db_question_option->maximum = $question_option_object->maximum;
              $db_question_option->precondition = $question_option_object->precondition;
              $db_question_option->save();

              foreach( $question_option_object->question_option_description_list as $question_option_description )
              {
                $db_language = $language_class_name::get_unique_record( 'code', $question_option_description->language );
                $db_question_option_description =
                  $db_question_option->get_description( $question_option_description->type, $db_language );
                $db_question_option_description->value = $question_option_description->value;
                $db_question_option_description->save();
              }
            }
          }
        }
      }
    }

    if( $qnaire_object->readonly )
    {
      $db_qnaire->readonly = $qnaire_object->readonly;
      $db_qnaire->save();
    }

    return $db_qnaire->id;
  }

  /**
   * Recalculates the average time taken to complete the qnaire
   * @static
   */
  public static function recalculate_average_time()
  {
    $select = lib::create( 'database\select' );
    $select->from( 'qnaire' );
    $select->add_column( 'id' );
    $select->add_column( 'SUM( time ) / COUNT( DISTINCT response.id )', 'average_time', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'respondent', 'qnaire.id', 'respondent.qnaire_id' );
    $modifier->join( 'response', 'respondent.id', 'response.respondent_id' );
    $modifier->join( 'page_time', 'response.id', 'page_time.response_id' );
    $modifier->join( 'page', 'page_time.page_id', 'page.id' );
    $modifier->where( 'IFNULL( page_time.time, 0 )', '<=', 'page.max_time', false );
    $modifier->where( 'response.submitted', '=', true );
    $modifier->group( 'qnaire.id' );

    static::db()->execute( sprintf(
      "REPLACE INTO qnaire_average_time( qnaire_id, time )\n%s %s",
      $select->get_sql(),
      $modifier->get_sql()
    ) );
  }
}
