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
      if( !is_null( $this->repeated ) )
      {
        if( is_null( $this->repeat_offset ) ) $this->repeat_offset = 0;
        if( is_null( $this->max_responses ) ) $this->max_responses = 0;
      }
      else
      {
        $this->repeat_offset = NULL;
        $this->max_responses = NULL;
      }
    }

    if( $this->has_column_changed( 'email_reminder' ) )
    {
      if( !is_null( $this->email_reminder ) )
      {
        if( is_null( $this->email_reminder_offset ) ) $this->email_reminder_offset = 0;
      }
      else
      {
        $this->email_reminder_offset = NULL;
      }
    }

    parent::save();
  }

  /**
   * TODO: document
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
   * TODO: document
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

    $question_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    return is_null( $question_id ) ? NULL : lib::create( 'database\question', $question_id );
  }

  /**
   * TODO: document
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
   * TODO: document
   */
  public function get_average_time( $submitted = false )
  {
    $select = lib::create( 'database\select' );
    $select->add_column( 'SUM( time ) / COUNT( DISTINCT response.id )', 'average_time', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'respondent', 'qnaire.id', 'respondent.qnaire_id' );
    $modifier->join( 'response', 'respondent.id', 'response.respondent_id' );
    $modifier->join( 'page_time', 'response.id', 'page_time.response_id' );
    $modifier->join( 'page', 'page_time.page_id', 'page.id' );
    $modifier->where( 'IFNULL( page_time.time, 0 )', '<=', 'page.max_time', false );
    if( $submitted ) $modifier->where( 'response.submitted', '=', true );

    return current( $this->select( $select, $modifier ) )['average_time'];
  }

  /**
   * TODO: document
   */
  public function clone_from( $db_source_qnaire )
  {
    $ignore_columns = array( 'id', 'update_timestamp', 'create_timestamp', 'name' );
    foreach( $this->get_column_names() as $column_name )
      if( !in_array( $column_name, $ignore_columns ) )
        $this->$column_name = $db_source_qnaire->$column_name;
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
   * TODO: document
   */
  public function get_description( $type, $db_language )
  {
    $qnaire_description = lib::get_class_name( 'database\qnaire_description' );
    return $qnaire_description::get_unique_record(
      array( 'qnaire_id', 'language_id', 'type' ),
      array( $this->id, $db_language->id, $type )
    );
  }

  /**
   * TODO: document
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
   * TODO: document
   */
  public function mass_respondent( $uid_list )
  {
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
   * TODO: document
   */
  public function generate_export( $qnaire_name = NULL )
  {
    $export = array(
      'base_language' => $this->get_base_language()->code,
      'name' => is_null( $qnaire_name ) ? $this->name : $qnaire_name,
      'debug' => $this->debug,
      'readonly' => $this->readonly,
      'repeated' => $this->repeated,
      'repeat_offset' => $this->repeat_offset,
      'max_responses' => $this->max_responses,
      'email_from_name' => $this->email_from_name,
      'email_from_address' => $this->email_from_address,
      'email_invitation' => $this->email_invitation,
      'email_reminder' => $this->email_reminder,
      'email_reminder_offset' => $this->email_reminder_offset,
      'description' => $this->description,
      'note' => $this->note,
      'language_list' => array(),
      'attribute_list' => array(),
      'qnaire_description_list' => array(),
      'module_list' => array()
    );

    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    foreach( $this->get_language_list( $language_sel ) as $item ) $export['language_list'][] = $item['code'];

    $attribute_sel = lib::create( 'database\select' );
    $attribute_sel->add_column( 'name' );
    $attribute_sel->add_column( 'code' );
    $attribute_sel->add_column( 'note' );
    foreach( $this->get_attribute_list( $attribute_sel ) as $item ) $export['attribute_list'][] = $item;

    $qnaire_description_sel = lib::create( 'database\select' );
    $qnaire_description_sel->add_table_column( 'language', 'code', 'language' );
    $qnaire_description_sel->add_column( 'type' );
    $qnaire_description_sel->add_column( 'value' );
    $qnaire_description_mod = lib::create( 'database\modifier' );
    $qnaire_description_mod->join( 'language', 'qnaire_description.language_id', 'language.id' );
    foreach( $this->get_qnaire_description_list( $qnaire_description_sel, $qnaire_description_mod ) as $item )
      $export['qnaire_description_list'][] = $item;

    $module_mod = lib::create( 'database\modifier' );
    $module_mod->order( 'rank' );
    foreach( $this->get_module_object_list() as $db_module )
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
      foreach( $db_module->get_module_description_list( $module_description_sel, $module_description_mod ) as $item )
        $module['module_description_list'][] = $item;

      $page_mod = lib::create( 'database\modifier' );
      $page_mod->order( 'rank' );
      foreach( $db_module->get_page_object_list() as $db_page )
      {
        $page = array(
          'rank' => $db_page->rank,
          'name' => $db_page->name,
          'max_time' => $db_page->max_time,
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
        foreach( $db_page->get_page_description_list( $page_description_sel, $page_description_mod ) as $item )
          $page['page_description_list'][] = $item;

        $question_mod = lib::create( 'database\modifier' );
        $question_mod->order( 'rank' );
        foreach( $db_page->get_question_object_list() as $db_question )
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
          foreach( $db_question->get_question_description_list( $question_description_sel, $question_description_mod ) as $item )
            $question['question_description_list'][] = $item;

          $question_option_mod = lib::create( 'database\modifier' );
          $question_option_mod->order( 'rank' );
          foreach( $db_question->get_question_option_object_list() as $db_question_option )
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
              'question_option_description_list' => array(),
              'question_option_option_list' => array()
            );

            $qod_sel = lib::create( 'database\select' );
            $qod_sel->add_table_column( 'language', 'code', 'language' );
            $qod_sel->add_column( 'type' );
            $qod_sel->add_column( 'value' );
            $qod_mod = lib::create( 'database\modifier' );
            $qod_mod->join( 'language', 'question_option_description.language_id', 'language.id' );
            foreach( $db_question_option->get_question_option_description_list( $qod_sel, $qod_mod ) as $item )
              $question_option['question_option_description_list'][] = $item;

            $question['question_option_list'][] = $question_option;
          } 

          $page['question_list'][] = $question;
        } 

        $module['page_list'][] = $page;
      }

      $export['module_list'][] = $module;
    }

    $filename = sprintf( '%s/%s.json', QNAIRE_EXPORT_PATH, $this->id );
    if( false === file_put_contents( $filename, util::json_encode( $export, JSON_PRETTY_PRINT ), LOCK_EX ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf(
          'Failed to generate qnaire export json file "%s" for qnaire %s',
          $filename,
          $this->name
        ),
        __METHOD__
      );
    }
  }
}
