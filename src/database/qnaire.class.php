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
   * Override the parent method
   */
  public static function get_relationship( $record_type )
  {
    // artificially create a relationship between response and qnaire
    $relationship_class_name = lib::get_class_name( 'database\relationship' );
    return 'response' == $record_type ?
      $relationship_class_name::ONE_TO_MANY : parent::get_relationship( $record_type );
  }

  /**
   * Override the parent method
   */
  protected function get_record_list(
    $record_type, $select = NULL, $modifier = NULL, $return_alt = '', $distinct = false )
  {
    $response_class_name = lib::get_class_name( 'database\response' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );

    if( !is_string( $record_type ) || 0 == strlen( $record_type ) )
      throw lib::create( 'exception\argument', 'record_type', $record_type, __METHOD__ );
    if( !is_null( $select ) && !is_a( $select, lib::get_class_name( 'database\select' ) ) )
      throw lib::create( 'exception\argument', 'select', $select, __METHOD__ );
    if( !is_null( $modifier ) && !is_a( $modifier, lib::get_class_name( 'database\modifier' ) ) )
      throw lib::create( 'exception\argument', 'modifier', $modifier, __METHOD__ );
    if( !is_string( $return_alt ) )
      throw lib::create( 'exception\argument', 'return_alt', $return_alt, __METHOD__ );

    // artificially create a relationship between response and qnaire
    $return_value = 'count' == $return_alt ? 0 : [];
    if( 'response' == $record_type )
    {
      if( is_null( $this->id ) )
      {
        log::warning( 'Tried to query qnaire record with no primary key.' );
      }
      else
      {
        if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );

        // wrap the existing modifier's where statements to avoid logic errors
        $modifier->wrap_where();

        if( !$modifier->has_join( 'respondent_current_response' ) )
        {
          $modifier->join(
            'respondent_current_response',
            'response.id',
            'respondent_current_response.response_id'
          );
          $modifier->join(
            'respondent',
            'respondent_current_response.respondent_id',
            'respondent.id'
          );
        }

        if( !$modifier->has_join( 'qnaire' ) ) $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
        $modifier->where( 'qnaire.id', '=', $this->id );

        if( 'count' == $return_alt )
        {
          $return_value = $response_class_name::count( $modifier, $distinct );
        }
        else
        {
          $return_value = 'object' == $return_alt ?
            $response_class_name::select_objects( $modifier ) :
            $response_class_name::select( $select, $modifier );
        }
      }
    }
    // artificially create a relationship between lookup and qnaire
    else if( 'lookup' == $record_type )
    {
      if( is_null( $this->id ) )
      {
        log::warning( 'Tried to query qnaire record with no primary key.' );
      }
      else
      {
        if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );

        // wrap the existing modifier's where statements to avoid logic errors
        $modifier->wrap_where();

        if( !$modifier->has_join( 'question' ) ) $modifier->join( 'question', 'lookup.id', 'question.lookup_id' );
        if( !$modifier->has_join( 'page' ) ) $modifier->join( 'page', 'question.page_id', 'page.id' );
        if( !$modifier->has_join( 'module' ) ) $modifier->join( 'module', 'page.module_id', 'module.id' );
        $modifier->where( 'module.qnaire_id', '=', $this->id );

        if( 'count' == $return_alt )
        {
          $return_value = $lookup_class_name::count( $modifier, $distinct );
        }
        else
        {
          $return_value = 'object' == $return_alt ?
            $lookup_class_name::select_objects( $modifier ) :
            $lookup_class_name::select( $select, $modifier );
        }
      }
    }
    else $return_value = parent::get_record_list( $record_type, $select, $modifier, $return_alt, $distinct );

    return $return_value;
  }

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
          $this->has_column_changed( 'stages' ) ||
          $this->has_column_changed( 'description' ) ||
          $this->has_column_changed( 'note' ) )
      {
        throw lib::create( 'exception\notice',
          'You cannot make changes to this questionnaire because it is in read-only mode.',
          __METHOD__
        );
      }
    }

    if( $this->has_column_changed( 'anonymous' ) || $this->has_column_changed( 'stages' ) )
    {
      if( $this->anonymous && $this->stages )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Tried to set qnaire "%s" to have both anonymous and stages set to true.', $this->name ),
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

    $changing_name = !is_null( $this->id ) && $this->has_column_changed( 'name' );
    $old_data_directory = $this->get_old_data_directory();

    parent::save();

    if( $changing_name )
    {
      // rename response data directories, if necessary
      if( file_exists( $old_data_directory ) ) rename( $old_data_directory, $this->get_data_directory() );
    }
  }

  /**
   * Override the parent method
   */
  public function delete()
  {
    // if we have stages we have to explicitly delete them because of database constraint on-delete voodoo
    $delete_mod = lib::create( 'database\modifier' );
    $delete_mod->where( 'qnaire_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM stage %s', $delete_mod->get_sql() ) );
    parent::delete();
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
    return $module_class_name::get_unique_record( ['qnaire_id', 'rank'], [$this->id, 1] );
  }

  /**
   * Returns the qnaire's last module
   * @return database\module
   */
  public function get_last_module()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to get first module of qnaire with no primary key.' );
      return NULL;
    }

    $module_class_name = lib::get_class_name( 'database\module' );
    return $module_class_name::get_unique_record(
      ['qnaire_id', 'rank'],
      [$this->id, $this->get_module_count()]
    );
  }

  /**
   * Returns the qnaire's first module for a response
   * 
   * @param database\response $db_response
   * @return database\module
   */
  public function get_first_module_for_response( $db_response )
  {
    // start by getting the first module
    $db_module = $this->get_first_module();
    if( is_null( $db_module ) ) return NULL;

    // make sure the first module is valid for this response
    $expression_manager = lib::create( 'business\expression_manager', $db_response );
    if( !$expression_manager->evaluate( $db_module->precondition ) )
      $db_module = $db_module->get_next_for_response( $db_response, true );

    return $db_module;
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
   * Get this qnaire's base_language record
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

    return is_null( $this->base_language_id ) ?
      NULL : lib::create( 'database\language', $this->base_language_id );
  }

  /**
   * Clones another qnaire
   * @param database\qnaire $db_source_qnaire
   */
  public function clone_from( $db_source_qnaire )
  {
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );

    $ignore_columns = ['id', 'update_timestamp', 'create_timestamp', 'name'];
    foreach( $this->get_column_names() as $column_name )
      if( !in_array( $column_name, $ignore_columns ) )
        $this->$column_name = $db_source_qnaire->$column_name;

    // override readonly, otherwise we can't create it
    $this->readonly = false;

    $this->save();

    // copy all languages
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_table_column( 'language', 'id' );
    $language_id_list = [];
    foreach( $db_source_qnaire->get_language_list( $language_sel ) as $language )
      $language_id_list[] = $language['id'];
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

    // copy all qnaire_document files
    foreach( $db_source_qnaire->get_qnaire_document_object_list() as $db_source_qnaire_document )
    {
      $db_qnaire_document = lib::create( 'database\qnaire_document' );
      $db_qnaire_document->qnaire_id = $this->id;
      $db_qnaire_document->name = $db_source_qnaire_document->name;
      $db_qnaire_document->data = $db_source_qnaire_document->data;
      $db_qnaire_document->save();
    }

    // copy all embedded files
    foreach( $db_source_qnaire->get_embedded_file_object_list() as $db_source_embedded_file )
    {
      $db_embedded_file = lib::create( 'database\embedded_file' );
      $db_embedded_file->qnaire_id = $this->id;
      $db_embedded_file->name = $db_source_embedded_file->name;
      $db_embedded_file->mime_type = $db_source_embedded_file->mime_type;
      $db_embedded_file->size = $db_source_embedded_file->size;
      $db_embedded_file->data = $db_source_embedded_file->data;
      $db_embedded_file->save();
    }

    // copy all reminders
    foreach( $db_source_qnaire->get_reminder_object_list() as $db_source_reminder )
    {
      $db_reminder = lib::create( 'database\reminder' );
      $db_reminder->qnaire_id = $this->id;
      $db_reminder->delay_offset = $db_source_reminder->delay_offset;
      $db_reminder->delay_unit = $db_source_reminder->delay_unit;
      $db_reminder->save();

      foreach( $db_source_reminder->get_reminder_description_object_list() as $db_source_reminder_description )
      {
        $db_reminder_description = $reminder_description_class_name::get_unique_record(
          ['reminder_id', 'language_id', 'type'],
          [
            $db_reminder->id,
            $db_source_reminder_description->language_id,
            $db_source_reminder_description->type
          ]
        );
        $db_reminder_description->value = $db_source_reminder_description->value;
        $db_reminder_description->save();
      }
    }

    // copy all devices
    $device_mod = lib::create( 'database\modifier' );
    $device_mod->order( 'device.id' );
    foreach( $db_source_qnaire->get_device_object_list( $device_mod ) as $db_source_device )
    {
      $db_device = lib::create( 'database\device' );
      $db_device->qnaire_id = $this->id;
      $db_device->name = $db_source_device->name;
      $db_device->url = $db_source_device->url;
      $db_device->emulate = $db_source_device->emulate;
      $db_device->save();

      // copy all device data
      $device_data_sel = lib::create( 'database\select' );
      $device_data_sel->add_column( 'name' );
      $device_data_sel->add_column( 'code' );
      $device_mod->order( 'device_data.id' );
      foreach( $db_source_device->get_device_data_list( $device_data_sel, $device_data_mod ) as $device_data )
      {
        $db_device_data = lib::create( 'database\device_data' );
        $db_device_data->device_id = $db_device->id;
        $db_device_data->name = $device_data['name'];
        $db_device_data->code = $device_data['code'];
        $db_device_data->save();
      }
    }

    // copy all reports
    $qnaire_report_mod = lib::create( 'database\modifier' );
    $qnaire_report_mod->order( 'qnaire_report.language_id' );
    $report_list = $db_source_qnaire->get_qnaire_report_object_list( $qnaire_report_mod );
    foreach( $report_list as $db_source_qnaire_report )
    {
      $db_qnaire_report = lib::create( 'database\qnaire_report' );
      $db_qnaire_report->qnaire_id = $this->id;
      $db_qnaire_report->language_id = $db_source_qnaire_report->language_id;
      $db_qnaire_report->data = $db_source_qnaire_report->data;
      $db_qnaire_report->save();

      // copy all qnaire_report data
      $rdata_sel = lib::create( 'database\select' );
      $rdata_sel->add_column( 'name' );
      $rdata_sel->add_column( 'code' );
      $rdata_mod->order( 'qnaire_report_data.id' );
      foreach( $db_source_qnaire_report->get_report_data_list( $rdata_sel, $rdata_mod ) as $report_data )
      {
        $db_qnaire_report_data = lib::create( 'database\qnaire_report_data' );
        $db_qnaire_report_data->qnaire_report_id = $db_qnaire_report->id;
        $db_qnaire_report_data->name = $report_data['name'];
        $db_qnaire_report_data->code = $report_data['code'];
        $db_qnaire_report_data->save();
      }
    }

    // remove any existing stages and modules
    $delete_mod = lib::create( 'database\modifier' );
    $delete_mod->where( 'qnaire_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM stage %s', $delete_mod->get_sql() ) );

    $delete_mod = lib::create( 'database\modifier' );
    $delete_mod->where( 'qnaire_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM module %s', $delete_mod->get_sql() ) );

    // clone all modules and stages from the source qnaire
    foreach( $db_source_qnaire->get_module_object_list() as $db_source_module )
    {
      $db_module = lib::create( 'database\module' );
      $db_module->qnaire_id = $this->id;
      $db_module->rank = $db_source_module->rank;
      $db_module->name = $db_source_module->name;
      $db_module->clone_from( $db_source_module );
    }

    // adding modules will create a default stage, so delete it again
    $delete_mod = lib::create( 'database\modifier' );
    $delete_mod->where( 'qnaire_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM stage %s', $delete_mod->get_sql() ) );

    foreach( $db_source_qnaire->get_stage_object_list() as $db_source_stage )
    {
      $db_stage = lib::create( 'database\stage' );
      $db_stage->qnaire_id = $this->id;
      $db_stage->rank = $db_source_stage->rank;
      $db_stage->name = $db_source_stage->name;
      $db_stage->clone_from( $db_source_stage );
    }

    // copy all deviation types
    $deviation_sel = lib::create( 'database\select' );
    $deviation_sel->add_column( 'type' );
    $deviation_sel->add_column( 'name' );
    $deviation_mod = lib::create( 'database\modifier' );
    $deviation_mod->order( 'deviation_type.id' );
    foreach( $db_source_qnaire->get_deviation_type_list( $deviation_sel, $deviation_mod )
      as $source_deviation_type )
    {
      $db_deviation_type = lib::create( 'database\deviation_type' );
      $db_deviation_type->qnaire_id = $this->id;
      $db_deviation_type->type = $source_deviation_type['type'];
      $db_deviation_type->name = $source_deviation_type['name'];
      $db_deviation_type->save();
    }

    // copy all consent confirms
    foreach( $db_source_qnaire->get_qnaire_consent_type_confirm_object_list() as $db_source_consent_type )
    {
      $db_qnaire_consent_type_confirm = lib::create( 'database\qnaire_consent_type_confirm' );
      $db_qnaire_consent_type_confirm->qnaire_id = $this->id;
      $db_qnaire_consent_type_confirm->consent_type_id = $db_source_consent_type->consent_type_id;
      $db_qnaire_consent_type_confirm->save();
    }

    // copy all participant triggers
    foreach( $db_source_qnaire->get_qnaire_participant_trigger_object_list() as $db_source_participant )
    {
      $db_question = $this->get_question( $db_source_participant->get_question()->name );
      $db_qnaire_participant_trigger = lib::create( 'database\qnaire_participant_trigger' );
      $db_qnaire_participant_trigger->qnaire_id = $this->id;
      $db_qnaire_participant_trigger->question_id = $db_question->id;
      $db_qnaire_participant_trigger->answer_value = $db_source_participant->answer_value;
      $db_qnaire_participant_trigger->column_name = $db_source_participant->column_name;
      $db_qnaire_participant_trigger->value = $db_source_participant->value;
      $db_qnaire_participant_trigger->save();
    }

    // copy all collection triggers
    foreach( $db_source_qnaire->get_qnaire_collection_trigger_object_list() as $db_source_collection )
    {
      $db_question = $this->get_question( $db_source_collection->get_question()->name );
      $db_qnaire_collection_trigger = lib::create( 'database\qnaire_collection_trigger' );
      $db_qnaire_collection_trigger->qnaire_id = $this->id;
      $db_qnaire_collection_trigger->collection_id = $db_source_collection->collection_id;
      $db_qnaire_collection_trigger->question_id = $db_question->id;
      $db_qnaire_collection_trigger->answer_value = $db_source_collection->answer_value;
      $db_qnaire_collection_trigger->add_to = $db_source_collection->add_to;
      $db_qnaire_collection_trigger->save();
    }

    // copy all consent triggers
    foreach( $db_source_qnaire->get_qnaire_consent_type_trigger_object_list() as $db_source_consent_type )
    {
      $db_question = $this->get_question( $db_source_consent_type->get_question()->name );
      $db_qnaire_consent_type_trigger = lib::create( 'database\qnaire_consent_type_trigger' );
      $db_qnaire_consent_type_trigger->qnaire_id = $this->id;
      $db_qnaire_consent_type_trigger->consent_type_id = $db_source_consent_type->consent_type_id;
      $db_qnaire_consent_type_trigger->question_id = $db_question->id;
      $db_qnaire_consent_type_trigger->answer_value = $db_source_consent_type->answer_value;
      $db_qnaire_consent_type_trigger->accept = $db_source_consent_type->accept;
      $db_qnaire_consent_type_trigger->save();
    }

    // copy all event triggers
    foreach( $db_source_qnaire->get_qnaire_event_type_trigger_object_list() as $db_source_event_type )
    {
      $db_question = $this->get_question( $db_source_event_type->get_question()->name );
      $db_qnaire_event_type_trigger = lib::create( 'database\qnaire_event_type_trigger' );
      $db_qnaire_event_type_trigger->qnaire_id = $this->id;
      $db_qnaire_event_type_trigger->event_type_id = $db_source_event_type->event_type_id;
      $db_qnaire_event_type_trigger->question_id = $db_question->id;
      $db_qnaire_event_type_trigger->answer_value = $db_source_event_type->answer_value;
      $db_qnaire_event_type_trigger->save();
    }

    // copy all alternate consent triggers
    foreach( $db_source_qnaire->get_qnaire_alternate_consent_type_trigger_object_list()
      as $db_source_aconsent_type )
    {
      $db_question = $this->get_question( $db_source_aconsent_type->get_question()->name );
      $db_qnaire_aconsent_type_trigger = lib::create( 'database\qnaire_alternate_consent_type_trigger' );
      $db_qnaire_aconsent_type_trigger->qnaire_id = $this->id;
      $db_qnaire_aconsent_type_trigger->alternate_consent_type_id =
        $db_source_aconsent_type->alternate_consent_type_id;
      $db_qnaire_aconsent_type_trigger->question_id = $db_question->id;
      $db_qnaire_aconsent_type_trigger->answer_value = $db_source_aconsent_type->answer_value;
      $db_qnaire_aconsent_type_trigger->accept = $db_source_aconsent_type->accept;
      $db_qnaire_aconsent_type_trigger->save();
    }

    // copy all proxy triggers
    foreach( $db_source_qnaire->get_qnaire_proxy_type_trigger_object_list() as $db_source_proxy_type )
    {
      $db_question = $this->get_question( $db_source_proxy_type->get_question()->name );
      $db_qnaire_proxy_type_trigger = lib::create( 'database\qnaire_proxy_type_trigger' );
      $db_qnaire_proxy_type_trigger->qnaire_id = $this->id;
      $db_qnaire_proxy_type_trigger->proxy_type_id = $db_source_proxy_type->proxy_type_id;
      $db_qnaire_proxy_type_trigger->question_id = $db_question->id;
      $db_qnaire_proxy_type_trigger->answer_value = $db_source_proxy_type->answer_value;
      $db_qnaire_proxy_type_trigger->save();
    }

    // copy all equipment triggers
    foreach( $db_source_qnaire->get_qnaire_equipment_type_trigger_object_list() as $db_source_equipment_type )
    {
      $db_question = $this->get_question( $db_source_equipment_type->get_question()->name );
      $db_qnaire_equipment_type_trigger = lib::create( 'database\qnaire_equipment_type_trigger' );
      $db_qnaire_equipment_type_trigger->qnaire_id = $this->id;
      $db_qnaire_equipment_type_trigger->equipment_type_id = $db_source_equipment_type->equipment_type_id;
      $db_qnaire_equipment_type_trigger->question_id = $db_question->id;
      $db_qnaire_equipment_type_trigger->loaned = $db_source_equipment_type->loaned;
      $db_qnaire_equipment_type_trigger->save();
    }

    // now copy the descriptions
    $update_mod = lib::create( 'database\modifier' );
    $update_mod->where( 'destination.qnaire_id', '=', $this->id );
    $update_mod->where( 'source.qnaire_id', '=', $db_source_qnaire->id );
    $sql = sprintf(
      'UPDATE qnaire_description AS destination '.
      'JOIN qnaire_description AS source '.
        'ON destination.language_id = source.language_id '.
        'AND destination.type = source.type '.
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
      ['qnaire_id', 'language_id', 'type'],
      [$this->id, $db_language->id, $type]
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
   * Updates all preconditions refering to a question name to a new name
   * 
   * @param database\record Either a stage, question or question_option
   * @param string $old_name The record's old name
   */
  public function update_name_in_preconditions( $db_record, $old_name )
  {
    // make sure the record is a stage, question or question_option
    if( is_null( $db_record ) )
      throw lib::create( 'exception\argument', 'db_record', $db_record, __METHOD__ );

    $type = NULL;
    if( is_a( $db_record, lib::get_class_name( 'database\stage' ) ) ) $type = 'stage';
    else if( is_a( $db_record, lib::get_class_name( 'database\question' ) ) ) $type = 'question';
    else if( is_a( $db_record, lib::get_class_name( 'database\question_option' ) ) ) $type = 'question_option';

    if( is_null( $type ) )
      throw lib::create( 'exception\argument', 'db_record', $db_record, __METHOD__ );

    $new_name = $db_record->name;

    // The sql regex match depends on what type of change we're making
    // Stages will all start with a # and end with either:
    //   "#" (for direct references),
    //   "." (for function)
    // Questions will all start with a $ and end with either:
    //   "$" (for direct references),
    //   ":" (for options)
    //   "." (for functions)
    // Question options will take the form of $QUESTION:OPTION$ or $QUESTION.extra(OPTION)$
    $match = '';
    if( 'stage' == $type )
    {
      $match = sprintf( '#%s[#:.]', $old_name );
      $replace = sprintf(
         'REPLACE( '.
           'REPLACE( '.
             '%%s.precondition, '.
             '"#%s#", '.
             '"#%s#" '.
           '), '.
           '"#%s.", '.
           '"#%s." '.
         ')',
         $old_name, $new_name,
         $old_name, $new_name
      );
    }
    else if( 'question' == $type )
    {
      $match = sprintf( '\\$%s[$:.]', $old_name );
      $replace = sprintf(
        'REPLACE( '.
          'REPLACE( '.
            'REPLACE( '.
              '%%s.precondition, '.
              '"$%s$", '.
              '"$%s$" '.
            '), '.
            '"$%s:", '.
            '"$%s:" '.
          '), '.
          '"$%s.", '.
          '"$%s." '.
        ')',
        $old_name, $new_name,
        $old_name, $new_name,
        $old_name, $new_name
      );
    }
    else // 'question_option' == $type
    {
      $db_question = $db_record->get_question();

      $match = sprintf(
        '\\$(%s((\\.extra\\( *%s *\\))|(:%s)))\\$',
        $db_question->name,
        $old_name,
        $old_name
      );
      $replace = sprintf(
        'REPLACE( '.
          'REPLACE( '.
            '%%s.precondition, '.
            '"$%s:%s$", '.
            '"$%s:%s$" '.
          '), '.
          '"$%s.extra(%s)$", '.
          '"$%s.extra(%s)$" '.
        ')',
        $db_question->name, $old_name, $db_question->name, $new_name,
        $db_question->name, $old_name, $db_question->name, $new_name
      );
    }

    // update all stages
    $where_mod = lib::create( 'database\modifier' );
    $where_mod->where( 'stage.precondition', 'RLIKE', $match );
    $where_mod->where( 'stage.qnaire_id', '=', $this->id );

    $sql = sprintf(
      'UPDATE stage SET stage.precondition = %s %s',
      sprintf( $replace, 'stage' ),
      $where_mod->get_sql()
    );
    static::db()->execute( $sql );

    // update all modules
    $where_mod = lib::create( 'database\modifier' );
    $where_mod->where( 'module.precondition', 'RLIKE', $match );
    $where_mod->where( 'module.qnaire_id', '=', $this->id );

    $sql = sprintf(
      'UPDATE module SET module.precondition = %s %s',
      sprintf( $replace, 'module' ),
      $where_mod->get_sql()
    );
    static::db()->execute( $sql );

    // update all pages
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'page', 'module.id', 'page.module_id' );

    $where_mod = lib::create( 'database\modifier' );
    $where_mod->where( 'page.precondition', 'RLIKE', $match );
    $where_mod->where( 'module.qnaire_id', '=', $this->id );

    $sql = sprintf(
      'UPDATE module %s SET page.precondition = %s %s',
      $join_mod->get_sql(),
      sprintf( $replace, 'page' ),
      $where_mod->get_sql()
    );
    static::db()->execute( $sql );

    // update all questions
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'page', 'module.id', 'page.module_id' );
    $join_mod->join( 'question', 'page.id', 'question.page_id' );

    $where_mod = lib::create( 'database\modifier' );
    $where_mod->where( 'question.precondition', 'RLIKE', $match );
    $where_mod->where( 'module.qnaire_id', '=', $this->id );

    $sql = sprintf(
      'UPDATE module %s SET question.precondition = %s %s',
      $join_mod->get_sql(),
      sprintf( $replace, 'question' ),
      $where_mod->get_sql()
    );
    static::db()->execute( $sql );

    // update all question_options
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->join( 'page', 'module.id', 'page.module_id' );
    $join_mod->join( 'question', 'page.id', 'question.page_id' );
    $join_mod->join( 'question_option', 'question.id', 'question_option.question_id' );

    $where_mod = lib::create( 'database\modifier' );
    $where_mod->where( 'question_option.precondition', 'RLIKE', $match );
    $where_mod->where( 'module.qnaire_id', '=', $this->id );

    $sql = sprintf(
      'UPDATE module %s SET question_option.precondition = %s %s',
      $join_mod->get_sql(),
      sprintf( $replace, 'question_option' ),
      $where_mod->get_sql()
    );
    static::db()->execute( $sql );
  }

  /**
   * Determines whether mail is sent by the qnaire
   * @return boolean
   */
   public function sends_mail()
   {
     return $this->email_invitation || 0 < $this->get_reminder_count();
   }

  /**
   * Sends all qnaire mail for the given identifier list
   * @param array $identifier_list A list of participant identifiers to affect
   */
  public function mass_send_all_mail( $db_identifier, $identifier_list )
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $participant_identifier_class_name = lib::get_class_name( 'database\participant_identifier' );

    foreach( $identifier_list as $identifier )
    {
      $db_participant = is_null( $db_identifier )
                      ? $participant_class_name::get_unique_record( 'uid', $identifier )
                      : $participant_identifier_class_name::get_unique_record(
                          ['identifier_id', 'value'],
                          [$db_identifier->id, $identifier]
                        )->get_participant();
      $db_respondent = $respondent_class_name::get_unique_record(
        ['qnaire_id', 'participant_id'],
        [$this->id, $db_participant->id]
      );
      $db_respondent->send_all_mail();
    }
  }

  /**
   * Removes all unsent qnaire mail for the given identifier list
   * @param array $identifier_list A list of participant identifiers to affect
   */
  public function mass_remove_unsent_mail( $db_identifier, $identifier_list )
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

    if( is_null( $db_identifier ) )
    {
      $modifier->where( 'participant.uid', 'IN', $identifier_list );
    }
    else
    {
      $modifier->join( 'participant_identifier', 'participant.id', 'participant_identifier.participant_id' );
      $modifier->where( 'participant_identifier.identifier_id', '=', $db_identifier->id );
      $modifier->where( 'participant_identifier.value', 'IN', $identifier_list );
    }

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
   * Test a detached instance's connection to the parent beartooth and pine servers
   */
  public function test_connection()
  {
    $setting_manager = lib::create( 'business\setting_manager' );
    if( !$setting_manager->get_setting( 'general', 'detached' ) || is_null( PARENT_INSTANCE_URL ) )
      return 'This instance of Pine is not detached so there is no remote connection to test.';

    $machine_username = $setting_manager->get_setting( 'general', 'machine_username' );
    $machine_password = $setting_manager->get_setting( 'general', 'machine_password' );

    // test the beartooth connection
    $url = sprintf(
      '%s/api/appointment%s',
      BEARTOOTH_INSTANCE_URL,
      is_null( $this->appointment_type ) ? '' : sprintf( '?type=%s', $this->appointment_type )
    );
    $curl = util::get_detached_curl_object( $url );

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      return sprintf(
        "Got error code %s while trying to connect to Beartooth server.\n\nMessage: %s",
        curl_errno( $curl ),
        curl_error( $curl )
      );
    }

    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 401 == $code )
    {
      return 'Unable to connect to Beartooth server, invalid username and/or password.';
    }
    else if( 306 == $code )
    {
      return sprintf( "Beartooth responded with the following notice\n\n\"%s\"", util::json_decode($response ) );
    }
    else if( 204 == $code || 300 <= $code )
    {
      return sprintf( 'Got response code %s when connecting to Beartooth server.', $code );
    }

    // now test the pine connection
    $url = sprintf(
      '%s/api/qnaire/name=%s?select={"column":["version"]}',
      PARENT_INSTANCE_URL,
      util::full_urlencode( $this->name )
    );
    $curl = util::get_detached_curl_object( $url );;

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      return sprintf(
        "Got error code %s when trying to connect to parent Pine server.\n\nMessage: %s",
        curl_errno( $curl ),
        curl_error( $curl )
      );
    }

    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 401 == $code )
    {
      return 'Unable to connect to parent Pine server, invalid username and/or password.';
    }
    else if( 404 == $code )
    {
      return sprintf( 'The questionnaire "%s" does not exist on the parent Pine server.', $this->name );
    }
    else if( 306 == $code )
    {
      return sprintf(
        "Parent Pine instance responded with the following notice\n\n\"%s\"",
        util::json_decode( $response )
      );
    }
    else if( 204 == $code || 300 <= $code )
    {
      return sprintf( 'Got error code %s when connecting to parent Pine server.', $code );
    }

    return 'Successfully connected to Beartooth and parent Pine servers.';
  }

  /**
   * Synchronizes data with the parent instance
   * 
   * This includes studies, consent types, alternate consent types, proxy types, and qnaires
   */
  public function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    // update the qnaire (but only if the version is different)
    $url_postfix = sprintf(
      '/name=%s?select={"column":["version"]}',
      util::full_urlencode( $this->name )
    );
    $parent_qnaire = util::get_data_from_parent( 'qnaire', $url_postfix );

    if( $this->version != $parent_qnaire->version )
    {
      // if the version is different then download the parent qnaire and apply it as a patch
      $old_version = $this->version;
      $new_version = $parent_qnaire->version;

      $url_postfix = sprintf(
        '/name=%s?output=export&download=true',
        util::full_urlencode( $this->name )
      );
      $parent_qnaire = util::get_data_from_parent( 'qnaire', $url_postfix );
      $readonly = $this->readonly;

      // override the readonly property while syncing with the parent instance
      if( $readonly )
      {
        $this->readonly = false;
        $this->save();
      }

      $this->process_patch( $parent_qnaire, true );

      if( $readonly && !$this->readonly )
      {
        $this->readonly = true;
        $this->save();
      }

      log::info( sprintf(
        'Questionnaire "%s" has been upgraded from version "%s" to "%s".',
        $this->name,
        $old_version,
        $new_version
      ) );
    }
  }

  /**
   * Sends ready-to-export respondent data to a parent instance of Pine
   * @param database\respondent $db_specific_respondent If provided then only that respondent will be exported
   */
  public function export_respondent_data( $db_specific_respondent = NULL )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $response_stage_pause_class_name = lib::get_class_name( 'database\response_stage_pause' );
    $setting_manager = lib::create( 'business\setting_manager' );
    if( !$setting_manager->get_setting( 'general', 'detached' ) || is_null( PARENT_INSTANCE_URL ) ) return;

    $machine_username = $setting_manager->get_setting( 'general', 'machine_username' );
    $machine_password = $setting_manager->get_setting( 'general', 'machine_password' );

    // encode all respondent and response data into an array
    $participant_data = [];
    $respondent_data = [];

    // get a list of all sub-directories (question names) in the qnaire's data directory
    $file_data = array_fill_keys(
      array_map(
        'basename',
        glob(
          sprintf( '%s/*', $this->get_data_directory() ),
          GLOB_ONLYDIR
        )
      ),
      []
    );

    // if no respondent is provided then export all completed and un-exported respondents
    $respondent_list = [];
    if( is_null( $db_specific_respondent ) )
    {
      $respondent_mod = lib::create( 'database\modifier' );
      $respondent_mod->where( 'export_datetime', '=', NULL );
      $respondent_mod->where( 'end_datetime', '!=', NULL );
      $respondent_mod->order( 'id' );
      $respondent_list = $this->get_respondent_object_list( $respondent_mod );
    }
    else
    {
      // make sure the respondent has completed the qnaire
      if( !is_null( $db_specific_respondent->end_datetime ) ) $respondent_list[] = $db_specific_respondent;
    }

    foreach( $respondent_list as $db_respondent )
    {
      $db_participant = $db_respondent->get_participant();

      // do not export anonymous respondents
      if( is_null( $db_participant ) ) continue;

      $participant = [
        'uid' => $db_participant->uid,
        'participant' => [
          'honorific' => $db_participant->honorific,
          'first_name' => $db_participant->first_name,
          'other_name' => $db_participant->other_name,
          'last_name' => $db_participant->last_name,
          'email' => $db_participant->email,

          // these may be modified by participant triggers
          'current_sex' => $db_participant->current_sex,
          'delink' => $db_participant->delink,
          'low_education' => $db_participant->low_education,
          'mass_email' => $db_participant->mass_email,
          'out_of_area' => $db_participant->out_of_area,
          'override_stratum' => $db_participant->override_stratum,
          'sex' => $db_participant->sex,
          'withdraw_third_party' => $db_participant->withdraw_third_party
        ],
        'interview' => [
          'datetime' => $db_respondent->end_datetime->format( 'c' )
        ]
      ];

      $respondent = [
        'uid' => $db_participant->uid,
        'token' => $db_respondent->token,
        'start_datetime' => $db_respondent->start_datetime->format( 'c' ),
        'end_datetime' => $db_respondent->end_datetime->format( 'c' ),
        'response_list' => []
      ];

      // add all responses belonging to the respondent
      $comment_list = [];
      $response_mod = lib::create( 'database\modifier' );
      $response_mod->order( 'rank' );
      foreach( $db_respondent->get_response_object_list( $response_mod ) as $db_response )
      {
        if( !is_null( $db_response->comments ) ) $comment_list[] = $db_response->comments;

        $db_page = $db_response->get_page();
        $db_module = is_null( $db_page ) ? NULL : $db_page->get_module();
        $response = [
          'rank' => $db_response->rank,
          'qnaire_version' => $db_response->qnaire_version,
          'language' => $db_response->get_language()->code,
          'site' => is_null( $db_response->site_id ) ? NULL : $db_response->get_site()->name,
          'module' => is_null( $db_module ) ? NULL : $db_module->name,
          'page' => is_null( $db_page ) ? NULL : $db_page->name,
          'submitted' => $db_response->submitted,
          'show_hidden' => $db_response->show_hidden,
          'start_datetime' => $db_response->start_datetime->format( 'c' ),
          'last_datetime' => $db_response->last_datetime->format( 'c' ),
          'comments' => $db_response->comments,
          'page_time_list' => [],
          'answer_list' => []
        ];

        $page_time_sel = lib::create( 'database\select' );
        $page_time_sel->add_table_column( 'module', 'name', 'module' );
        $page_time_sel->add_table_column( 'page', 'name', 'page' );
        $page_time_sel->add_column( 'datetime' );
        $page_time_sel->add_column( 'microtime' );
        $page_time_sel->add_column( 'time' );
        $page_time_mod = lib::create( 'database\modifier' );
        $page_time_mod->join( 'page', 'page_time.page_id', 'page.id' );
        $page_time_mod->join( 'module', 'page.module_id', 'module.id' );
        foreach( $db_response->get_page_time_list( $page_time_sel, $page_time_mod ) as $page_time )
        {
          $response['page_time_list'][] = [
            'module' => $page_time['module'],
            'page' => $page_time['page'],
            'datetime' => $page_time['datetime'],
            'microtime' => $page_time['microtime'],
            'time' => $page_time['time']
          ];
        }

        $answer_sel = lib::create( 'database\select' );
        $answer_sel->add_table_column( 'question', 'name', 'question' );
        $answer_sel->add_table_column( 'question', 'type' );
        $answer_sel->add_table_column( 'language', 'code', 'language' );
        $answer_sel->add_column( 'value' );
        $answer_mod = lib::create( 'database\modifier' );
        $answer_mod->join( 'question', 'answer.question_id', 'question.id' );
        $answer_mod->join( 'language', 'answer.language_id', 'language.id' );
        foreach( $db_response->get_answer_list( $answer_sel, $answer_mod ) as $answer )
        {
          $value = $answer['value'];

          // convert list question option IDs to names
          if( 'list' == $answer['type'] )
          {
            // only change array answers (the others don't have IDs to convert)
            if( '[' == substr( $value, 0, 1 ) )
            {
              $new_value = [];
              foreach( util::json_decode( $value ) as $val )
              {
                if( is_int( $val ) )
                {
                  $new_value[] = lib::create( 'database\question_option', $val )->name;
                }
                else if( is_object( $val ) )
                {
                  $val->name = lib::create( 'database\question_option', $val->id )->name;
                  unset( $val->id );
                  $new_value[] = $val;
                }
              }
              $value = util::json_encode( $new_value );
            }
          }

          $response['answer_list'][] = [
            'question' => $answer['question'],
            'language' => $answer['language'],
            'value' => $value
          ];
        }

        if( $this->stages )
        {
          // add response stage details
          $response_stage_sel = lib::create( 'database\select' );
          $response_stage_sel->add_column( 'id' );
          $response_stage_sel->add_table_column( 'stage', 'rank', 'stage' );
          $response_stage_sel->add_table_column( 'user', 'name', 'user' );
          $response_stage_sel->add_table_column( 'deviation_type', 'type', 'deviation_type' );
          $response_stage_sel->add_table_column( 'deviation_type', 'name', 'deviation_name' );
          $response_stage_sel->add_column( 'status' );
          $response_stage_sel->add_column( 'deviation_comments' );
          $response_stage_sel->add_column( 'start_datetime' );
          $response_stage_sel->add_column( 'end_datetime' );
          $response_stage_sel->add_column( 'comments' );
          $response_stage_mod = lib::create( 'database\modifier' );
          $response_stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
          $response_stage_mod->join( 'user', 'response_stage.user_id', 'user.id' );
          $response_stage_mod->left_join(
            'deviation_type',
            'response_stage.deviation_type_id',
            'deviation_type.id'
          );
          $response_stage_mod->order( 'stage.rank' );
          $response_stage_list =
            $db_response->get_response_stage_list( $response_stage_sel, $response_stage_mod );

          // add any pauses
          foreach( $response_stage_list as $index => $response_stage )
          {
            $pause_sel = lib::create( 'database\select' );
            $pause_sel->add_table_column( 'user', 'name', 'user' );
            $pause_sel->add_column( 'start_datetime' );
            $pause_sel->add_column( 'end_datetime' );
            
            $pause_mod = lib::create( 'database\modifier' );
            $pause_mod->join( 'user', 'response_stage_pause.user_id', 'user.id' );
            $pause_mod->where( 'response_stage_id', '=', $response_stage['id'] );
            $pause_mod->order( 'start_datetime' );

            $response_stage_list[$index]['pause_list'] =
              $response_stage_pause_class_name::select( $pause_sel, $pause_mod );

            // remove the response stage's id now that we don't need it anymore
            unset( $response_stage_list[$index]['id'] );
          }

          $response['stage_list'] = $response_stage_list;
        }

        $respondent['response_list'][] = $response;
      }

      if( 0 < count( $comment_list ) ) $participant['interview']['comment_list'] = $comment_list;

      $participant_data[] = $participant;
      $respondent_data[] = $respondent;

      // search for data files if needed
      foreach( $file_data as $question_name => $unused )
      {
        $file_list = glob(
          sprintf(
            '%s/%s/%s/*',
            $this->get_data_directory(),
            $question_name,
            $db_participant->uid
          )
        );
        if( 0 < count( $file_list ) )
        {
          $file_data_list = [];
          foreach( $file_list as $filename )
            $file_data_list[basename( $filename )] = base64_encode( file_get_contents( $filename ) );
          $file_data[$question_name][$db_participant->uid] = $file_data_list;
        }
      }
    }

    if( 0 < count( $respondent_data ) )
    {
      // First export the data to the master pine application
      $url = sprintf(
        '%s/api/qnaire/name=%s/respondent?action=import',
        PARENT_INSTANCE_URL,
        util::full_urlencode( $this->name )
      );
      $curl = util::get_detached_curl_object( $url );;
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt(
        $curl,
        CURLOPT_POSTFIELDS,
        util::json_encode( [
          'respondents' => $respondent_data,
          'files' => $file_data
        ] )
      );

      $response = curl_exec( $curl );
      if( curl_errno( $curl ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Got error code %s when exporting respondent data to parent instance.  Message: %s',
                   curl_errno( $curl ),
                   curl_error( $curl ) ),
          __METHOD__
        );
      }

      $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
      if( 401 == $code )
      {
        throw lib::create( 'exception\notice',
          'Unable to export respondent data to parent instance, invalid username and/or password.',
          __METHOD__
        );
      }
      else if( 404 == $code )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to export respondent data, questionnaire "%s" was not found in parent instance.',
            $this->name
          ),
          __METHOD__
        );
      }
      else if( 306 == $code )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            "Parent Pine instance responded with the following notice\n\n\"%s\"",
            util::json_decode( $response )
          ),
          __METHOD__
        );
      }
      else if( 204 == $code || 300 <= $code )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Got error code %s when exporting respondent data to parent instance.', $code ),
          __METHOD__
        );
      }
      else
      {
        // mark the respondents as exported
        foreach( $respondent_list as $db_respondent )
        {
          $db_respondent->export_datetime = util::get_datetime_object();
          $db_respondent->save();
        }
      }
    }

    $result = [];
    if( 0 < count( $participant_data ) )
    {
      // Now export the participant's details to beartooth
      $url = sprintf( '%s/api/pine', BEARTOOTH_INSTANCE_URL );
      $curl = util::get_detached_curl_object( $url );;
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, util::json_encode( $participant_data ) );

      $response = curl_exec( $curl );
      if( curl_errno( $curl ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Got error code %s when sending participant data to Beartooth.  Message: %s',
                   curl_errno( $curl ),
                   curl_error( $curl ) ),
          __METHOD__
        );
      }

      $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
      if( 401 == $code )
      {
        throw lib::create( 'exception\notice',
          'Unable to export participant data, invalid Beartooth username and/or password.',
          __METHOD__
        );
      }
      else if( 306 == $code )
      {
        throw lib::create( 'exception\notice',
          sprintf( "Beartooth responded with the following notice\n\n\"%s\"", util::json_decode($response ) ),
          __METHOD__
        );
      }
      else if( 204 == $code || 300 <= $code )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Got response code %s when getting exporting participant data to Beartooth.', $code ),
          __METHOD__
        );
      }

      // return a list of all exported UIDs
      foreach( $participant_data as $participant ) $result[] = $participant['uid'];
    }

    return $result;
  }

  /**
   * Imports response data from a CSV file, returning an associative array of the import details
   * @param string $csv_data The CSV contents (including a header and one row for each response)
   * @param boolean $apply Whether to apply or evaluate the CSV data
   * @param boolean $apply Whether to only import new responses (only used when $apply is true)
   * @return associative array
   */
  public function import_response_data_from_csv( $csv_data, $apply = false, $new_only = false )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    // a private function for testing data values
    function test_value( $type, $value )
    {
      // null values are valid for all types
      if( 'null' == $value ) return true;
      else if( 'audio' == $type ) return 'YES' == $value;
      else if( 'date' == $type ) return preg_match( '/^[0-9]{4}-[0-1][0-9]-[0-3][0-9]$/', $value );
      else if( 'device' == $type ) return true;
      else if( 'equipment' == $type ) return true;
      else if( 'list' == $type ) return true;
      else if( 'lookup' == $type ) return true;
      else if( 'number' == $type ) return util::string_matches_float( $value );
      else if( 'number with unit' == $type ) return util::string_matches_float( $value );
      else if( 'string' == $type ) return true;
      else if( 'text' == $type ) return true;
      else if( 'time' == $type ) return preg_match( '/^[01][0-9]:[0-5][0-9]$/', $value );

      return false;
    }

    // a private function to convert a json value to a number
    function convert_to_number( $value )
    {
      return util::string_matches_float( $value ) ? (float) $value : $value;
    }

    $identifier_class_name = lib::get_class_name( 'database\identifier' );
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $participant_identifier_class_name = lib::get_class_name( 'database\participant_identifier' );
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $response_class_name = lib::get_class_name( 'database\response' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $language_class_name = lib::get_class_name( 'database\language' );
    $site_class_name = lib::get_class_name( 'database\site' );

    // get a list of all questions in this array (without qnaire name prefix)
    $question_list = $this->get_output_column_list();
    $valid_column_list = NULL;
    $invalid_column_list = NULL;
    $missing_column_list = NULL;
    $new_responses = 0;
    $existing_responses = 0;
    $row_errors = [];
    $column_errors = [];
    $db_identifier = NULL;
    $metadata_columns = [];
    $column_name_list = NULL;

    foreach( preg_split( '/\r\n|\n|\r/', $csv_data ) as $row_index => $line )
    {
      $row = str_getcsv( $line );
      if( 2 > count( $row ) ) continue;
      if( is_null( $column_name_list ) )
      {
        // the first column MUST be an identifier
        $identifier_name = array_shift( $row );

        // make sure first column is either a UID or an identifier
        if( 'uid' != $identifier_name )
        {
          $db_identifier = $identifier_class_name::get_unique_record( 'name', $identifier_name );
          if( is_null( $db_identifier ) )
          {
            throw lib::create( 'exception\notice',
              sprintf(
                'The first column name, "%s", must either be uid or the name of an identifier.',
                $identifier_name
              ),
              __METHOD__
            );
          }
        }

        // build the column name list while making note whether certain metadata columns are found
        $column_name_list = [];
        foreach( $row as $index => $column_name )
        {
          if( 'rank' == $column_name ) $metadata_columns['rank'] = $index;
          else if( 'qnaire_version' == $column_name ) $metadata_columns['qnaire_version'] = $index;
          else if( 'submitted' == $column_name ) $metadata_columns['submitted'] = $index;
          else if( 'language' == $column_name ) $metadata_columns['language'] = $index;
          else if( 'site' == $column_name ) $metadata_columns['site'] = $index;
          else if( 'start_datetime' == $column_name ) $metadata_columns['start_datetime'] = $index;
          else if( 'last_datetime' == $column_name ) $metadata_columns['last_datetime'] = $index;
          $column_name_list[] = $column_name;
        }

        // determine valid, invalid and missing columns based on the CSV header
        $valid_column_list = array_values( array_intersect(
          $column_name_list,
          array_keys( $question_list )
        ) );
        $invalid_column_list = array_values( array_diff(
          $column_name_list,
          array_merge( array_keys( $metadata_columns ), array_keys( $question_list ) )
        ) );
        $missing_column_list = array_values( array_diff(
          array_keys( $question_list ),
          $column_name_list
        ) );
        continue;
      }

      // convert the identifier into a participant
      $db_participant = NULL;
      $db_respondent = NULL;
      $db_response = NULL;
      $identifier = array_shift( $row );

      // determine metadata (including certain default values)
      $metadata = [ 'rank' => 1, 'submitted' => 1 ];
      foreach( $metadata_columns as $metadata_column_name => $metadata_column_index )
        $metadata[$metadata_column_name] = $row[$metadata_column_index];

      $existing = false;
      if( !$identifier )
      {
        // the participant record must be found for non-anonymous qnaires
        if( !$this->anonymous )
        {
          $row_errors[] = sprintf( 'Line %d: missing identifier', $row_index+1 );
          continue;
        }
      }
      else
      {
        if( is_null( $db_identifier ) )
        {
          $db_participant = $participant_class_name::get_unique_record( 'uid', $identifier );
        }
        else
        {
          $db_participant_identifier = $participant_identifier_class_name::get_unique_record(
            ['identifier_id', 'value'],
            [$db_identifier->id, $identifier]
          );
          if( !is_null( $db_participant_identifier ) )
            $db_participant = $db_participant_identifier->get_participant();
        }

        if( is_null( $db_participant ) )
        {
          $row_errors[] = sprintf( 'Line %d: identifier "%s" not found', $row_index+1, $identifier );
          continue;
        }
        else
        {
          // the respondent may or may not already exist
          $db_respondent = $respondent_class_name::get_unique_record(
            ['qnaire_id', 'participant_id'],
            [$this->id, $db_participant->id]
          );

          // the response may or may not already exist
          if( !is_null( $db_respondent ) )
          {
            $db_response = $response_class_name::get_unique_record(
              ['respondent_id', 'rank'],
              [$db_respondent->id, $metadata['rank']]
            );

            if( !is_null( $db_response ) ) $existing = true;
          }
        }
      }

      if( $existing ) $existing_responses++;
      else $new_responses++;

      // whether or not we apply this row depends on if this is an existing response
      $apply_this_row = $apply && !($existing && $new_only);

      if( $apply_this_row )
      {
        if( is_null( $db_respondent ) )
        {
          $db_respondent = lib::create( 'database\respondent' );
          $db_respondent->qnaire_id = $this->id;
          if( !is_null( $db_participant ) ) $db_respondent->participant_id = $db_participant->id;
        }

        if( is_null( $db_respondent->start_datetime ) )
          $db_respondent->start_datetime = array_key_exists( 'start_datetime', $metadata ) ?
            $metadata['start_datetime'] : util::get_datetime_object();

        if( $metadata['submitted'] )
        {
          // set the end datetime if the qnaire isn't repeated or this is the last response
          if( !$this->repeated || $metadata['rank'] == $this->max_responses )
            $db_respondent->end_datetime = array_key_exists( 'last_datetime', $metadata ) ?
              $metadata['last_datetime'] : util::get_datetime_object();
        }
        $db_respondent->save();

        if( is_null( $db_response ) )
        {
          $db_response = lib::create( 'database\response' );
          $db_response->respondent_id = $db_respondent->id;
          $db_response->rank = $metadata['rank'];
          $db_response->qnaire_version = $this->version;
        }

        $db_response->language_id = $this->base_language_id;
        if( array_key_exists( 'language', $metadata ) )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $metadata['language'] );
          if( is_null( $db_language ) )
            $db_language = $language_class_name::get_unique_record( 'name', $metadata['language'] );
          if( !is_null( $db_language ) ) $db_response->language_id = $db_language->id;
        }

        $db_response->site_id = NULL;
        if( array_key_exists( 'site', $metadata ) )
        {
          $db_site = $site_class_name::get_unique_record( 'name', $metadata['site'] );
          if( !is_null( $db_site ) ) $db_response->site_id = $db_site->id;
        }

        $db_response->submitted = $metadata['submitted'];
        $db_response->comments = 'Imported from CSV';

        if( array_key_exists( 'start_datetime', $metadata ) )
          $db_response->start_datetime = $metadata['start_datetime'];
        else if( is_null( $db_response->start_datetime ) )
          $db_response->start_datetime = util::get_datetime_object();

        if( array_key_exists( 'last_datetime', $metadata ) )
          $db_response->last_datetime = $metadata['last_datetime'];
        $db_response->save();
      }

      foreach( $question_list as $column_name => $question )
      {
        $col_index = array_search( $column_name, $column_name_list );
        if( false === $col_index ) continue; // ignore any columns that aren't in the import data
        $value = $row[$col_index];
        if( is_string( $value ) && 0 == strlen( $value ) ) continue; // ignore emptry string values

        // when applying the data makes sure the answer record exists
        if( $apply_this_row )
        {
          $db_answer = is_null( $db_response ) ? NULL : $answer_class_name::get_unique_record(
            ['response_id', 'question_id'],
            [$db_response->id, $question['question_id']]
          );
          if( is_null( $db_answer ) )
          {
            $db_answer = lib::create( 'database\answer' );
            $db_answer->response_id = $db_response->id;
            $db_answer->question_id = $question['question_id'];
            $db_answer->language_id = $this->base_language_id;
            $db_answer->value = 'null';
            $db_answer->save();
          }
        }

        // the invalid error is the same in all situations
        $invalid = false;

        // handle special columns differently
        if( array_key_exists( 'missing_list', $question ) ) // missing column
        {
          // the value must be in the missing list
          $missing = NULL;
          foreach( $question['missing_list'] as $m => $not_used )
          {
            if( $value == $m ) { $missing = $m; break; }
          }

          if( is_null( $missing ) ) $invalid = true;
          else if( $apply_this_row )
          {
            if( in_array( $missing, ['DK_NA', 'REFUSED'] ) )
            {
              if( 'DK_NA' == $missing ) $db_answer->set_dkna();
              else $db_answer->set_refuse();
              $db_answer->save();
            }
          }
        }
        else if( array_key_exists( 'unit_list', $question ) ) // unit column
        {
          // the value must be in the unit list
          $unit = NULL;
          $unit_list = $this->get_unit_list_enum( $question['unit_list'] );

          // the enum list will be divided into languages, so search the first one's key=>value unit list
          foreach( current( $unit_list ) as $k => $v )
          {
            if( $value == $k ) { $unit = $value; break; }
          }

          if( is_null( $unit ) ) $invalid = true;
          else if( $apply_this_row )
          {
            // add the unit to the value property
            $new_value = 'null';
            if( array_key_exists( 'option_id', $question ) )
            {
              // this unit belongs to extra data in a "list" question
              $new_value = util::json_decode( $db_answer->value );
              if( !is_array( $new_value ) )
              {
                throw lib::create( 'exception\notice',
                  sprintf(
                    'Can\'t set unit value, value for answer to %s on row %d is not an array!',
                    $db_answer->get_question()->name,
                    $row_index + 1
                  ),
                  __METHOD__
                );
              }

              foreach( $new_value as $i => $v )
              {
                if( is_object( $v ) && $v->id == $question['option_id'] )
                {
                  $new_value[$i]->value->unit = $unit;
                  break;
                }
              }
            }
            else
            {
              $temp_value = util::json_decode( $db_answer->value );
              if( is_object( $temp_value ) )
              {
                throw lib::create( 'exception\notice',
                  sprintf(
                    'Can\'t set unit value, value for answer to %s on row %d is set to DK_NA or MISSING!',
                    $db_answer->get_question()->name,
                    $row_index + 1
                  ),
                  __METHOD__
                );
              }

              // this unit belongs to a "number with unit" question
              $new_value = (object) [
                'value' => convert_to_number( $temp_value ),
                'unit' => $unit
              ];
            }

            $db_answer->value = util::json_encode( $new_value );
            $db_answer->save();
          }
        }
        else if( // dkna or refuse column
          array_key_exists( 'option_id', $question ) &&
          in_array( $question['option_id'], ['dkna', 'refuse'] )
        ) {
          if( !in_array( $value, ['0', '1'] ) ) $invalid = true;
          else if( $apply_this_row )
          {
            // NOTE: allow the main column to define the value if this column has a value of 0
            if( '1' == $value )
            {
              $db_answer->value = util::json_encode( (object) [$question['option_id'] => true] );
              $db_answer->save();
            }
          }
        }
        else if( // multi-select list column
          array_key_exists( 'option_id', $question ) &&
          !$question['all_exclusive']
        ) {
          // if there is extra data then we must test for that type
          if( array_key_exists( 'extra', $question ) && $question['extra'] )
          {
            if( !test_value( $question['extra'], $value ) ) $invalid = true;
            else if( $apply_this_row )
            {
              $a = util::json_decode( $db_answer->value );
              if( is_null( $a ) ) $a = [];
              $v_index = NULL;
              foreach( $a as $i => $v )
              {
                if( is_object( $v ) && $question['option_id'] == $v->id ) { $v_index = $i; break; }
              }

              $new_value = (object) ['id' => $question['option_id'], 'value' => $value];
              if( is_null( $v_index ) ) $a[] = $new_value;
              else $a[$v_index] = $new_value;
              $db_answer->value = util::json_encode( $a );
              $db_answer->save();
            }
          }
          else
          {
            if( !in_array( $value, ['0', '1'] ) ) $invalid = true;
            else if( $apply_this_row )
            {
              if( '1' == $value )
              {
                $a = util::json_decode( $db_answer->value );
                if( is_null( $a ) ) $a = [];
                if( !in_array( $question['option_id'], $a ) ) $a[] = $question['option_id'];
                $db_answer->value = util::json_encode( $a );
                $db_answer->save();
              }
            }
          }
        }
        else if( 'list' == $question['type'] )
        {
          // the value must be in the option list
          $option = NULL;
          if( array_key_exists( 'option_list', $question ) )
          {
            foreach( $question['option_list'] as $o )
            {
              if( $value == $o['name'] ) { $option = $o; break; }
            }
          }
          else if( array_key_exists( 'option_id', $question ) )
          {
            // some extra columns (such as number-with-unit "NB" columns) have no option-list
            // but they do have an ID
            $option = ['id' => $question['option_id']];
          }

          if( is_null( $option ) ) $invalid = true;
          else
          {
            if( array_key_exists( 'extra', $question ) && $question['extra'] ) // extra data for a selected option
            {
              if( !test_value( $question['extra'], $value ) ) $invalid = true;
              else if( $apply_this_row )
              {
                $new_value = util::json_decode( $db_answer->value );
                if( !is_array( $new_value ) ) $new_value = [];

                $obj = [
                  'id' => $question['option_id'],
                  'value' => 'number with unit' == $question['extra'] ?
                    (object) ['value' => convert_to_number( $value ), 'unit' => NULL] :
                    $value
                ];
                // look for this id as an integer in the array and replace it with an object

                $found = false;
                foreach( $new_value as $i => $v )
                {
                  if( is_int( $v ) && $v == $question['option_id'] )
                  {
                    $new_value[$i] = (array) $obj;
                    $found = true;
                    break;
                  }
                }

                if( !$found )
                {
                  $new_value[] = [
                    'id' => $question['option_id'],
                    'value' => 'number with unit' == $question['extra'] ?
                      (object) ['value' => convert_to_number( $value ), 'unit' => NULL] :
                      $value
                  ];
                }
                $db_answer->value = util::json_encode( $new_value );
                $db_answer->save();
              }
            }
            else // no extra data
            {
              if( $apply_this_row )
              {
                if( 'DK_NA' == $option['name'] ) $db_answer->set_dkna();
                else if( 'REFUSED' == $option['name'] ) $db_answer->set_refuse();
                else
                {
                  $new_value = util::json_decode( $db_answer->value );
                  if( !is_array( $new_value ) || $question['all_exclusive'] ) $new_value = [];
                  $new_value[] = $option['id'];
                  $db_answer->value = util::json_encode( $new_value );
                }
                $db_answer->save();
              }
            }
          }
        }
        else if( 'boolean' == $question['type'] )
        {
          // the value must be in the boolean list
          $option = NULL;
          if( array_key_exists( 'boolean_list', $question ) )
          {
            foreach( $question['boolean_list'] as $o )
            {
              if( $value == $o['name'] ) { $option = $o; break; }
            }
          }

          if( is_null( $option ) ) $invalid = true;
          else if( $apply_this_row )
          {
            if( 'DK_NA' == $option['name'] ) $db_answer->set_dkna();
            else if( 'REFUSED' == $option['name'] ) $db_answer->set_refuse();
            else $db_answer->value = util::json_encode( 'YES' == $value );
            $db_answer->save();
          }
        }
        else
        {
          if( !test_value( $question['type'], $value ) ) $invalid = true;
          else if( $apply_this_row )
          {
            $formatted_value = $value;
            if( 'number' == $question['type'] ) $formatted_value = convert_to_number( $value );
            $db_answer->value = util::json_encode( $formatted_value );
            $db_answer->save();
          }
        }

        if( $invalid )
        {
          // keep up to 11 errors per column
          if( !array_key_exists( $column_name, $column_errors ) ) $column_errors[$column_name] = [];
          if( 10 >= count( $column_errors[$column_name] ) )
            $column_errors[$column_name][] = sprintf( 'Line %d has invalid value "%s"', $row_index+1, $value );
        }
      }
    }

    // convert the column errors associative array to a non-associative array
    $column_error_list = [];
    foreach( $column_errors as $column_name => $row_list )
    {
      $column_error_list[] = [
        'column' => $column_name,
        'rows' => $row_list
      ];
    }

    return [
      'valid_column_list' => $valid_column_list,
      'invalid_column_list' => $invalid_column_list,
      'missing_column_list' => $missing_column_list,
      'new_responses' => $new_responses,
      'existing_responses' => $existing_responses,
      'row_errors' => $row_errors,
      'column_errors' => $column_error_list
    ];
  }

  /**
   * Imports response data from a child instance of Pine
   * @param array $respondent_list Respondent questionnaire data
   * @param array $file_list File data
   */
  public function import_response_data( $respondent_list, $file_list )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $participant_class_name = lib::get_class_name( 'database\participant' );
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $response_class_name = lib::get_class_name( 'database\response' );
    $language_class_name = lib::get_class_name( 'database\language' );
    $site_class_name = lib::get_class_name( 'database\site' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $page_class_name = lib::get_class_name( 'database\page' );
    $page_time_class_name = lib::get_class_name( 'database\page_time' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );
    $stage_class_name = lib::get_class_name( 'database\stage' );
    $user_class_name = lib::get_class_name( 'database\user' );
    $response_stage_class_name = lib::get_class_name( 'database\response_stage' );
    $deviation_type_class_name = lib::get_class_name( 'database\deviation_type' );
    $db_current_user = lib::create( 'business\session' )->get_user();

    // first import the respondent data
    foreach( $respondent_list as $respondent )
    {
      $db_participant = $participant_class_name::get_unique_record( 'uid', $respondent->uid );
      if( !is_null( $db_participant ) )
      {
        $db_respondent = $respondent_class_name::get_unique_record(
          ['qnaire_id', 'participant_id'],
          [$this->id, $db_participant->id]
        );

        $new_respondent = false;
        if( is_null( $db_respondent ) )
        {
          $new_respondent = true;
          $db_respondent = lib::create( 'database\respondent' );
          $db_respondent->qnaire_id = $this->id;
          $db_respondent->participant_id = $db_participant->id;
          $db_respondent->save();
        }

        $db_respondent->start_datetime = $respondent->start_datetime;
        $db_respondent->end_datetime = $respondent->end_datetime;
        $db_respondent->token = $respondent->token;
        $db_respondent->save();

        foreach( $respondent->response_list as $response )
        {
          $db_response = NULL;
          if( !$new_respondent )
          { // only bother to check for an existing response if the respondent isn't new
            $db_response = $response_class_name::get_unique_record(
              ['respondent_id', 'rank'],
              [$db_respondent->id, $response->rank]
            );
          }

          $new_response = false;
          if( is_null( $db_response ) )
          {
            $new_response = true;
            $db_response = lib::create( 'database\response' );
            $db_response->respondent_id = $db_respondent->id;
            $db_response->rank = $response->rank;
            $db_response->qnaire_version = $response->qnaire_version;
          }

          $db_module = $module_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $response->module]
          );
          $db_page = is_null( $db_module )
                   ? NULL
                   : $page_class_name::get_unique_record(
                       ['module_id', 'name'],
                       [$db_module->id, $response->page]
                     );

          $db_response->language_id = $language_class_name::get_unique_record( 'code', $response->language )->id;
          
          // the site may be NULL, so ignore it if it is
          if( !is_null( $response->site ) )
          {
            // make sure the site exists and warn if it doesn't
            $db_site = $site_class_name::get_unique_record( 'name', $response->site );
            if( is_null( $db_site ) )
            {
              log::warning( sprintf(
                'Invalid site name "%s" found while importing response data.',
                $response->site
              ) );
            }
            else
            {
              $db_response->site_id = $db_site->id;
            }
          }

          if( !is_null( $db_page ) ) $db_response->page_id = $db_page->id;
          $db_response->show_hidden = $response->show_hidden;
          $db_response->start_datetime = $response->start_datetime;
          $db_response->last_datetime = $response->last_datetime;
          $db_response->comments = $response->comments;
          $db_response->save();

          foreach( $response->page_time_list as $page_time )
          {
            $db_pt_module = $module_class_name::get_unique_record(
              ['qnaire_id', 'name'],
              [$this->id, $page_time->module]
            );
            $db_pt_page = $page_class_name::get_unique_record(
              ['module_id', 'name'],
              [$db_pt_module->id, $page_time->page]
            );

            $db_page_time = NULL;
            if( !$new_response )
            { // only bother to check for an existing page_time if the response isn't new
              $db_page_time = $page_time_class_name::get_unique_record(
                ['response_id', 'page_id'],
                [$db_response->id, $db_pt_page->id]
              );
            }

            if( is_null( $db_page_time ) )
            {
              $db_page_time = lib::create( 'database\page_time' );
              $db_page_time->response_id = $db_response->id;
              $db_page_time->page_id = $db_pt_page->id;
            }

            $db_page_time->datetime = $page_time->datetime;
            $db_page_time->microtime = $page_time->microtime;
            $db_page_time->time = $page_time->time;
            $db_page_time->save();
          }

          foreach( $response->answer_list as $answer )
          {
            $db_question = $this->get_question( $answer->question );
            $db_answer = NULL;
            if( !$new_response )
            { // only bother to check for an existing answer if the response isn't new
              $db_answer = $answer_class_name::get_unique_record(
                ['response_id', 'question_id'],
                [$db_response->id, $db_question->id]
              );
            }

            if( is_null( $db_answer ) )
            {
              $db_answer = lib::create( 'database\answer' );
              $db_answer->response_id = $db_response->id;
              $db_answer->question_id = $db_question->id;
            }

            // list answers will have options encoded as names, so convert to IDs
            $value = $answer->value;
            if( 'list' == $db_question->type )
            {
              // only change array answers (the others don't have Names to convert)
              if( '[' == substr( $value, 0, 1 ) )
              {
                $new_value = [];
                foreach( util::json_decode( $value ) as $val )
                {
                  if( is_string( $val ) )
                  {
                    $new_value[] = $question_option_class_name::get_unique_record(
                      ['question_id', 'name'],
                      [$db_question->id, $val]
                    )->id;
                  }
                  else if( is_object( $val ) )
                  {
                    $val->id = $question_option_class_name::get_unique_record(
                      ['question_id', 'name'],
                      [$db_question->id, $val->name]
                    )->id;
                    unset( $val->name );
                    $new_value[] = $val;
                  }
                }
                $value = util::json_encode( $new_value );
              }
            }

            $db_answer->user_id = $db_current_user->id;
            $db_answer->language_id = $language_class_name::get_unique_record( 'code', $answer->language )->id;
            $db_answer->value = $value;
            $db_answer->save();
          }

          if( $this->stages )
          {
            foreach( $response->stage_list as $stage )
            {
              $db_stage = $stage_class_name::get_unique_record(
                ['qnaire_id', 'rank'],
                [$this->id, $stage->stage]
              );

              $db_response_stage = $response_stage_class_name::get_unique_record(
                ['response_id', 'stage_id'],
                [$db_response->id, $db_stage->id]
              );

              $db_user = $user_class_name::get_unique_record( 'name', $stage->user );
              if( is_null( $db_user ) ) $db_user = $db_current_user;

              $db_deviation_type = NULL;
              if( !is_null( $stage->deviation_type ) )
              {
                $db_deviation_type = $deviation_type_class_name::get_unique_record(
                  ['qnaire_id', 'type', 'name'],
                  [$this->id, $stage->deviation_type, $stage->deviation_name]
                );
              }

              $db_response_stage->user_id = $db_user->id;
              $db_response_stage->status = $stage->status;
              $db_response_stage->deviation_type_id = is_null( $db_deviation_type )
                                                    ? NULL
                                                    : $db_deviation_type->id;
              $db_response_stage->deviation_comments = $stage->deviation_comments;
              $db_response_stage->start_datetime = $stage->start_datetime;
              $db_response_stage->end_datetime = $stage->end_datetime;
              $db_response_stage->comments = $stage->comments;
              $db_response_stage->save();

              // replace all pauses
              $db_response_stage->remove_response_stage_pause( NULL );
              foreach( $stage->pause_list as $pause )
              {
                $db_user = $user_class_name::get_unique_record( 'name', $pause->user );
                if( is_null( $db_user ) ) $db_user = $db_current_user;

                $db_response_stage_pause = lib::create( 'database\response_stage_pause' );
                $db_response_stage_pause->response_stage_id = $db_response_stage->id;
                $db_response_stage_pause->user_id = $db_user->id;
                $db_response_stage_pause->start_datetime = $pause->start_datetime;
                $db_response_stage_pause->end_datetime = $pause->end_datetime;
                $db_response_stage_pause->save();
              }
            }
          }

          // If the importing response is submitted do so now and safe the response again.
          // This is necessary in order to get all triggers from the imported response to fire.
          if( $response->submitted )
          {
            $db_response->submitted = $response->submitted;
            $db_response->save();
          }
        }
      }
    }

    // now import the file data
    foreach( $file_list as $question_name => $participant )
    {
      $db_question = $this->get_question( $question_name );
      if( is_null( $db_question ) )
      {
        log::warning( sprintf(
          'Tried to import file data for question "%s" which doesn\'t exist.',
          $question_name
        ) );
        continue;
      }

      $base_dir = $db_question->get_data_directory();
      foreach( $participant as $uid => $file_list )
      {
        $directory = sprintf( '%s/%s', $base_dir, $uid );
        if( !file_exists( $directory ) ) mkdir( $directory, 0755, true );
        foreach( $file_list as $filename => $base64_file )
        {
          file_put_contents(
            sprintf( '%s/%s', $directory, $filename ),
            base64_decode( $base64_file )
          );
        }
      }
    }
  }

  /**
   * Deletes all respondents which have been exported for longer than the purge delay
   * (Note: this does nothing if not in detached mode)
   */
  public function delete_purged_respondents()
  {
    $setting_manager = lib::create( 'business\setting_manager' );
    if( !$setting_manager->get_setting( 'general', 'detached' ) || is_null( PARENT_INSTANCE_URL ) )
    {
      log::warning( 'Tried to purge respondents from an undetached instance of Pine.' );
      return;
    }

    $respondent_mod = lib::create( 'database\modifier' );
    $respondent_mod->where(
      sprintf( 'export_datetime + INTERVAL %d DAY', $setting_manager->get_setting( 'general', 'purge_delay' ) ),
      '<=',
      'UTC_TIMESTAMP()',
      false
    );
    foreach( $this->get_respondent_object_list( $respondent_mod ) as $db_respondent )
    {
      log::info( sprintf(
        'Purged respondent %s from questionnaire "%s"',
        $db_respondent->get_participant()->uid,
        $this->name
      ) );
      $db_respondent->delete();
    }
  }

  /**
   * Imports a list of respondents to the qnaire from Beartooth
   */
  public function get_respondents_from_beartooth()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $cohort_class_name = lib::get_class_name( 'database\cohort' );
    $language_class_name = lib::get_class_name( 'database\language' );
    $region_class_name = lib::get_class_name( 'database\region' );
    $study_class_name = lib::get_class_name( 'database\study' );
    $identifier_class_name = lib::get_class_name( 'database\identifier' );
    $collection_class_name = lib::get_class_name( 'database\collection' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $event_type_class_name = lib::get_class_name( 'database\event_type' );

    $setting_manager = lib::create( 'business\setting_manager' );
    if( !$setting_manager->get_setting( 'general', 'detached' ) || is_null( PARENT_INSTANCE_URL ) ) return;

    $machine_username = $setting_manager->get_setting( 'general', 'machine_username' );
    $machine_password = $setting_manager->get_setting( 'general', 'machine_password' );

    if( is_null( BEARTOOTH_INSTANCE_URL ) || is_null( $machine_username ) || is_null( $machine_password ) )
    {
      throw lib::create( 'expression\runtime',
        'Tried to get respondents from Beartooth without a URL, username and password.',
        __METHOD__
      );
    }

    $url = sprintf(
      '%s/api/appointment%s',
      BEARTOOTH_INSTANCE_URL,
      is_null( $this->appointment_type ) ? '' : sprintf( '?type=%s', $this->appointment_type )
    );
    $curl = util::get_detached_curl_object( $url );;

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when getting appointment list from Beartooth.  Message: %s',
                 curl_errno( $curl ),
                 curl_error( $curl ) ),
        __METHOD__
      );
    }

    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 401 == $code )
    {
      throw lib::create( 'exception\notice',
        'Unable to get appointment list, invalid Beartooth username and/or password.',
        __METHOD__
      );
    }
    else if( 306 == $code )
    {
      throw lib::create( 'exception\notice',
        sprintf( "Beartooth responded with the following notice\n\n\"%s\"", util::json_decode($response ) ),
        __METHOD__
      );
    }
    else if( 204 == $code || 300 <= $code )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got response code %s when getting appointment list from Beartooth.', $code ),
        __METHOD__
      );
    }

    // Now that we know we have successfully pulled a list of appointments, start by deleting
    // all respondents that haven't been checked in yet (avoiding those that have started stage selection
    // but have gone back to the check-in stage)
    $respondent_mod = lib::create( 'database\modifier' );
    $respondent_mod->join(
      'respondent_current_response',
      'respondent.id',
      'respondent_current_response.respondent_id'
    );
    $respondent_mod->left_join( 'response', 'respondent_current_response.response_id', 'response.id' );
    $respondent_mod->where( 'IFNULL( response.stage_selection, false )', '=', false );
    $respondent_mod->where( 'IFNULL( response.submitted, false )', '=', false );
    $respondent_mod->where( 'IFNULL( response.checked_in, false )', '=', false );

    foreach( $this->get_respondent_object_list( $respondent_mod ) as $db_respondent )
      $db_respondent->delete();

    // now load all data provided by the response from beartooth
    $data = ['success' => [], 'fail' => []];
    foreach( util::json_decode( $response ) as $participant )
    {
      // update the participant record
      $db_participant = $participant_class_name::get_unique_record( 'uid', $participant->uid );
      if( is_null( $db_participant ) )
      {
        $db_participant = lib::create( 'database\participant' );
        $db_participant->uid = $participant->uid;
      }

      $db_participant->cohort_id = $cohort_class_name::get_unique_record( 'name', $participant->cohort )->id;
      $db_participant->language_id =
        $language_class_name::get_unique_record( 'code', $participant->language )->id;
      $db_participant->honorific = $participant->honorific;
      $db_participant->first_name = $participant->first_name;
      $db_participant->other_name = $participant->other_name;
      $db_participant->last_name = $participant->last_name;
      $db_participant->email = $participant->email;
      if( $participant->date_of_birth ) $db_participant->date_of_birth = $participant->date_of_birth;

      // these may be modified by participant triggers
      $db_participant->current_sex = $participant->current_sex;
      $db_participant->delink = $participant->delink;
      $db_participant->low_education = $participant->low_education;
      $db_participant->mass_email = $participant->mass_email;
      $db_participant->out_of_area = $participant->out_of_area;
      $db_participant->override_stratum = $participant->override_stratum;
      $db_participant->sex = $participant->sex;
      $db_participant->withdraw_third_party = $participant->withdraw_third_party;
      $db_participant->save();

      // update the address record
      $db_address = $db_participant->get_primary_address();
      if( is_null( $db_address ) )
      {
        $db_address = lib::create( 'database\address' );
        $db_address->participant_id = $db_participant->id;
        $db_address->rank = 1;
      }

      $db_address->address1 = $participant->address1;
      $db_address->address2 = $participant->address2;
      $db_address->city = $participant->city;
      $db_address->region_id = $region_class_name::get_unique_record( 'name', $participant->region )->id;
      $db_address->postcode = $participant->postcode;
      $db_address->save();

      // replace all eligible studies with the provided list
      $study_name_list = $participant->study_list ? explode( ';', $participant->study_list ) : [];

      $study_mod = lib::create( 'database\modifier' );
      $study_mod->where( 'participant_id', '=', $db_participant->id );
      static::db()->execute( sprintf( 'DELETE FROM study_has_participant %s', $study_mod->get_sql() ) );

      if( 0 < count( $study_name_list ) )
      {
        $study_sel = lib::create( 'database\select' );
        $study_sel->from( 'study' );
        $study_sel->add_column( 'study.id', 'study_id', false );
        $study_sel->add_constant( $db_participant->id, 'participant_id' );

        $study_mod = lib::create( 'database\modifier' );
        $study_mod->where( 'study.name', 'IN', $study_name_list );

        static::db()->execute( sprintf(
          'INSERT IGNORE INTO study_has_participant( study_id, participant_id ) '.
          '%s %s',
          $study_sel->get_sql(),
          $study_mod->get_sql()
        ) );
      }

      // replace all collections with the provided list
      $collection_name_list = $participant->collection_list ? explode( ';', $participant->collection_list ) : [];

      $collection_mod = lib::create( 'database\modifier' );
      $collection_mod->where( 'participant_id', '=', $db_participant->id );
      static::db()->execute( sprintf(
        'DELETE FROM collection_has_participant %s',
        $collection_mod->get_sql()
      ) );

      if( 0 < count( $collection_name_list ) )
      {
        $collection_sel = lib::create( 'database\select' );
        $collection_sel->from( 'collection' );
        $collection_sel->add_column( 'collection.id', 'collection_id', false );
        $collection_sel->add_constant( $db_participant->id, 'participant_id' );

        $collection_mod = lib::create( 'database\modifier' );
        $collection_mod->where( 'collection.name', 'IN', $collection_name_list );

        static::db()->execute( sprintf(
          'INSERT IGNORE INTO collection_has_participant( collection_id, participant_id ) '.
          '%s %s',
          $collection_sel->get_sql(),
          $collection_mod->get_sql()
        ) );
      }

      // create participant_identifier records
      $participant_identifier_list = $participant->participant_identifier_list
                                   ? explode( ';', $participant->participant_identifier_list )
                                   : [];

      $identifier_mod = lib::create( 'database\modifier' );
      $identifier_mod->where( 'participant_id', '=', $db_participant->id );
      static::db()->execute( sprintf(
        'DELETE FROM participant_identifier %s',
        $identifier_mod->get_sql()
      ) );

      foreach( $participant_identifier_list as $participant_identifier_entry )
      {
        // entries have the format: identifier_name$value, convert to an associative array
        $participant_identifier_data = explode( '$', $participant_identifier_entry );
        if( 2 != count( $participant_identifier_data ) ) continue;

        $identifier = [
          'name' => $participant_identifier_data[0],
          'value' => $participant_identifier_data[1]
        ];
        $db_identifier = $identifier_class_name::get_unique_record( 'name', $identifier['name'] );

        $db_participant_identifier = lib::create( 'database\participant_identifier' );
        $db_participant_identifier->participant_id = $db_participant->id;
        $db_participant_identifier->identifier_id = $db_identifier->id;
        $db_participant_identifier->value = $identifier['value'];
        $db_participant_identifier->save();
      }

      // create consent records (NOTE: importing alternate consent has not been implemented)
      // NOTE: We can't delete and re-create them all because of database triggers!
      $new_consent_list = [];
      foreach( explode( ';', $participant->consent_list ) as $consent_entry )
      {
        if( 0 == strlen( $consent_entry ) ) continue;

        // entries have the format: consent_type_name$accept$datetime, convert to an associative array
        $consent_data = explode( '$', $consent_entry );
        if( 3 != count( $consent_data ) ) continue;

        $new_consent_list[] = [
          'consent_type' => $consent_type_class_name::get_unique_record( 'name', $consent['consent_type'] ),
          'accept' => $consent_data[1],
          'datetime' => $consent_data[2],
          'record' => NULL
        ];
      }

      // delete all consent records that aren't in the list
      foreach( $db_participant->get_consent_object_list() as $db_consent )
      {
        $found = false;
        foreach( $new_consent_list as $consent )
        {
          if(
            $consent['consent_type']->id == $db_consent->get_consent_type()->name &&
            $consent['datetime'] = $db_consent->datetime
          )
          {
            $consent['record'] = $db_consent;
            $found = true;
            break;
          }
        }

        if( !$found ) $db_consent->delete();
      }

      // now add all new consent records
      foreach( $new_consent_list as $consent )
      {
        if( !is_null( $consent['record'] ) )
        { // the consent record already exists, just make sure its accept value is correct
          $consent['record']->accept = $consent['accept'];
          $consent['record']->save();
        }
        else // there is no consent record for that datetime, so create one
        {
          $db_consent = lib::create( 'database\consent' );
          $db_consent->participant_id = $db_participant->id;
          $db_consent->consent_type_id = $db_consent_type->id;
          $db_consent->accept = $consent['accept'];
          $db_consent->datetime = $consent['datetime'];
          $db_consent->save();
        }
      }

      // create event records (NOTE: importing alternate event has not been implemented)
      // NOTE: We can't delete and re-create them all because of database triggers!
      $new_event_list = [];
      foreach( explode( ';', $participant->event_list ) as $event_entry )
      {
        if( 0 == strlen( $event_entry ) ) continue;

        // entries have the format: event_type_name$accept$datetime, convert to an associative array
        $event_data = explode( '$', $event_entry );
        if( 3 != count( $event_data ) ) continue;

        $new_event_list[] = [
          'event_type' => $event_type_class_name::get_unique_record( 'name', $event['event_type'] ),
          'accept' => $event_data[1],
          'datetime' => $event_data[2],
          'record' => NULL
        ];
      }

      // delete all event records that aren't in the list
      foreach( $db_participant->get_event_object_list() as $db_event )
      {
        $found = false;
        foreach( $new_event_list as $event )
        {
          if(
            $event['event_type']->id == $db_event->get_event_type()->name &&
            $event['datetime'] = $db_event->datetime
          )
          {
            $event['record'] = $db_event;
            $found = true;
            break;
          }
        }

        if( !$found ) $db_event->delete();
      }

      // now add all new event records
      foreach( $new_event_list as $event )
      {
        // add the event record if it doesn't already exist
        if( is_null( $event['record'] ) )
        {
          $db_event = lib::create( 'database\event' );
          $db_event->participant_id = $db_participant->id;
          $db_event->event_type_id = $db_event_type->id;
          $db_event->datetime = $event['datetime'];
          $db_event->save();
        }
      }

      // add the participant's respondent file
      $db_respondent = $respondent_class_name::get_unique_record(
        ['qnaire_id', 'participant_id'],
        [$this->id, $db_participant->id]
      );

      if( is_null( $db_respondent ) )
      {
        $db_respondent = lib::create( 'database\respondent' );
        $db_respondent->qnaire_id = $this->id;
        $db_respondent->participant_id = $db_participant->id;
        $db_respondent->start_datetime = $participant->datetime;
        $db_respondent->save();
      }

      try
      {
        // Since some attributes may require access to a remote server we must immediately
        // create the response record to make sure attributes are available
        $db_respondent->get_current_response( true );
        $data['success'][] = $participant->uid;
      }
      catch( \cenozo\exception\base_exception $e )
      {
        $db_respondent->delete();
        $data['fail'][] = $participant->uid;
      }
    }

    return $data;
  }

  /**
   * Creates a batch of respondents as a single operation
   * @param array $identifier_list A list of participant identifiers to affect
   */
  public function mass_respondent( $db_identifier, $identifier_list )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $participant_class_name = lib::get_class_name( 'database\participant' );
    $participant_identifier_class_name = lib::get_class_name( 'database\participant_identifier' );

    $data = ['success' => [], 'fail' => []];
    foreach( $identifier_list as $identifier )
    {
      $db_participant = is_null( $db_identifier )
                      ? $participant_class_name::get_unique_record( 'uid', $identifier )
                      : $participant_identifier_class_name::get_unique_record(
                          ['identifier_id', 'value'],
                          [$db_identifier->id, $identifier]
                        )->get_participant();
      $db_respondent = lib::create( 'database\respondent' );
      $db_respondent->qnaire_id = $this->id;
      $db_respondent->participant_id = $db_participant->id;
      $db_respondent->save();

      try
      {
        // now make sure the respondent has a response (so response attribute values are cached)
        $db_respondent->get_current_response( true );
        $data['success'][] = $identifier;
      }
      catch( \cenozo\exception\base_exception $e )
      {
        $db_respondent->delete();
        $data['fail'][] = $identifier;
      }
    }

    return $data;
  }

  /**
   * Transforms a JSON-encoded unit-list string into an associative array.
   * 
   * Note that the response depends on the questionnaire's languages, so only unit lists from
   * questions or options belonging to this qnaire should be passed to this method.
   * 
   * Unit lists are used by the "number with unit" question type and question option extra types.
   * Data is stored in a JSON-encoded string using multiple different formats, for example:
   *   [ "mg", "IU" ]
   *   [ { "MG": "mg" }, { "IU": { "en": "IU", "fr": "U. I." } } ]
   *   { "MG": "mg", "IU": { "en": "IU", "fr": "U. I." } }
   * 
   * @param string $unit_list A JSON-encoded unit list
   * @return associative array
   * @static
   * @access public
   */
  public function get_unit_list_enum( $unit_list )
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

    $base_lang = $this->get_base_language()->code;
    $data = util::json_decode( $unit_list );

    $unit_list_enum = [];
    foreach( $this->get_language_object_list() as $db_language )
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

  /**
   * Returns an array of all questions belonging to this qnaire
   * @param boolean $descriptions If true then include module, page and question descriptions
   * @param boolean $export_only Whether to restrict to questions marked for export only
   * @return array
   */
  public function get_output_column_list( $descriptions = false, $export_only = false )
  {
    $column_list = [];

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
        $question_mod->where( 'type', 'NOT IN', ['comment', 'device'] );
        if( $export_only ) $question_mod->where( 'export', '=', true );
        $question_mod->order( 'question.rank' );
        foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
        {
          $column_list = array_merge(
            $column_list,
            $db_question->get_output_column_list( $descriptions )
          );
        }
      }
    }

    return $column_list;
  }

  /**
   * Returns an array of all response metadata for this qnaire
   * @param database\modifier $modifier
   * @return ['header', 'data']
   */
  public function get_response_metadata( $modifier = NULL )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $response_class_name = lib::get_class_name( 'database\response' );
    $response_stage_class_name = lib::get_class_name( 'database\response_stage' );

    $data = [];

    // now loop through all responses and fill in the data array
    $response_mod = lib::create( 'database\modifier' );
    $response_mod->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $response_mod->left_join( 'participant', 'respondent.participant_id', 'participant.id' );
    $response_mod->join( 'language', 'response.language_id', 'language.id' );
    $response_mod->left_join( 'site', 'response.site_id', 'site.id' );
    $response_mod->where( 'respondent.qnaire_id', '=', $this->id );
    // make sure the response has stages, 
    $response_mod->join( 'response_stage', 'response.id', 'response_stage.response_id' );
    $response_mod->group( 'response.id' );
    $response_mod->order( 'respondent.end_datetime' );

    if( !is_null( $modifier ) )
    {
      $response_mod->merge( $modifier );
      $response_mod->limit( $modifier->get_limit() );
      $response_mod->offset( $modifier->get_offset() );
    }

    $response_sel = lib::create( 'database\select' );
    $response_sel->add_column( 'id' );
    $response_sel->add_table_column( 'respondent', 'token' );
    $response_sel->add_column( 'rank' );
    $response_sel->add_column( 'qnaire_version' );
    $response_sel->add_table_column( 'language', 'code', 'language' );
    $response_sel->add_table_column( 'site', 'name', 'site' );
    $response_sel->add_column( 'submitted' );
    $response_sel->add_column(
      'DATE_FORMAT( response.start_datetime, "%Y-%m-%dT%T+00:00" )',
      'start_datetime',
      false
    );
    $response_sel->add_column(
      'DATE_FORMAT( response.last_datetime, "%Y-%m-%dT%T+00:00" )',
      'last_datetime',
      false
    );
    $response_sel->add_table_column( 'participant', 'uid' );

    // add the base response data
    $data = [];
    foreach( $response_class_name::select( $response_sel, $response_mod ) as $response )
    {
      $data[$response['id']] = [
        'uid' => $response['uid'],
        'token' => $response['token'],
        'rank' => $response['rank'],
        'qnaire_version' => $response['qnaire_version'],
        'language' => $response['language'],
        'site' => $response['site'],
        'submitted' => $response['submitted'],
        'start_datetime' => $response['start_datetime'],
        'last_datetime' => $response['last_datetime']
      ];
    }

    // now get a list of all response stage details for all selected response IDs
    $stage_sel = lib::create( 'database\select' );
    $stage_sel->add_column( 'response_id' );
    $stage_sel->add_table_column( 'stage', 'rank', 'stage_rank' );
    $stage_sel->add_table_column( 'stage', 'name', 'stage_name' );
    $stage_sel->add_table_column( 'user', 'name', 'stage_user' );
    $stage_sel->add_column( 'start_datetime', 'stage_start_datetime' );
    $stage_sel->add_column( 'end_datetime', 'stage_end_datetime' );
    $stage_sel->add_column(
      $response_stage_class_name::get_elapsed_column(),
      'stage_duration',
      false
    );
    $stage_sel->add_column( 'status', 'stage_status' );
    $stage_sel->add_table_column( 'deviation_type', 'type', 'stage_deviation_type' );
    $stage_sel->add_table_column( 'deviation_type', 'name', 'stage_deviation_name' );
    $stage_sel->add_column( 'deviation_comments', 'stage_deviation_comments' );
    $stage_sel->add_column( 'comments', 'stage_comments' );
    
    $stage_mod = lib::create( 'database\modifier' );
    $stage_mod->where( 'response_id', 'IN', array_keys( $data ) );
    $stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
    $stage_mod->left_join( 'user', 'response_stage.user_id', 'user.id' );
    $stage_mod->left_join( 'deviation_type', 'response_stage.deviation_type_id', 'deviation_type.id' );
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'response_stage.id', '=', 'response_stage_pause.response_stage_id', false );
    $join_mod->where( 'response_stage_pause.end_datetime', '!=', NULL );
    $stage_mod->join_modifier( 'response_stage_pause', $join_mod, 'left' );
    $stage_mod->group( 'response_stage.id' );
    $stage_mod->order( 'response_id' );
    $stage_mod->order( 'stage.rank' );
    $stage_data = [];

    $name_list = [
      'stage_name', 'stage_user', 'stage_start_datetime', 'stage_end_datetime', 'stage_duration', 'stage_status',
      'stage_deviation_type', 'stage_deviation_name', 'stage_deviation_comments', 'stage_comments'
    ];
    $column_list = [];
    foreach( $response_stage_class_name::select( $stage_sel, $stage_mod ) as $response_stage )
    {
      // make sure to add new columns as we go
      $stage_rank = $response_stage['stage_rank'];
      foreach( $name_list as $name )
      {
        $column_name = sprintf( '%s_%d', $name, $stage_rank );
        // add this rank to the column list if we haven't done it yet
        if( !in_array( $column_name, $column_list ) ) $column_list[] = $column_name;

        $data[$response_stage['response_id']][$column_name] = $response_stage[$name];
      }
    }

    $header = $column_list;
    array_unshift(
      $header,
      'uid', 'token', 'rank', 'qnaire_version', 'language', 'site', 'submitted', 'start_datetime', 'last_datetime'
    );
    return ['header' => $header, 'data' => array_values( $data )];
  }

  /**
   * Returns an array of all responses to this qnaire
   * @param database\modifier $modifier
   * @param boolean $exporting Whether the data is being exported (some questions are marked to not be exported)
   * @param boolean $attributes Whether to include attribute values
   * @param boolean $answers_only Whether to restrict responses to those with one or more answers
   * @return ['header', 'data']
   */
  public function get_response_data(
    $modifier = NULL, $exporting = false, $attributes = false, $answers_only = false )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $response_class_name = lib::get_class_name( 'database\response' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $column_list = $this->get_output_column_list( false, $exporting ); // exclude questions not marked for export
    $attribute_list = [];

    // now loop through all responses and fill in the data array
    $data = [];
    $response_mod = lib::create( 'database\modifier' );
    $response_mod->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $response_mod->left_join( 'participant', 'respondent.participant_id', 'participant.id' );
    $response_mod->join( 'language', 'response.language_id', 'language.id' );
    $response_mod->left_join( 'site', 'response.site_id', 'site.id' );
    $response_mod->where( 'respondent.qnaire_id', '=', $this->id );
    $response_mod->order( 'respondent.end_datetime' );

    if( !is_null( $modifier ) )
    {
      $response_mod->merge( $modifier );
      $response_mod->limit( $modifier->get_limit() );
      $response_mod->offset( $modifier->get_offset() );
    }

    $response_sel = lib::create( 'database\select' );
    $response_sel->add_column( 'id' );
    $response_sel->add_table_column( 'respondent', 'token' );
    $response_sel->add_column( 'rank' );
    $response_sel->add_column( 'qnaire_version' );
    $response_sel->add_table_column( 'language', 'code', 'language' );
    $response_sel->add_table_column( 'site', 'name', 'site' );
    $response_sel->add_column( 'submitted' );
    $response_sel->add_column(
      'DATE_FORMAT( response.start_datetime, "%Y-%m-%dT%T+00:00" )',
      'start_datetime',
      false
    );
    $response_sel->add_column(
      'DATE_FORMAT( response.last_datetime, "%Y-%m-%dT%T+00:00" )',
      'last_datetime',
      false
    );
    $response_sel->add_table_column( 'participant', 'uid' );

    $response_attribute_data = array();
    if( $attributes )
    {
      // get a list of all attributes for this qnaire
      $attribute_sel = lib::create( 'database\select' );
      $attribute_sel->add_column( 'name' );
      $attribute_mod = lib::create( 'database\modifier' );
      $attribute_mod->order( 'attribute.id' );
      foreach( $this->get_attribute_list( $attribute_sel, $attribute_mod ) as $attribute )
        $attribute_list[] = $attribute['name'];

      // get all response attributes for all responses
      $response_attribute_sel = lib::create( 'database\select' );
      $response_attribute_sel->add_column( 'response_id' );
      $response_attribute_sel->add_table_column( 'attribute', 'name' );
      $response_attribute_sel->add_column( 'value' );
      $response_attribute_mod = lib::create( 'database\modifier' );
      $response_attribute_mod->join( 'attribute', 'response_attribute.attribute_id', 'attribute.id' );
      $response_attribute_mod->join( 'response', 'response_attribute.response_id', 'response.id' );
      $response_attribute_mod->join( 'respondent', 'response.respondent_id', 'respondent.id' );
      $response_attribute_mod->where( 'respondent.qnaire_id', '=', $this->id );
      $response_attribute_mod->order( 'response_id' );
      $response_attribute_mod->order( 'attribute_id' );
      if( !is_null( $modifier ) ) $response_attribute_mod->merge( $modifier );

      foreach( $response_attribute_class_name::select( $response_attribute_sel, $response_attribute_mod ) as $ra )
      {
        if( !array_key_exists( $ra['response_id'], $response_attribute_data ) )
          $response_attribute_data[$ra['response_id']] = [];
        $response_attribute_data[$ra['response_id']][$ra['name']] = $ra['value'];
      }
    }

    // get a list of all response IDs so we can use it to get all answers
    $response_id_list = [];
    foreach( $response_class_name::select( $response_sel, $response_mod ) as $response )
      $response_id_list[] = $response['id'];

    $answer_sel = lib::create( 'database\select' );
    $answer_sel->add_column( 'response_id' );
    $answer_sel->add_column( 'question_id' );
    $answer_sel->add_column( 'value' );
    $answer_mod = lib::create( 'database\modifier' );
    $answer_mod->where( 'response_id', 'IN', $response_id_list );
    $answer_mod->order( 'response_id' );
    $answer_mod->order( 'question_id' );
    $answer_data = [];
    foreach( $answer_class_name::select( $answer_sel, $answer_mod ) as $answer )
    {
      if( !array_key_exists( $answer['response_id'], $answer_data ) ) $answer_data[$answer['response_id']] = [];
      $answer_data[$answer['response_id']][$answer['question_id']] = $answer['value'];
    }

    // loop through each response and build the data
    foreach( $response_class_name::select( $response_sel, $response_mod ) as $response )
    {
      $answer_list = array_key_exists( $response['id'], $answer_data )
                   ? $answer_data[$response['id']]
                   : NULL;

      // if requested, don't add responses with no answers
      if( $answers_only && is_null( $answer_list ) ) continue;

      $data_row = [
        $response['uid'],
        $response['token'],
        $response['rank'],
        $response['qnaire_version'],
        $response['language'],
        $response['site'],
        $response['submitted'] ? 1 : 0,
        $response['start_datetime'],
        $response['last_datetime']
      ];

      foreach( $column_list as $column_name => $column )
      {
        $row_value = NULL;

        if( !is_null( $answer_list ) && array_key_exists( $column['question_id'], $answer_list ) )
        {
          $non_exclusive_list = 'list' == $column['type'] && !$column['all_exclusive'];
          if( $answer_class_name::DKNA == $answer_list[$column['question_id']] )
          {
            if( $non_exclusive_list && array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so set the value to false unless this is the DN_KA column
              $row_value = 'dkna' == $column['option_id'] ? 1 : 0;
            }
            else if( $non_exclusive_list ||
                     array_key_exists( 'option_list', $column ) ||
                     array_key_exists( 'missing_list', $column ) )
            {
              $row_value = 'DK_NA';
            }
          }
          else if( $answer_class_name::REFUSE == $answer_list[$column['question_id']] )
          {
            if( $non_exclusive_list && array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so set the value to false unless this is the REFUSED column
              $row_value = 'refuse' == $column['option_id'] ? 1 : 0;
            }
            else if( $non_exclusive_list ||
                     array_key_exists( 'option_list', $column ) ||
                     array_key_exists( 'boolean_list', $column ) ||
                     array_key_exists( 'missing_list', $column ) )
            {
              $row_value = 'REFUSED';
            }
          }
          else
          {
            $answer = util::json_decode( $answer_list[$column['question_id']] );
            if( array_key_exists( 'missing_list', $column ) )
            {
              // leave the row value null
            }
            else if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so every possible answer has its own variable
              if( 'dkna' == $column['option_id'] || 'refuse' == $column['option_id'] )
              {
                // whatever the answer is it isn't dkna or refuse
                $row_value = 0;
              }
              else
              {
                $row_value = $non_exclusive_list ? 0 : NULL;
                if( is_array( $answer ) ) foreach( $answer as $a )
                {
                  if( ( is_object( $a ) && $column['option_id'] == $a->id ) ||
                      ( !is_object( $a ) && $column['option_id'] == $a ) )
                  {
                    // use the value if the option asks for extra data
                    $row_value = 1;

                    if( !is_null( $column['extra'] ) )
                    {
                      if( 'number with unit' == $column['extra'] )
                      {
                        $row_value = NULL;
                        if( property_exists( $a, 'value' ) )
                        {
                          $row_value = array_key_exists( 'unit_list', $column )
                                     ? $a->value->unit
                                     : $a->value->value;
                        }
                      }
                      else
                      {
                        $row_value = property_exists( $a, 'value' ) ? $a->value : NULL;
                      }
                    }

                    break;
                  }
                }
              }
            }
            else // the question can only have one answer
            {
              if( in_array( $column['type'], ['audio', 'boolean'] ) )
              {
                // convert audio and boolean values from 0 and 1 to NO and YES
                $row_value = $answer ? 'YES' : 'NO';
              }
              else if( 'list' == $column['type'] )
              { // this is a "select one option" so set the answer to the option's name
                if( is_array( $answer ) )
                {
                  $this_answer = current( $answer );
                  $option_id = is_object( $this_answer ) ? $this_answer->id : $this_answer;
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
              else if( 'number with unit' == $column['type'] )
              {
                // if the column has a unit_list property then this is the UNIT column, otherwise it's the value
                $row_value = array_key_exists( 'unit_list', $column ) ? $answer->unit : $answer->value;
              }
              else // date, number, string, text and time are all just direct answers
              {
                $row_value = $answer;
              }
            }
          }
        }

        $data_row[] = is_array( $row_value ) ? implode( ';', $row_value ) : $row_value;
      }

      if( $attributes )
      {
        foreach( $attribute_list as $attribute )
        {
          $data_row[] = array_key_exists( $response['id'], $response_attribute_data )
                      ? $response_attribute_data[$response['id']][$attribute]
                      : NULL;
        }
      }

      $data[] = $data_row;
    }

    $header = array_keys( $column_list );
    array_unshift(
      $header,
      'uid', 'token', 'rank', 'qnaire_version', 'language', 'site', 'submitted', 'start_datetime', 'last_datetime'
    );
    if( $attributes ) foreach( $attribute_list as $attribute ) $header[] = sprintf( 'attribute:%s', $attribute );
    return ['header' => $header, 'data' => $data];
  }

  /**
   * Compiles embedded files in any description
   * @param string $description
   */
  public function compile_description( $description )
  {
    $embedded_file_class_name = lib::get_class_name( 'database\embedded_file' );

    preg_match_all( '/@([A-Za-z0-9_]+)(\.width\( *([0-9]+%?) *\))?@/', $description, $matches );
    foreach( $matches[1] as $index => $match )
    {
      $name = $match;

      // images may have a width argument, for example: @name.width(123)@
      $width = array_key_exists( 3, $matches ) ? $matches[3][$index] : NULL;
      $db_embedded_file = $embedded_file_class_name::get_unique_record(
        ['qnaire_id', 'name'],
        [$this->id, $name]
      );
      if( !is_null( $db_embedded_file ) )
      {
        $description = str_replace( $matches[0][$index], $db_embedded_file->get_tag( $width ), $description );
      }
    }

    return $description;
  }

  /**
   * Applies a patch file to the qnaire and returns an object containing all elements which are affected by
   * the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   * @return stdObject
   */
  public function process_patch( $patch_object, $apply = false )
  {
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $language_class_name = lib::get_class_name( 'database\language' );
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $qnaire_document_class_name = lib::get_class_name( 'database\qnaire_document' );
    $embedded_file_class_name = lib::get_class_name( 'database\embedded_file' );
    $deviation_type_class_name = lib::get_class_name( 'database\deviation_type' );
    $reminder_class_name = lib::get_class_name( 'database\reminder' );
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );
    $collection_class_name = lib::get_class_name( 'database\collection' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $event_type_class_name = lib::get_class_name( 'database\event_type' );
    $alternate_consent_type_class_name = lib::get_class_name( 'database\alternate_consent_type' );
    $proxy_type_class_name = lib::get_class_name( 'database\proxy_type' );
    $equipment_type_class_name = lib::get_class_name( 'database\equipment_type' );
    $qnaire_consent_type_confirm_class_name = lib::get_class_name( 'database\qnaire_consent_type_confirm' );
    $qnaire_participant_trigger_class_name = lib::get_class_name( 'database\qnaire_participant_trigger' );
    $qnaire_collection_trigger_class_name = lib::get_class_name( 'database\qnaire_collection_trigger' );
    $qnaire_consent_type_trigger_class_name = lib::get_class_name( 'database\qnaire_consent_type_trigger' );
    $qnaire_event_type_trigger_class_name = lib::get_class_name( 'database\qnaire_event_type_trigger' );
    $qnaire_aconsent_type_trigger_class_name =
      lib::get_class_name( 'database\qnaire_alternate_consent_type_trigger' );
    $qnaire_proxy_type_trigger_class_name = lib::get_class_name( 'database\qnaire_proxy_type_trigger' );
    $qnaire_equipment_type_trigger_class_name = lib::get_class_name( 'database\qnaire_equipment_type_trigger' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $stage_class_name = lib::get_class_name( 'database\stage' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );
    $device_class_name = lib::get_class_name( 'database\device' );
    $qnaire_report_class_name = lib::get_class_name( 'database\qnaire_report' );

    // NOTE: since we want to avoid duplicate unique keys caused by re-naming or re-ordering modules we use
    // the following offset and suffix values when setting rank and name, then after all changes have been
    // made remove the offset/suffix
    $name_suffix = bin2hex( openssl_random_pseudo_bytes( 5 ) );

    $difference_list = [];
    $change_question_name_list = [];

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
        $add_list = [];
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
        $remove_list = [];
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

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['language_list'] = $diff_list;
      }
      else if( 'reminder_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->reminder_list as $reminder )
        {
          $reminder_mod = lib::create( 'database\modifier' );
          $reminder_mod->where( 'delay_offset', '=', $reminder->delay_offset );
          $reminder_mod->where( 'delay_unit', '=', $reminder->delay_unit );
          $reminder_list = $this->get_reminder_object_list( $reminder_mod );
          $db_reminder = 0 == count( $reminder_list ) ? NULL : current( $reminder_list );
          if( is_null( $db_reminder ) )
          {
            if( $apply ) $reminder_class_name::create_from_object( $reminder, $this );
            else $add_list[] = $reminder;
          }
          else
          {
            // find and add all differences
            $diff = $db_reminder->process_patch( $reminder, $apply );
            if( !is_null( $diff ) )
            {
              // the process_patch() function above applies any changes so we don't have to do it here
              if( !$apply )
              {
                $index = sprintf( '%d %s', $reminder->delay_offset, $reminder->delay_unit );
                $change_list[$index] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_reminder_object_list() as $db_reminder )
        {
          $found = false;
          foreach( $patch_object->reminder_list as $reminder )
          {
            if(
              $db_reminder->delay_offset == $reminder->delay_offset &&
              $db_reminder->delay_unit == $reminder->delay_unit
            ) {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_reminder->delete();
            else $remove_list[] = sprintf( '%s %s', $db_reminder->delay_offset, $db_reminder->delay_unit );
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['reminder_list'] = $diff_list;
      }
      else if( 'lookup_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->lookup_list as $lookup )
        {
          $db_lookup = $lookup_class_name::get_unique_record( 'name', $lookup->name );

          if( is_null( $db_lookup ) )
          {
            if( $apply ) $lookup_class_name::create_from_object( $lookup );
            else $add_list[] = $lookup;
          }
          else if( $db_lookup->version != $lookup->version )
          {
            if( $apply )
            {
              // delete and re-create the entire lookup
              $db_lookup->delete();
              $lookup_class_name::create_from_object( $lookup );
            }
            else $change_list[$lookup->name] = ['version' => $lookup->version];
          }
        }

        // check every item in this object for removals (lookups referenced by the qnaire only)
        $remove_list = [];
        foreach( $this->get_lookup_object_list() as $db_lookup )
        {
          $found = false;
          foreach( $patch_object->lookup_list as $lookup )
          {
            if( $db_lookup->name == $lookup->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_lookup->delete();
            else $remove_list[] = $db_lookup->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['lookup_list'] = $diff_list;
      }
      else if( 'device_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->device_list as $device )
        {
          $db_device = $device_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $device->name]
          );

          if( is_null( $db_device ) )
          {
            if( $apply ) $device_class_name::create_from_object( $device, $this );
            else $add_list[] = $device;
          }
          else
          {
            // find and add all differences
            $diff = $db_device->process_patch( $device, $apply );
            if( !is_null( $diff ) )
            {
              // the process_patch() function above applies any changes so we don't have to do it here
              if( !$apply ) $change_list[$device->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_device_object_list() as $db_device )
        {
          $found = false;
          foreach( $patch_object->device_list as $device )
          {
            if( $db_device->name == $device->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_device->delete();
            else $remove_list[] = $db_device->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['device_list'] = $diff_list;
      }
      else if( 'qnaire_report_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_report_list as $qnaire_report )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $qnaire_report->language );
          $db_qnaire_report = $qnaire_report_class_name::get_unique_record(
            ['qnaire_id', 'language_id'],
            [$this->id, $db_language->id]
          );

          if( is_null( $db_qnaire_report ) )
          {
            if( $apply ) $qnaire_report_class_name::create_from_object( $qnaire_report, $this );
            else $add_list[] = $qnaire_report;
          }
          else
          {
            // find and all add differences
            $diff = $db_qnaire_report->process_patch( $qnaire_report, $apply );
            if( !is_null( $diff ) )
            {
              // the process_patch() function above applies any changes so we don't have to do it here
              if( !$apply ) $change_list[$db_language->code] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_report_object_list() as $db_qnaire_report )
        {
          $language = $db_qnaire_report->get_language()->code;
          $found = false;
          foreach( $patch_object->qnaire_report_list as $qnaire_report )
          {
            if( $language == $qnaire_report->language )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_report->delete();
            else $remove_list[] = $language;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_report_list'] = $diff_list;
      }
      else if( 'deviation_type_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        foreach( $patch_object->deviation_type_list as $deviation_type )
        {
          $db_deviation_type = $deviation_type_class_name::get_unique_record(
            ['qnaire_id', 'type', 'name'],
            [$this->id, $deviation_type->type, $deviation_type->name]
          );

          if( is_null( $db_deviation_type ) )
          {
            if( $apply ) $deviation_type_class_name::create_from_object( $deviation_type, $this );
            else $add_list[] = $deviation_type;
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_deviation_type_object_list() as $db_deviation_type )
        {
          $found = false;
          foreach( $patch_object->deviation_type_list as $deviation_type )
          {
            if( $db_deviation_type->type == $deviation_type->type &&
                $db_deviation_type->name == $deviation_type->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_deviation_type->delete();
            else
            {
              $index = sprintf( '%s [%s]', $db_deviation_type->type, $db_deviation_type->name );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['deviation_type_list'] = $diff_list;
      }
      else if( 'attribute_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->attribute_list as $attribute )
        {
          $db_attribute = $attribute_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $attribute->name]
          );

          if( is_null( $db_attribute ) )
          {
            if( $apply ) $attribute_class_name::create_from_object( $attribute, $this );
            else $add_list[] = $attribute;
          }
          else
          {
            // find and add all differences
            $diff = [];
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
        $remove_list = [];
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

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['attribute_list'] = $diff_list;
      }
      else if( 'qnaire_document_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_document_list as $qnaire_document )
        {
          $db_qnaire_document = $qnaire_document_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $qnaire_document->name]
          );

          if( is_null( $db_qnaire_document ) )
          {
            if( $apply ) $qnaire_document_class_name::create_from_object( $qnaire_document, $this );
            else $add_list[] = $qnaire_document;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $qnaire_document as $property => $value )
              if( $db_qnaire_document->$property != $qnaire_document->$property )
                $diff[$property] = $qnaire_document->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_qnaire_document->name = $qnaire_document->name;
                $db_qnaire_document->data = $qnaire_document->data;
                $db_qnaire_document->save();
              }
              else $change_list[$db_qnaire_document->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_document_object_list() as $db_qnaire_document )
        {
          $found = false;
          foreach( $patch_object->qnaire_document_list as $qnaire_document )
          {
            if( $db_qnaire_document->name == $qnaire_document->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_document->delete();
            else $remove_list[] = $db_qnaire_document->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_document_list'] = $diff_list;
      }
      else if( 'image_list' == $property || 'embedded_file_list' == $property )
      {
        // Note: the embedded_file object used to be called image, so just assume they are the same

        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->embedded_file_list as $embedded_file )
        {
          $db_embedded_file = $embedded_file_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $embedded_file->name]
          );

          if( is_null( $db_embedded_file ) )
          {
            if( $apply ) $embedded_file_class_name::create_from_object( $embedded_file, $this );
            else $add_list[] = $embedded_file;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $embedded_file as $property => $value )
              if( $db_embedded_file->$property != $embedded_file->$property )
                $diff[$property] = $embedded_file->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_embedded_file->name = $embedded_file->name;
                $db_embedded_file->mime_type = $embedded_file->mime_type;
                $db_embedded_file->size = $embedded_file->size;
                $db_embedded_file->data = $embedded_file->data;
                $db_embedded_file->save();
              }
              else $change_list[$db_embedded_file->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_embedded_file_object_list() as $db_embedded_file )
        {
          $found = false;
          foreach( $patch_object->embedded_file_list as $embedded_file )
          {
            if( $db_embedded_file->name == $embedded_file->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_embedded_file->delete();
            else $remove_list[] = $db_embedded_file->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['embedded_file_list'] = $diff_list;
      }
      else if( 'qnaire_description_list' == $property )
      {
        // check every item in the patch object for changes (additions aren't possible)
        $change_list = [];
        foreach( $patch_object->qnaire_description_list as $qnaire_description )
        {
          $db_language = $language_class_name::get_unique_record( 'code', $qnaire_description->language );
          $db_qnaire_description = $this->get_description( $qnaire_description->type, $db_language );

          // find and add all differences
          $diff = [];
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

        $diff_list = [];
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_description_list'] = $diff_list;
      }
      else if( 'module_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->module_list as $module )
        {
          // match module by name or rank
          $db_module = $module_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $module->name]
          );
          if( is_null( $db_module ) )
          {
            // we may have renamed the module, so see if it exists exactly the same under the same rank
            $db_module = $module_class_name::get_unique_record(
              ['qnaire_id', 'rank'],
              [$this->id, $module->rank]
            );
            if( !is_null( $db_module ) )
            {
              // confirm that the name is the only thing that has changed
              $properties = array_keys( get_object_vars(
                $db_module->process_patch( $module, $name_suffix, false )
              ) );
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
        $remove_list = [];
        $module_mod = lib::create( 'database\modifier' );
        $module_mod->order( 'rank' );
        foreach( $this->get_module_object_list( $module_mod ) as $db_module )
        {
          $found = false;
          foreach( $patch_object->module_list as $module )
          {
            // see if the module exists in the patch or if we're already changing the module
            $name = $apply
                  ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_module->name )
                  : $db_module->name;
            if( $name == $module->name || in_array( $name, array_keys( $change_list ) ) )
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

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['module_list'] = $diff_list;

        // get a list of all questions with new names (used by other properties)
        if( array_key_exists( 'module_list', $difference_list ) &&
            array_key_exists( 'change', $difference_list['module_list'] ) )
        {
          foreach( $difference_list['module_list']['change'] as $module_change )
          {
            if( property_exists( $module_change, 'page_list' ) &&
                array_key_exists( 'change', $module_change->page_list ) )
            {
              foreach( $module_change->page_list['change'] as $page_change )
              {
                if( property_exists( $page_change, 'question_list' ) &&
                    array_key_exists( 'change', $page_change->question_list ) )
                {
                  foreach( $page_change->question_list['change'] as $old_name => $question_change )
                  {
                    if( property_exists( $question_change, 'name' ) )
                    {
                      $change_question_name_list[$question_change->name] = $old_name;
                    }
                  }
                }
              }
            }
          }
        }
      }
      else if( 'stage_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->stage_list as $stage )
        {
          // match stage by name or rank
          $db_stage = $stage_class_name::get_unique_record(
            ['qnaire_id', 'name'],
            [$this->id, $stage->name]
          );
          if( is_null( $db_stage ) )
          {
            // we may have renamed the stage, so see if it exists exactly the same under the same rank
            $db_stage = $stage_class_name::get_unique_record(
              ['qnaire_id', 'rank'],
              [$this->id, $stage->rank]
            );
            if( !is_null( $db_stage ) )
            {
              // confirm that the name is the only thing that has changed
              $properties = array_keys( get_object_vars(
                $db_stage->process_patch( $stage, $name_suffix, false )
              ) );
              if( 1 != count( $properties ) || 'name' != current( $properties ) ) $db_stage = NULL;
            }
          }

          if( is_null( $db_stage ) )
          {
            if( $apply )
            {
              $db_stage = lib::create( 'database\stage' );
              $db_stage->qnaire_id = $this->id;
              $db_stage->rank = $stage->rank;
              $db_stage->name = sprintf( '%s_%s', $stage->name, $name_suffix );
              $db_stage->precondition = $stage->precondition;
              $db_stage->process_patch( $stage, $name_suffix, $apply );
              $db_stage->save();
            }
            else $add_list[] = $stage;
          }
          else
          {
            // find and add all differences
            $diff = $db_stage->process_patch( $stage, $name_suffix, $apply );
            if( !is_null( $diff ) )
            {
              if( !$apply ) $change_list[$db_stage->name] = $diff;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        $stage_mod = lib::create( 'database\modifier' );
        $stage_mod->order( 'rank' );
        foreach( $this->get_stage_object_list( $stage_mod ) as $db_stage )
        {
          $found = false;
          foreach( $patch_object->stage_list as $stage )
          {
            // see if the stage exists in the patch or if we're already changing the stage
            $name = $apply
                  ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_stage->name )
                  : $db_stage->name;
            if( $name == $stage->name || in_array( $name, array_keys( $change_list ) ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_stage->delete();
            else $remove_list[] = $db_stage->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['stage_list'] = $diff_list;
      }
      else if( 'qnaire_consent_type_confirm_list' == $property )
      {
        // check every item in the patch object for additions
        $add_list = [];
        foreach( $patch_object->qnaire_consent_type_confirm_list as $qnaire_consent_type_confirm )
        {
          $db_consent_type = $consent_type_class_name::get_unique_record(
            'name',
            $qnaire_consent_type_confirm->consent_type_name
          );

          if( is_null( $db_consent_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Consent Confirm for "%s" will be ignore since the consent type does not exist.',
                $qnaire_consent_type_confirm->consent_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_qnaire_consent_type_confirm = $qnaire_consent_type_confirm_class_name::get_unique_record(
              ['qnaire_id', 'consent_type_id'],
              [$this->id, $db_consent_type->id]
            );

            if( is_null( $db_qnaire_consent_type_confirm ) )
            {
              if( $apply )
              {
                $qnaire_consent_type_confirm_class_name::create_from_object(
                  $qnaire_consent_type_confirm,
                  $this
                );
              }
              else $add_list[] = $qnaire_consent_type_confirm;
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_consent_type_confirm_object_list() as $db_qnaire_consent_type_confirm )
        {
          $consent_type_name = $db_qnaire_consent_type_confirm->get_consent_type()->name;

          $found = false;
          foreach( $patch_object->qnaire_consent_type_confirm_list as $qnaire_consent_type_confirm )
          {
            // see if the qnaire_consent_type_confirm exists
            if( $consent_type_name == $qnaire_consent_type_confirm->consent_type_name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_consent_type_confirm->delete();
            else $remove_list[] = $consent_type_name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_consent_type_confirm_list'] = $diff_list;
      }
      else if( 'qnaire_participant_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_participant_trigger_list as $qnaire_participant_trigger )
        {
          $db_question = $this->get_question(
            array_key_exists( $qnaire_participant_trigger->question_name, $change_question_name_list ) ?
            $change_question_name_list[$qnaire_participant_trigger->question_name] :
            $qnaire_participant_trigger->question_name
          );

          // check to see if the question has been renamed as part of the applied patch
          if( $apply && is_null( $db_question ) )
          {
            $db_question = $this->get_question(
              sprintf(
                '%s_%s',
                $qnaire_participant_trigger->question_name,
                $name_suffix
              )
            );
          }

          $db_qnaire_participant_trigger = $qnaire_participant_trigger_class_name::get_unique_record(
            ['qnaire_id', 'question_id', 'answer_value', 'column_name'],
            [
              $this->id,
              $db_question->id,
              $qnaire_participant_trigger->answer_value,
              $qnaire_participant_trigger->column_name
            ]
          );

          if( is_null( $db_qnaire_participant_trigger ) )
          {
            if( $apply )
            {
              $qnaire_participant_trigger_class_name::create_from_object(
                $qnaire_participant_trigger,
                $db_question
              );
            }
            else $add_list[] = $qnaire_participant_trigger;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $qnaire_participant_trigger as $property => $value )
              if( 'question_name' != $property &&
                  $db_qnaire_participant_trigger->$property != $qnaire_participant_trigger->$property )
                $diff[$property] = $qnaire_participant_trigger->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_qnaire_participant_trigger->answer_value = $qnaire_participant_trigger->answer_value;
                $db_qnaire_participant_trigger->save();
              }
              else
              {
                $index = sprintf(
                  '%s %s [%s]',
                  $qnaire_participant_trigger->column_name,
                  $qnaire_participant_trigger->value,
                  $qnaire_participant_trigger->question_name
                );
                $change_list[$index] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_participant_trigger_object_list() as $db_qnaire_participant_trigger )
        {
          $changed_name = array_search(
            $db_qnaire_participant_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_participant_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_participant_trigger_list as $qnaire_participant_trigger )
          {
            // see if the qnaire_participant_trigger exists
            if( ( $db_qnaire_participant_trigger->column_name == $qnaire_participant_trigger->column_name &&
                  $question_name == $qnaire_participant_trigger->question_name &&
                  $db_qnaire_participant_trigger->value == $qnaire_participant_trigger->value ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_participant_trigger->delete();
            else
            {
              $index = sprintf(
                '%s %s [%s]',
                $db_qnaier_participant_trigger->column_name,
                $db_qnaire_participant_trigger->value,
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_participant_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_collection_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_collection_trigger_list as $qnaire_collection_trigger )
        {
          $db_collection = $collection_class_name::get_unique_record(
            'name',
            $qnaire_collection_trigger->collection_name
          );

          if( is_null( $db_collection ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Consent trigger for "%s" will be ignore since the collection does not exist.',
                $qnaire_collection_trigger->collection_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_collection_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_collection_trigger->question_name] :
              $qnaire_collection_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_collection_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_collection_trigger = $qnaire_collection_trigger_class_name::get_unique_record(
              ['qnaire_id', 'collection_id', 'question_id', 'answer_value'],
              [
                $this->id,
                $db_collection->id,
                $db_question->id,
                $qnaire_collection_trigger->answer_value
              ]
            );

            if( is_null( $db_qnaire_collection_trigger ) )
            {
              if( $apply )
              {
                $qnaire_collection_trigger_class_name::create_from_object(
                  $qnaire_collection_trigger,
                  $db_question
                );
              }
              else $add_list[] = $qnaire_collection_trigger;
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_collection_trigger as $property => $value )
                if( !in_array( $property, [ 'collection_name', 'question_name' ] ) &&
                    $db_qnaire_collection_trigger->$property != $qnaire_collection_trigger->$property )
                  $diff[$property] = $qnaire_collection_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_collection_trigger->answer_value = $qnaire_collection_trigger->answer_value;
                  $db_qnaire_collection_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s %s [%s]',
                    $qnaire_collection_trigger->collection_name,
                    $qnaire_collection_trigger->add_to ? 'add' : 'remove',
                    $qnaire_collection_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_collection_trigger_object_list() as $db_qnaire_collection_trigger )
        {
          $collection_name = $db_qnaire_collection_trigger->get_collection()->name;
          $changed_name = array_search(
            $db_qnaire_collection_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_collection_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_collection_trigger_list as $qnaire_collection_trigger )
          {
            // see if the qnaire_collection_trigger exists
            if( ( $collection_name == $qnaire_collection_trigger->collection_name &&
                  $question_name == $qnaire_collection_trigger->question_name &&
                  $db_qnaire_collection_trigger->add_to == $qnaire_collection_trigger->add_to ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_collection_trigger->delete();
            else
            {
              $index = sprintf(
                '%s %s [%s]',
                $collection_name,
                $db_qnaire_collection_trigger->add_to ? 'add' : 'remove',
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_collection_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_consent_type_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_consent_type_trigger_list as $qnaire_consent_type_trigger )
        {
          $db_consent_type = $consent_type_class_name::get_unique_record(
            'name',
            $qnaire_consent_type_trigger->consent_type_name
          );

          if( is_null( $db_consent_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Consent trigger for "%s" will be ignore since the consent type does not exist.',
                $qnaire_consent_type_trigger->consent_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_consent_type_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_consent_type_trigger->question_name] :
              $qnaire_consent_type_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_consent_type_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_consent_type_trigger = $qnaire_consent_type_trigger_class_name::get_unique_record(
              ['qnaire_id', 'consent_type_id', 'question_id', 'answer_value'],
              [
                $this->id,
                $db_consent_type->id,
                $db_question->id,
                $qnaire_consent_type_trigger->answer_value
              ]
            );

            if( is_null( $db_qnaire_consent_type_trigger ) )
            {
              if( $apply )
              {
                $qnaire_consent_type_trigger_class_name::create_from_object(
                  $qnaire_consent_type_trigger,
                  $db_question
                );
              }
              else $add_list[] = $qnaire_consent_type_trigger;
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_consent_type_trigger as $property => $value )
                if( !in_array( $property, [ 'consent_type_name', 'question_name' ] ) &&
                    $db_qnaire_consent_type_trigger->$property != $qnaire_consent_type_trigger->$property )
                  $diff[$property] = $qnaire_consent_type_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_consent_type_trigger->answer_value = $qnaire_consent_type_trigger->answer_value;
                  $db_qnaire_consent_type_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s %s [%s]',
                    $qnaire_consent_type_trigger->consent_type_name,
                    $qnaire_consent_type_trigger->accept ? 'accept' : 'reject',
                    $qnaire_consent_type_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_consent_type_trigger_object_list() as $db_qnaire_consent_type_trigger )
        {
          $consent_type_name = $db_qnaire_consent_type_trigger->get_consent_type()->name;
          $changed_name = array_search(
            $db_qnaire_consent_type_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_consent_type_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_consent_type_trigger_list as $qnaire_consent_type_trigger )
          {
            // see if the qnaire_consent_type_trigger exists
            if( ( $consent_type_name == $qnaire_consent_type_trigger->consent_type_name &&
                  $question_name == $qnaire_consent_type_trigger->question_name &&
                  $db_qnaire_consent_type_trigger->accept == $qnaire_consent_type_trigger->accept ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_consent_type_trigger->delete();
            else
            {
              $index = sprintf(
                '%s %s [%s]',
                $consent_type_name,
                $db_qnaire_consent_type_trigger->accept ? 'accept' : 'reject',
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_consent_type_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_event_type_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_event_type_trigger_list as $qnaire_event_type_trigger )
        {
          $db_event_type = $event_type_class_name::get_unique_record(
            'name',
            $qnaire_event_type_trigger->event_type_name
          );

          if( is_null( $db_event_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Consent trigger for "%s" will be ignore since the event type does not exist.',
                $qnaire_event_type_trigger->event_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_event_type_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_event_type_trigger->question_name] :
              $qnaire_event_type_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_event_type_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_event_type_trigger = $qnaire_event_type_trigger_class_name::get_unique_record(
              ['qnaire_id', 'event_type_id', 'question_id', 'answer_value'],
              [
                $this->id,
                $db_event_type->id,
                $db_question->id,
                $qnaire_event_type_trigger->answer_value
              ]
            );

            if( is_null( $db_qnaire_event_type_trigger ) )
            {
              if( $apply )
              {
                $qnaire_event_type_trigger_class_name::create_from_object(
                  $qnaire_event_type_trigger,
                  $db_question
                );
              }
              else $add_list[] = $qnaire_event_type_trigger;
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_event_type_trigger as $property => $value )
                if( !in_array( $property, [ 'event_type_name', 'question_name' ] ) &&
                    $db_qnaire_event_type_trigger->$property != $qnaire_event_type_trigger->$property )
                  $diff[$property] = $qnaire_event_type_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_event_type_trigger->answer_value = $qnaire_event_type_trigger->answer_value;
                  $db_qnaire_event_type_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s [%s]',
                    $qnaire_event_type_trigger->event_type_name,
                    $qnaire_event_type_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_event_type_trigger_object_list() as $db_qnaire_event_type_trigger )
        {
          $event_type_name = $db_qnaire_event_type_trigger->get_event_type()->name;
          $changed_name = array_search(
            $db_qnaire_event_type_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_event_type_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_event_type_trigger_list as $qnaire_event_type_trigger )
          {
            // see if the qnaire_event_type_trigger exists
            if( ( $event_type_name == $qnaire_event_type_trigger->event_type_name &&
                  $question_name == $qnaire_event_type_trigger->question_name ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_event_type_trigger->delete();
            else
            {
              $index = sprintf(
                '%s [%s]',
                $event_type_name,
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_event_type_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_alternate_consent_type_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_alternate_consent_type_trigger_list as $qnaire_aconsent_type_trigger )
        {
          $db_aconsent_type = $alternate_consent_type_class_name::get_unique_record(
            'name',
            $qnaire_aconsent_type_trigger->alternate_consent_type_name
          );

          if( is_null( $db_aconsent_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Alternate consent trigger for "%s" will be ignore since the '.
                'alternate consent type does not exist.',
                $qnaire_aconsent_type_trigger->alternate_consent_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_aconsent_type_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_aconsent_type_trigger->question_name] :
              $qnaire_aconsent_type_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_aconsent_type_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_aconsent_type_trigger = $qnaire_aconsent_type_trigger_class_name::get_unique_record(
              ['qnaire_id', 'alternate_consent_type_id', 'question_id', 'answer_value'],
              [
                $this->id,
                $db_aconsent_type->id,
                $db_question->id,
                $qnaire_aconsent_type_trigger->answer_value
              ]
            );

            if( is_null( $db_qnaire_aconsent_type_trigger ) )
            {
              if( $apply )
              {
                $qnaire_aconsent_type_trigger_class_name::create_from_object(
                  $qnaire_alternate_consent_type_trigger,
                  $db_question
                );
              }
              else $add_list[] = $qnaire_aconsent_type_trigger;
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_aconsent_type_trigger as $property => $value )
                if( !in_array( $property, [ 'alternate_consent_type_name', 'question_name' ] ) &&
                    $db_qnaire_aconsent_type_trigger->$property != $qnaire_aconsent_type_trigger->$property )
                  $diff[$property] = $qnaire_aconsent_type_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_aconsent_type_trigger->answer_value = $qnaire_aconsent_type_trigger->answer_value;
                  $db_qnaire_aconsent_type_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s %s [%s]',
                    $qnaire_aconsent_type_trigger->alternate_consent_type_name,
                    $qnaire_aconsent_type_trigger->accept ? 'accept' : 'reject',
                    $qnaire_aconsent_type_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_alternate_consent_type_trigger_object_list()
          as $db_qnaire_aconsent_type_trigger )
        {
          $alternate_consent_type_name = $db_qnaire_aconsent_type_trigger->get_alternate_consent_type()->name;
          $changed_name = array_search(
            $db_qnaire_aconsent_type_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_aconsent_type_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_alternate_consent_type_trigger_list as $qnaire_aconsent_type_trigger )
          {
            // see if the qnaire_alternate_consent_type_trigger exists
            if( ( $alternate_consent_type_name == $qnaire_aconsent_type_trigger->alternate_consent_type_name &&
                  $question_name == $qnaire_aconsent_type_trigger->question_name &&
                  $db_qnaire_aconsent_type_trigger->accept == $qnaire_aconsent_type_trigger->accept ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_aconsent_type_trigger->delete();
            else
            {
              $index = sprintf(
                '%s %s [%s]',
                $alternate_consent_type_name,
                $db_qnaire_aconsent_type_trigger->accept ? 'accept' : 'reject',
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_alternate_consent_type_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_proxy_type_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_proxy_type_trigger_list as $qnaire_proxy_type_trigger )
        {
          $db_proxy_type = $qnaire_proxy_type_trigger->proxy_type_name ?
            $proxy_type_class_name::get_unique_record( 'name', $qnaire_proxy_type_trigger->proxy_type_name ) :
            NULL;

          if( is_null( $db_proxy_type ) && $qnaire_proxy_type_trigger->proxy_type_name )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Proxy trigger for "%s" will be ignore since the proxy type does not exist.',
                $qnaire_proxy_type_trigger->proxy_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_proxy_type_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_proxy_type_trigger->question_name] :
              $qnaire_proxy_type_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_proxy_type_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_proxy_type_trigger = $qnaire_proxy_type_trigger_class_name::get_unique_record(
              ['qnaire_id', 'proxy_type_id', 'question_id', 'answer_value'],
              [
                $this->id,
                is_null( $db_proxy_type ) ? NULL : $db_proxy_type->id,
                $db_question->id,
                $qnaire_proxy_type_trigger->answer_value
              ]
            );

            if( is_null( $db_qnaire_proxy_type_trigger ) )
            {
              if( $apply )
              {
                $qnaire_proxy_type_trigger_class_name::create_from_object(
                  $qnaire_proxy_type_trigger,
                  $db_question
                );
              }
              else
              {
                // if the proxy type name is an empty string then show (empty) instead
                if( !$qnaire_proxy_type_trigger->proxy_type_name )
                  $qnaire_proxy_type_trigger->proxy_type_name = '(empty)';
                $add_list[] = $qnaire_proxy_type_trigger;
              }
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_proxy_type_trigger as $property => $value )
                if( !in_array( $property, [ 'proxy_type_name', 'question_name' ] ) &&
                    $db_qnaire_proxy_type_trigger->$property != $qnaire_proxy_type_trigger->$property )
                  $diff[$property] = $qnaire_proxy_type_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_proxy_type_trigger->answer_value = $qnaire_proxy_type_trigger->answer_value;
                  $db_qnaire_proxy_type_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s [%s]',
                    $qnaire_proxy_type_trigger->proxy_type_name ?
                      $qnaire_proxy_type_trigger->proxy_type_name : '(empty)',
                    $qnaire_proxy_type_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_proxy_type_trigger_object_list() as $db_qnaire_proxy_type_trigger )
        {
          $db_proxy_type = $db_qnaire_proxy_type_trigger->get_proxy_type();
          $proxy_type_name = is_null( $db_proxy_type ) ? '' : $db_proxy_type->name;
          $changed_name = array_search(
            $db_qnaire_proxy_type_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_proxy_type_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_proxy_type_trigger_list as $qnaire_proxy_type_trigger )
          {
            // see if the qnaire_proxy_type_trigger exists
            if( ( $proxy_type_name == $qnaire_proxy_type_trigger->proxy_type_name &&
                  $question_name == $qnaire_proxy_type_trigger->question_name ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_proxy_type_trigger->delete();
            else
            {
              $index = sprintf(
                '%s [%s]',
                $proxy_type_name ? $proxy_type_name : '(empty)',
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_proxy_type_trigger_list'] = $diff_list;
      }
      else if( 'qnaire_equipment_type_trigger_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_equipment_type_trigger_list as $qnaire_equipment_type_trigger )
        {
          $db_equipment_type = $equipment_type_class_name::get_unique_record(
            'name',
            $qnaire_equipment_type_trigger->equipment_type_name
          );

          if( is_null( $db_equipment_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Proxy trigger for "%s" will be ignore since the equipment type does not exist.',
                $qnaire_equipment_type_trigger->equipment_type_name
              );
              $add_list[] = $error;
            }
          }
          else
          {
            $db_question = $this->get_question(
              array_key_exists( $qnaire_equipment_type_trigger->question_name, $change_question_name_list ) ?
              $change_question_name_list[$qnaire_equipment_type_trigger->question_name] :
              $qnaire_equipment_type_trigger->question_name
            );

            // check to see if the question has been renamed as part of the applied patch
            if( $apply && is_null( $db_question ) )
            {
              $db_question = $this->get_question(
                sprintf(
                  '%s_%s',
                  $qnaire_equipment_type_trigger->question_name,
                  $name_suffix
                )
              );
            }

            $db_qnaire_equipment_type_trigger = $qnaire_equipment_type_trigger_class_name::get_unique_record(
              ['qnaire_id', 'equipment_type_id', 'question_id'],
              [$this->id, $db_equipment_type->id, $db_question->id]
            );

            if( is_null( $db_qnaire_equipment_type_trigger ) )
            {
              if( $apply )
              {
                $qnaire_equipment_type_trigger_class_name::create_from_object(
                  $qnaire_equipment_type_trigger,
                  $db_question
                );
              }
              else
              {
                // if the equipment type name is an empty string then show (empty) instead
                if( !$qnaire_equipment_type_trigger->equipment_type_name )
                  $qnaire_equipment_type_trigger->equipment_type_name = '(empty)';
                $add_list[] = $qnaire_equipment_type_trigger;
              }
            }
            else
            {
              // find and add all differences
              $diff = [];
              foreach( $qnaire_equipment_type_trigger as $property => $value )
                if( !in_array( $property, [ 'equipment_type_name', 'question_name' ] ) &&
                    $db_qnaire_equipment_type_trigger->$property != $qnaire_equipment_type_trigger->$property )
                  $diff[$property] = $qnaire_equipment_type_trigger->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply )
                {
                  $db_qnaire_equipment_type_trigger->loaned = $qnaire_equipment_type_trigger->loaned;
                  $db_qnaire_equipment_type_trigger->save();
                }
                else
                {
                  $index = sprintf(
                    '%s [%s]',
                    $qnaire_equipment_type_trigger->equipment_type_name ?
                      $qnaire_equipment_type_trigger->equipment_type_name : '(empty)',
                    $qnaire_equipment_type_trigger->question_name
                  );
                  $change_list[$index] = $diff;
                }
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_equipment_type_trigger_object_list() as $db_qnaire_equipment_type_trigger )
        {
          $db_equipment_type = $db_qnaire_equipment_type_trigger->get_equipment_type();
          $equipment_type_name = is_null( $db_equipment_type ) ? '' : $db_equipment_type->name;
          $changed_name = array_search(
            $db_qnaire_equipment_type_trigger->get_question()->name,
            $change_question_name_list
          );
          $question_name = $changed_name ? $changed_name : $db_qnaire_equipment_type_trigger->get_question()->name;
          if( $apply ) $question_name = preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $question_name );

          $found = false;
          foreach( $patch_object->qnaire_equipment_type_trigger_list as $qnaire_equipment_type_trigger )
          {
            // see if the qnaire_equipment_type_trigger exists
            if( ( $equipment_type_name == $qnaire_equipment_type_trigger->equipment_type_name &&
                  $question_name == $qnaire_equipment_type_trigger->question_name ) )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_equipment_type_trigger->delete();
            else
            {
              $index = sprintf(
                '%s [%s]',
                $equipment_type_name ? $equipment_type_name : '(empty)',
                $question_name
              );
              $remove_list[] = $index;
            }
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_equipment_type_trigger_list'] = $diff_list;
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

      // remove the name suffix from stages
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->join( 'qnaire', 'stage.qnaire_id', 'qnaire.id' );
      $where_mod = lib::create( 'database\modifier' );
      $where_mod->where( 'qnaire.id', '=', $this->id );
      $where_mod->where( 'stage.name', 'LIKE', '%_'.$name_suffix );
      static::db()->execute( sprintf(
        'UPDATE stage %s '.
        'SET stage.name = REPLACE( stage.name, "_%s", "" ) %s',
        $join_mod->get_sql(),
        $name_suffix,
        $where_mod->get_sql()
      ) );

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

    // not patching, so just return the difference list
    return (object)$difference_list;
  }

  /**
   * Exports or prints the qnaire
   * @param string $type One of "export" or "print"
   * @param boolean $return_value Whether to return the generated data or write it to a file
   * @return NULL|string|object
   */
  public function generate( $type = 'export', $return_value = false )
  {
    $separator = "====================================================================================\n\n";
    $qnaire_data = [
      'base_language' => $this->get_base_language()->code,
      'name' => $this->name,
      'version' => $this->version,
      'variable_suffix' => $this->variable_suffix,
      'debug' => $this->debug,
      'readonly' => $this->readonly,
      'allow_in_hold' => $this->allow_in_hold,
      'problem_report' => $this->problem_report,
      'attributes_mandatory' => $this->attributes_mandatory,
      'stages' => $this->stages,
      'repeated' => $this->repeated,
      'repeat_offset' => $this->repeat_offset,
      'max_responses' => $this->max_responses,
      'email_from_name' => $this->email_from_name,
      'email_from_address' => $this->email_from_address,
      'email_invitation' => $this->email_invitation,
      'token_regex' => $this->token_regex,
      'token_check' => $this->token_check,
      'description' => $this->description,
      'note' => $this->note,
      'language_list' => [],
      'attribute_list' => [],
      'qnaire_document_list' => [],
      'embedded_file_list' => [],
      'reminder_list' => [],
      'qnaire_description_list' => [],
      'lookup_list' => []
    ];

    if( $this->stages )
    {
      $qnaire_data['device_list'] = [];
      $qnaire_data['qnaire_report_list'] = [];
      $qnaire_data['deviation_type_list'] = [];
      $qnaire_data['stage_list'] = [];
    }

    // The following properties must come after the optional stage properties
    $qnaire_data['module_list'] = [];
    $qnaire_data['qnaire_participant_trigger_list'] = [];
    $qnaire_data['qnaire_consent_type_confirm_list'] = [];
    $qnaire_data['qnaire_collection_trigger_list'] = [];
    $qnaire_data['qnaire_consent_type_trigger_list'] = [];
    $qnaire_data['qnaire_event_type_trigger_list'] = [];
    $qnaire_data['qnaire_alternate_consent_type_trigger_list'] = [];
    $qnaire_data['qnaire_proxy_type_trigger_list'] = [];
    $qnaire_data['qnaire_equipment_type_trigger_list'] = [];

    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    foreach( $this->get_language_list( $language_sel ) as $item )
      $qnaire_data['language_list'][] = $item['code'];

    $attribute_sel = lib::create( 'database\select' );
    $attribute_sel->add_column( 'name' );
    $attribute_sel->add_column( 'code' );
    $attribute_sel->add_column( 'note' );
    foreach( $this->get_attribute_list( $attribute_sel ) as $item )
      $qnaire_data['attribute_list'][] = $item;

    $qnaire_document_sel = lib::create( 'database\select' );
    $qnaire_document_sel->add_column( 'name' );
    $qnaire_document_sel->add_column( 'data' );
    foreach( $this->get_qnaire_document_list( $qnaire_document_sel ) as $item )
      $qnaire_data['qnaire_document_list'][] = $item;

    $embedded_file_sel = lib::create( 'database\select' );
    $embedded_file_sel->add_column( 'name' );
    $embedded_file_sel->add_column( 'mime_type' );
    $embedded_file_sel->add_column( 'size' );
    $embedded_file_sel->add_column( 'data' );
    foreach( $this->get_embedded_file_list( $embedded_file_sel ) as $item )
      $qnaire_data['embedded_file_list'][] = $item;

    if( $this->stages )
    {
      foreach( $this->get_device_object_list() as $db_device )
      {
        $item = [
          'name' => $db_device->name,
          'url' => $db_device->url,
          'emulate' => $db_device->emulate,
          'device_data_list' => []
        ];

        $data_sel = lib::create( 'database\select' );
        $data_sel->add_column( 'name' );
        $data_sel->add_column( 'code' );
        foreach( $db_device->get_device_data_list( $data_sel ) as $data )
          $item['device_data_list'][] = $data;
        $qnaire_data['device_list'][] = $item;
      }

      foreach( $this->get_qnaire_report_object_list() as $db_qnaire_report )
      {
        $item = [
          'language' => $db_qnaire_report->get_language()->code,
          'data' => $db_qnaire_report->data,
          'qnaire_report_data_list' => []
        ];

        $data_sel = lib::create( 'database\select' );
        $data_sel->add_column( 'name' );
        $data_sel->add_column( 'code' );
        foreach( $db_qnaire_report->get_qnaire_report_data_list( $data_sel ) as $data )
          $item['qnaire_report_data_list'][] = $data;
        $qnaire_data['qnaire_report_list'][] = $item;
      }

      $deviation_type_sel = lib::create( 'database\select' );
      $deviation_type_sel->add_column( 'type' );
      $deviation_type_sel->add_column( 'name' );
      foreach( $this->get_deviation_type_list( $deviation_type_sel ) as $item )
        $qnaire_data['deviation_type_list'][] = $item;
    }

    foreach( $this->get_reminder_object_list() as $db_reminder )
    {
      $item = [
        'delay_offset' => $db_reminder->delay_offset,
        'delay_unit' => $db_reminder->delay_unit,
        'reminder_description_list' => []
      ];

      $description_sel = lib::create( 'database\select' );
      $description_sel->add_table_column( 'language', 'code', 'language' );
      $description_sel->add_column( 'type' );
      $description_sel->add_column( 'value' );
      $description_mod = lib::create( 'database\modifier' );
      $description_mod->join( 'language', 'reminder_description.language_id', 'language.id' );
      $description_mod->order( 'type' );
      $description_mod->order( 'language.code' );
      foreach( $db_reminder->get_reminder_description_list( $description_sel, $description_mod )
        as $description )
      {
        $item['reminder_description_list'][] = $description;
      }

      $qnaire_data['reminder_list'][] = $item;
    }

    $description_sel = lib::create( 'database\select' );
    $description_sel->add_table_column( 'language', 'code', 'language' );
    $description_sel->add_column( 'type' );
    $description_sel->add_column( 'value' );
    $description_mod = lib::create( 'database\modifier' );
    $description_mod->join( 'language', 'qnaire_description.language_id', 'language.id' );
    $description_mod->order( 'type' );
    $description_mod->order( 'language.code' );
    foreach( $this->get_qnaire_description_list( $description_sel, $description_mod ) as $item )
      $qnaire_data['qnaire_description_list'][] = $item;

    $module_mod = lib::create( 'database\modifier' );
    $module_mod->order( 'module.rank' );
    foreach( $this->get_module_object_list( $module_mod ) as $db_module )
    {
      $module = [
        'rank' => $db_module->rank,
        'name' => $db_module->name,
        'precondition' => $db_module->precondition,
        'note' => $db_module->note,
        'module_description_list' => [],
        'page_list' => []
      ];

      $description_sel = lib::create( 'database\select' );
      $description_sel->add_table_column( 'language', 'code', 'language' );
      $description_sel->add_column( 'type' );
      $description_sel->add_column( 'value' );
      $description_mod = lib::create( 'database\modifier' );
      $description_mod->join( 'language', 'module_description.language_id', 'language.id' );
      $description_mod->order( 'type' );
      $description_mod->order( 'language.code' );
      foreach( $db_module->get_module_description_list( $description_sel, $description_mod )
        as $item )
      {
        $module['module_description_list'][] = $item;
      }

      $page_mod = lib::create( 'database\modifier' );
      $page_mod->order( 'page.rank' );
      foreach( $db_module->get_page_object_list( $page_mod ) as $db_page )
      {
        $page = [
          'rank' => $db_page->rank,
          'name' => $db_page->name,
          'precondition' => $db_page->precondition,
          'tabulate' => $db_page->tabulate,
          'note' => $db_page->note,
          'page_description_list' => [],
          'question_list' => []
        ];

        $description_sel = lib::create( 'database\select' );
        $description_sel->add_table_column( 'language', 'code', 'language' );
        $description_sel->add_column( 'type' );
        $description_sel->add_column( 'value' );
        $description_mod = lib::create( 'database\modifier' );
        $description_mod->join( 'language', 'page_description.language_id', 'language.id' );
        $description_mod->order( 'type' );
        $description_mod->order( 'language.code' );
        foreach( $db_page->get_page_description_list( $description_sel, $description_mod ) as $item )
          $page['page_description_list'][] = $item;

        $question_mod = lib::create( 'database\modifier' );
        $question_mod->order( 'question.rank' );
        foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
        {
          $db_device = $db_question->get_device();
          $db_equipment_type = $db_question->get_equipment_type();
          $db_lookup = $db_question->get_lookup();
          $question = [
            'rank' => $db_question->rank,
            'name' => $db_question->name,
            'type' => $db_question->type,
            'export' => $db_question->export,
            'mandatory' => $db_question->mandatory,
            'dkna_allowed' => $db_question->dkna_allowed,
            'refuse_allowed' => $db_question->refuse_allowed,
            'device_name' => is_null( $db_device ) ? NULL : $db_device->name,
            'equipment_type_name' => is_null( $db_equipment_type ) ? NULL : $db_equipment_type->name,
            'lookup_name' => is_null( $db_lookup ) ? NULL : $db_lookup->name,
            'unit_list' => $db_question->unit_list,
            'minimum' => $db_question->minimum,
            'maximum' => $db_question->maximum,
            'default_answer' => $db_question->default_answer,
            'precondition' => $db_question->precondition,
            'note' => $db_question->note,
            'question_description_list' => [],
            'question_option_list' => []
          ];

          if( !is_null( $db_lookup ) )
          {
            // only add lookups once
            $found = false;
            foreach( $qnaire_data['lookup_list'] as $lookup )
            {
              if( $lookup['name'] == $db_lookup->name )
              {
                $found = true;
                break;
              }
            }

            if( !$found )
            {
              $lookup = [
                'name' => $db_lookup->name,
                'version' => $db_lookup->version,
                'description' => $db_lookup->description,
                'indicator_list' => [],
                'lookup_item_list' => []
              ];

              $indicator_sel = lib::create( 'database\select' );
              $indicator_sel->add_column( 'name' );
              $indicator_mod = lib::create( 'database\modifier' );
              $indicator_mod->order( 'name' );
              foreach( $db_lookup->get_indicator_list( $indicator_sel, $indicator_mod ) as $indicator )
              {
                $lookup['indicator_list'][] = ['name' => $indicator['name']];
              }

              $lookup_item_sel = lib::create( 'database\select' );
              $lookup_item_sel->add_column( 'identifier' );
              $lookup_item_sel->add_column( 'name' );
              $lookup_item_sel->add_column( 'description' );
              $lookup_item_sel->add_column(
                'GROUP_CONCAT( indicator.name ORDER BY indicator.name )',
                'indicator_list',
                false
              );
              $lookup_item_mod = lib::create( 'database\modifier' );
              $lookup_item_mod->left_join(
                'indicator_has_lookup_item',
                'lookup_item.id',
                'indicator_has_lookup_item.lookup_item_id'
              );
              $lookup_item_mod->left_join(
                'indicator',
                'indicator_has_lookup_item.indicator_id',
                'indicator.id'
              );
              $lookup_item_mod->group( 'lookup_item.id' );
              $lookup_item_mod->order( 'identifier' );
              foreach( $db_lookup->get_lookup_item_list( $lookup_item_sel, $lookup_item_mod ) as $lookup_item )
              {
                $item = [
                  'identifier' => $lookup_item['identifier'],
                  'name' => $lookup_item['name'],
                  'description' => $lookup_item['description'],
                  'indicator_list' => []
                ];

                foreach( explode( ',', $lookup_item['indicator_list'] ) as $indicator )
                {
                  $indicator = trim( $indicator, '"' );
                  if( 0 < strlen( $indicator ) ) $item['indicator_list'][] = $indicator;
                }

                $lookup['lookup_item_list'][] = $item;
              }

              $qnaire_data['lookup_list'][] = $lookup;
            }
          }

          $description_sel = lib::create( 'database\select' );
          $description_sel->add_table_column( 'language', 'code', 'language' );
          $description_sel->add_column( 'type' );
          $description_sel->add_column( 'value' );
          $description_mod = lib::create( 'database\modifier' );
          $description_mod->join( 'language', 'question_description.language_id', 'language.id' );
          $description_mod->order( 'type' );
          $description_mod->order( 'language.code' );
          foreach( $db_question->get_question_description_list( $description_sel, $description_mod ) as $item )
          {
            $question['question_description_list'][] = $item;
          }

          $question_option_mod = lib::create( 'database\modifier' );
          $question_option_mod->order( 'question_option.rank' );
          foreach( $db_question->get_question_option_object_list( $question_option_mod ) as $db_question_option )
          {
            $question_option = [
              'rank' => $db_question_option->rank,
              'name' => $db_question_option->name,
              'exclusive' => $db_question_option->exclusive,
              'extra' => $db_question_option->extra,
              'multiple_answers' => $db_question_option->multiple_answers,
              'unit_list' => $db_question_option->unit_list,
              'minimum' => $db_question_option->minimum,
              'maximum' => $db_question_option->maximum,
              'precondition' => $db_question_option->precondition,
              'question_option_description_list' => []
            ];

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

    if( $this->stages )
    {
      $stage_sel = lib::create( 'database\select' );
      $stage_sel->add_column( 'rank' );
      $stage_sel->add_column( 'name' );
      $stage_sel->add_table_column( 'first_module', 'rank', 'first_module_rank' );
      $stage_sel->add_table_column( 'last_module', 'rank', 'last_module_rank' );
      $stage_sel->add_column( 'precondition' );
      $stage_mod = lib::create( 'database\modifier' );
      $stage_mod->join( 'module', 'stage.first_module_id', 'first_module.id', '', 'first_module' );
      $stage_mod->join( 'module', 'stage.last_module_id', 'last_module.id', '', 'last_module' );
      $stage_mod->order( 'stage.rank' );
      foreach( $this->get_stage_list( $stage_sel, $stage_mod ) as $item ) $qnaire_data['stage_list'][] = $item;
    }

    $confirm_sel = lib::create( 'database\select' );
    $confirm_sel->add_table_column( 'consent_type', 'name', 'consent_type_name' );
    $confirm_mod = lib::create( 'database\modifier' );
    $confirm_mod->join(
      'consent_type',
      'qnaire_consent_type_confirm.consent_type_id',
      'consent_type.id'
    );
    foreach( $this->get_qnaire_consent_type_confirm_list( $confirm_sel, $confirm_mod ) as $item )
      $qnaire_data['qnaire_consent_type_confirm_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_sel->add_column( 'column_name' );
    $trigger_sel->add_column( 'value' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->join( 'question', 'qnaire_participant_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_participant_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_participant_trigger_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_table_column( 'collection', 'name', 'collection_name' );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_sel->add_column( 'add_to' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->join(
      'collection',
      'qnaire_collection_trigger.collection_id',
      'collection.id'
    );
    $trigger_mod->join( 'question', 'qnaire_collection_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_collection_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_collection_trigger_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_table_column( 'consent_type', 'name', 'consent_type_name' );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_sel->add_column( 'accept' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->join(
      'consent_type',
      'qnaire_consent_type_trigger.consent_type_id',
      'consent_type.id'
    );
    $trigger_mod->join( 'question', 'qnaire_consent_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_consent_type_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_consent_type_trigger_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_table_column( 'event_type', 'name', 'event_type_name' );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->join(
      'event_type',
      'qnaire_event_type_trigger.event_type_id',
      'event_type.id'
    );
    $trigger_mod->join( 'question', 'qnaire_event_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_event_type_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_event_type_trigger_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_table_column( 'alternate_consent_type', 'name', 'alternate_consent_type_name' );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_sel->add_column( 'accept' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->join(
      'alternate_consent_type',
      'qnaire_alternate_consent_type_trigger.alternate_consent_type_id',
      'alternate_consent_type.id'
    );
    $trigger_mod->join( 'question', 'qnaire_alternate_consent_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_alternate_consent_type_trigger_list( $trigger_sel, $trigger_mod )
      as $item )
    {
      $qnaire_data['qnaire_alternate_consent_type_trigger_list'][] = $item;
    }

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_column( 'IFNULL( proxy_type.name, "" )', 'proxy_type_name', false );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'answer_value' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->left_join( 'proxy_type', 'qnaire_proxy_type_trigger.proxy_type_id', 'proxy_type.id' );
    $trigger_mod->join( 'question', 'qnaire_proxy_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_proxy_type_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_proxy_type_trigger_list'][] = $item;

    $trigger_sel = lib::create( 'database\select' );
    $trigger_sel->add_column( 'IFNULL( equipment_type.name, "" )', 'equipment_type_name', false );
    $trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $trigger_sel->add_column( 'loaned' );
    $trigger_mod = lib::create( 'database\modifier' );
    $trigger_mod->left_join(
      'equipment_type',
      'qnaire_equipment_type_trigger.equipment_type_id',
      'equipment_type.id'
    );
    $trigger_mod->join( 'question', 'qnaire_equipment_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_equipment_type_trigger_list( $trigger_sel, $trigger_mod ) as $item )
      $qnaire_data['qnaire_equipment_type_trigger_list'][] = $item;

    if( 'export' == $type )
    {
      $filename = sprintf( '%s/qnaire_export_%d.json', TEMP_PATH, $this->id );
      $contents = util::json_encode( $qnaire_data, JSON_PRETTY_PRINT );
    }
    else // print
    {
      $filename = sprintf( '%s/qnaire_print_%d.txt', TEMP_PATH, $this->id );
      $contents = sprintf(
        "%s (%s)\n",
        $qnaire_data['name'],
        is_null( $qnaire_data['version'] ) ?
          'no version specified' : sprintf( 'version %s', $qnaire_data['version'] )
      ) . $separator;
      if( $qnaire_data['description'] )$contents .= sprintf( "%s\n\n", $qnaire_data['description'] );

      $description = ['introduction' => [], 'conclusion' => [], 'closed' => []];
      foreach( $qnaire_data['qnaire_description_list'] as $d )
        if( in_array( $d['type'], ['introduction', 'conclusion', 'closed'] ) )
          $description[$d['type']][$d['language']] = $d['value'];

      $contents .= sprintf( "INTRODUCTION\n" ) . $separator;
      foreach( $description['introduction'] as $language => $value )
        $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      $contents .= sprintf( "CONCLUSION\n" ) . $separator;
      foreach( $description['conclusion'] as $language => $value )
        $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      $contents .= sprintf( "CLOSED\n" ) . $separator;
      foreach( $description['closed'] as $language => $value )
        $contents .= sprintf( "[%s] %s\n\n", $language, $value );

      foreach( $qnaire_data['module_list'] as $module )
      {
        $contents .= sprintf(
          "%d) MODULE %s%s\n",
          $module['rank'],
          $module['name'],
          is_null( $module['precondition'] ) ? '' : sprintf( ' (precondition: %s)', $module['precondition'] )
        ) . $separator;

        $description = ['prompt' => [], 'popup' => []];
        foreach( $module['module_description_list'] as $d )
          $description[$d['type']][$d['language']] = $d['value'];

        foreach( $description['prompt'] as $language => $value )
        {
          $contents .= sprintf(
            "[%s] %s%s\n",
            $language,
            $value,
            is_null( $description['popup'][$language] ) ?
              '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
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
          ) . $separator;

          $description = ['prompt' => [], 'popup' => []];
          foreach( $page['page_description_list'] as $d ) $description[$d['type']][$d['language']] = $d['value'];

          foreach( $description['prompt'] as $language => $value )
          {
            $contents .= sprintf(
              "[%s] %s%s\n",
              $language,
              $value,
              is_null( $description['popup'][$language] ) ?
                '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
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
              is_null(
                $question['precondition'] ) ?
                  '' : sprintf( ' (precondition: %s)', $question['precondition']
              )
            ) . $separator;

            $description = ['prompt' => [], 'popup' => []];
            foreach( $question['question_description_list'] as $d )
              $description[$d['type']][$d['language']] = $d['value'];

            foreach( $description['prompt'] as $language => $value )
            {
              $contents .= sprintf(
                "[%s] %s%s\n",
                $language,
                $value,
                is_null( $description['popup'][$language] ) ?
                  '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language] )
              );
            }
            $contents .= "\n";

            if( array_key_exists( 'question_option_list', $question ) &&
                0 < count( $question['question_option_list'] ) )
            {
              foreach( $question['question_option_list'] as $question_option )
              {
                $contents .= sprintf(
                  "OPTION #%d, %s%s:\n",
                  $question_option['rank'],
                  $question_option['name'],
                  is_null(
                    $question_option['precondition'] ? '' :
                      sprintf( ' (precondition: %s)', $question_option['precondition'] )
                  )
                );

                $description = ['prompt' => [], 'popup' => []];
                foreach( $question_option['question_option_description_list'] as $d )
                  $description[$d['type']][$d['language']] = $d['value'];

                foreach( $description['prompt'] as $language => $value )
                {
                  $contents .= sprintf(
                    "[%s] %s%s\n",
                    $language,
                    $value,
                    is_null(
                      $description['popup'][$language] ) ?
                        '' : sprintf( "\n\nPOPUP: %s", $description['popup'][$language]
                    )
                  );
                }
                $contents .= "\n";
              }
            }
          }
        }
      }
    }

    if( $return_value )
    {
      return $contents;
    }
    else if( false === file_put_contents( $filename, $contents, LOCK_EX ) )
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
    ini_set( 'memory_limit', '2G' );
    set_time_limit( 900 ); // 15 minutes max

    $language_class_name = lib::get_class_name( 'database\language' );
    $reminder_class_name = lib::get_class_name( 'database\reminder' );
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $qnaire_document_class_name = lib::get_class_name( 'database\qnaire_document' );
    $embedded_file_class_name = lib::get_class_name( 'database\embedded_file' );
    $collection_class_name = lib::get_class_name( 'database\collection' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $event_type_class_name = lib::get_class_name( 'database\event_type' );
    $alternate_consent_type_class_name = lib::get_class_name( 'database\alternate_consent_type' );
    $proxy_type_class_name = lib::get_class_name( 'database\proxy_type' );
    $equipment_type_class_name = lib::get_class_name( 'database\equipment_type' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $stage_class_name = lib::get_class_name( 'database\stage' );
    $device_class_name = lib::get_class_name( 'database\device' );
    $qnaire_report_class_name = lib::get_class_name( 'database\qnaire_report' );
    $deviation_type_class_name = lib::get_class_name( 'database\deviation_type' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );
    $qnaire_consent_type_confirm_class_name = lib::get_class_name( 'database\qnaire_consent_type_confirm' );
    $qnaire_participant_trigger_class_name = lib::get_class_name( 'database\qnaire_participant_trigger' );
    $qnaire_collection_trigger_class_name = lib::get_class_name( 'database\qnaire_collection_trigger' );
    $qnaire_consent_type_trigger_class_name = lib::get_class_name( 'database\qnaire_consent_type_trigger' );
    $qnaire_event_type_trigger_class_name = lib::get_class_name( 'database\qnaire_event_type_trigger' );
    $qnaire_aconsent_type_trigger_class_name =
      lib::get_class_name( 'database\qnaire_alternate_consent_type_trigger' );
    $qnaire_proxy_type_trigger_class_name = lib::get_class_name( 'database\qnaire_proxy_type_trigger' );
    $qnaire_equipment_type_trigger_class_name = lib::get_class_name( 'database\qnaire_equipment_type_trigger' );

    $default_page_max_time =
      lib::create( 'business\setting_manager' )->get_setting( 'general', 'default_page_max_time' );

    // make sure the qnaire doesn't already exist
    $db_qnaire = static::get_unique_record( 'name', $qnaire_object->name );
    if( !is_null( $db_qnaire ) )
    {
      throw lib::create( 'exception\notice',
        sprintf(
          'A questionnaire named "%s" already exists. '.
          ' Please make sure to rename the questionnaire you are trying to import a different name, '.
          'or patch the existing questionnaire instead.',
          $qnaire_object->name
        ),
        __METHOD__
      );
    }

    $db_qnaire = lib::create( 'database\qnaire' );
    $db_qnaire->base_language_id =
      $language_class_name::get_unique_record( 'code', $qnaire_object->base_language )->id;
    $db_qnaire->name = $qnaire_object->name;
    $db_qnaire->version = property_exists( $qnaire_object, 'version' ) ? $qnaire_object->version : NULL;
    $db_qnaire->variable_suffix = $qnaire_object->variable_suffix;
    $db_qnaire->debug = $qnaire_object->debug;
    $db_qnaire->allow_in_hold = $qnaire_object->allow_in_hold;
    $db_qnaire->problem_report = $qnaire_object->problem_report;
    $db_qnaire->attributes_mandatory = $qnaire_object->attributes_mandatory;
    $db_qnaire->stages = $qnaire_object->stages;
    $db_qnaire->repeated = $qnaire_object->repeated;
    $db_qnaire->repeat_offset = $qnaire_object->repeat_offset;
    $db_qnaire->max_responses = $qnaire_object->max_responses;
    $db_qnaire->email_from_name = $qnaire_object->email_from_name;
    $db_qnaire->email_from_address = $qnaire_object->email_from_address;
    $db_qnaire->email_invitation = $qnaire_object->email_invitation;
    $db_qnaire->token_regex = $qnaire_object->token_regex;
    $db_qnaire->token_check = $qnaire_object->token_check;
    $db_qnaire->description = $qnaire_object->description;
    $db_qnaire->note = $qnaire_object->note;
    $db_qnaire->save();

    foreach( $qnaire_object->language_list as $language )
      $db_qnaire->add_language( $language_class_name::get_unique_record( 'code', $language )->id );

    foreach( $qnaire_object->reminder_list as $reminder )
      $reminder_class_name::create_from_object( $reminder, $db_qnaire );

    foreach( $qnaire_object->attribute_list as $attribute )
      $attribute_class_name::create_from_object( $attribute, $db_qnaire );

    foreach( $qnaire_object->qnaire_document_list as $qnaire_document )
      $qnaire_document_class_name::create_from_object( $qnaire_document, $db_qnaire );

    foreach( $qnaire_object->embedded_file_list as $embedded_file )
      $embedded_file_class_name::create_from_object( $embedded_file, $db_qnaire );

    if( $db_qnaire->stages )
    {
      foreach( $qnaire_object->device_list as $device )
        $device_class_name::create_from_object( $device, $db_qnaire );

      foreach( $qnaire_object->qnaire_report_list as $qnaire_report )
        $qnaire_report_class_name::create_from_object( $qnaire_report, $db_qnaire );

      foreach( $qnaire_object->deviation_type_list as $deviation_type )
        $deviation_type_class_name::create_from_object( $deviation_type, $db_qnaire );
    }

    foreach( $qnaire_object->qnaire_description_list as $qnaire_description )
    {
      $db_language = $language_class_name::get_unique_record( 'code', $qnaire_description->language );
      $db_qnaire_description = $db_qnaire->get_description( $qnaire_description->type, $db_language );
      $db_qnaire_description->value = $qnaire_description->value;
      $db_qnaire_description->save();
    }

    foreach( $qnaire_object->lookup_list as $lookup )
    {
      $db_existing_lookup = $lookup_class_name::get_unique_record( 'name', $lookup->name );
      if( is_null( $db_existing_lookup ) )
      {
        $lookup_class_name::create_from_object( $lookup );
      }
      else if( $db_existing_lookup->version != $lookup->version )
      {
        // delete and re-create the entire lookup
        $db_existing_lookup->delete();
        $lookup_class_name::create_from_object( $lookup );
      }
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
        $db_page->tabulate = $page_object->tabulate;
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
          $db_device = is_null( $question_object->device_name )
                     ? NULL
                     : $device_class_name::get_unique_record(
                         ['qnaire_id', 'name'],
                         [$db_qnaire->id, $question_object->device_name]
                       );

          $db_equipment_type = is_null( $question_object->equipment_type_name )
                     ? NULL
                     : $equipment_type_class_name::get_unique_record(
                         'name',
                         $question_object->equipment_type_name
                       );

          $db_lookup = is_null( $question_object->lookup_name )
                     ? NULL
                     : $lookup_class_name::get_unique_record( 'name', $question_object->lookup_name );

          $db_question = lib::create( 'database\question' );
          $db_question->page_id = $db_page->id;
          $db_question->rank = $question_object->rank;
          $db_question->name = $question_object->name;
          $db_question->type = $question_object->type;
          $db_question->export = $question_object->export;
          $db_question->mandatory = $question_object->mandatory;
          $db_question->dkna_allowed = $question_object->dkna_allowed;
          $db_question->refuse_allowed = $question_object->refuse_allowed;
          if( !is_null( $db_device ) ) $db_question->device_id = $db_device->id;
          if( !is_null( $db_equipment_type ) ) $db_question->equipment_type_id = $db_equipment_type->id;
          if( !is_null( $db_lookup ) ) $db_question->lookup_id = $db_lookup->id;
          $db_question->unit_list = $question_object->unit_list;
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
              $db_question_option->unit_list = $question_option_object->unit_list;
              $db_question_option->minimum = $question_option_object->minimum;
              $db_question_option->maximum = $question_option_object->maximum;
              $db_question_option->precondition = $question_option_object->precondition;
              $db_question_option->save();

              foreach( $question_option_object->question_option_description_list as $question_option_description )
              {
                $db_language =
                  $language_class_name::get_unique_record( 'code', $question_option_description->language );
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

    if( $db_qnaire->stages )
    {
      foreach( $qnaire_object->stage_list as $stage )
      {
        $db_stage = lib::create( 'database\stage' );
        $db_stage->qnaire_id = $db_qnaire->id;
        $db_stage->rank = $stage->rank;
        $db_stage->name = $stage->name;

        $db_first_module = $module_class_name::get_unique_record(
          ['qnaire_id', 'rank'],
          [$db_qnaire->id, $stage->first_module_rank]
        );
        if( is_null( $db_first_module ) )
        {
          throw lib::create( 'exception\notice',
            sprintf(
              'Import file references rank %d as first module for stage "%s", but module does not exist.',
              $stage->first_module_rank,
              $stage->name
            ),
            __METHOD__
          );
        }

        $db_last_module = $module_class_name::get_unique_record(
          ['qnaire_id', 'rank'],
          [$db_qnaire->id, $stage->last_module_rank]
        );
        if( is_null( $db_last_module ) )
        {
          throw lib::create( 'exception\notice',
            sprintf(
              'Import file references rank %d as last module for stage "%s", but module does not exist.',
              $stage->last_module_rank,
              $stage->name
            ),
            __METHOD__
          );
        }

        $db_stage->first_module_id = $db_first_module->id;
        $db_stage->last_module_id = $db_last_module->id;
        $db_stage->precondition = $stage->precondition;

        // There may already be a stage by this name automatically created when modules were imported.
        // If so we must delete it before saving this record
        $db_old_stage = $stage_class_name::get_unique_record(
          ['qnaire_id', 'name'],
          [$db_qnaire->id, $db_stage->name]
        );
        if( !is_null( $db_old_stage ) ) $db_old_stage->delete();

        $db_stage->save();
      }

      // now delete any stages which weren't in the import object
      foreach( $db_qnaire->get_stage_object_list() as $db_stage )
      {
        $found = false;
        foreach( $qnaire_object->stage_list as $stage )
        {
          if( $stage->rank == $db_stage->rank )
          {
            $found = true;
            break;
          }
        }

        if( !$found ) $db_stage->delete();
      }
    }

    foreach( $qnaire_object->qnaire_consent_type_confirm_list as $qnaire_consent_type_confirm )
    {
      $db_consent_type =
        $consent_type_class_name::get_unique_record( 'name', $qnaire_consent_type_confirm->consent_type_name );
      if( is_null( $db_consent_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a consent confirm '.
            'for consent type "%s" which does not exist.',
            $qnaire_consent_type_confirm->consent_type_name
          ),
          __METHOD__
        );
      }

      $qnaire_consent_type_confirm_class_name::create_from_object( $qnaire_consent_type_confirm, $db_qnaire );
    }

    foreach( $qnaire_object->qnaire_participant_trigger_list as $qnaire_participant_trigger )
    {
      $db_question = $db_qnaire->get_question( $qnaire_participant_trigger->question_name );
      $qnaire_participant_trigger_class_name::create_from_object( $qnaire_participant_trigger, $db_question );
    }

    foreach( $qnaire_object->qnaire_collection_trigger_list as $qnaire_collection_trigger )
    {
      $db_collection = $collection_class_name::get_unique_record(
        'name',
        $qnaire_collection_trigger->collection_name
      );
      if( is_null( $db_collection ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a collection trigger '.
            'for collection type "%s" which does not exist.',
            $qnaire_collection_trigger->collection_name
          ),
          __METHOD__
        );
      }

      $db_question = $db_qnaire->get_question( $qnaire_collection_trigger->question_name );
      $qnaire_collection_trigger_class_name::create_from_object( $qnaire_collection_trigger, $db_question );
    }

    foreach( $qnaire_object->qnaire_consent_type_trigger_list as $qnaire_consent_type_trigger )
    {
      $db_consent_type = $consent_type_class_name::get_unique_record(
        'name',
        $qnaire_consent_type_trigger->consent_type_name
      );
      if( is_null( $db_consent_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a consent trigger '.
            'for consent type "%s" which does not exist.',
            $qnaire_consent_type_trigger->consent_type_name
          ),
          __METHOD__
        );
      }

      $db_question = $db_qnaire->get_question( $qnaire_consent_type_trigger->question_name );
      $qnaire_consent_type_trigger_class_name::create_from_object( $qnaire_consent_type_trigger, $db_question );
    }

    foreach( $qnaire_object->qnaire_event_type_trigger_list as $qnaire_event_type_trigger )
    {
      $db_event_type = $event_type_class_name::get_unique_record(
        'name',
        $qnaire_event_type_trigger->event_type_name
      );
      if( is_null( $db_event_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a event trigger '.
            'for event type "%s" which does not exist.',
            $qnaire_event_type_trigger->event_type_name
          ),
          __METHOD__
        );
      }

      $db_question = $db_qnaire->get_question( $qnaire_event_type_trigger->question_name );
      $qnaire_event_type_trigger_class_name::create_from_object( $qnaire_event_type_trigger, $db_question );
    }

    foreach( $qnaire_object->qnaire_alternate_consent_type_trigger_list
      as $qnaire_alternate_consent_type_trigger )
    {
      $db_alternate_consent_type = $alternate_consent_type_class_name::get_unique_record(
        'name',
        $qnaire_alternate_consent_type_trigger->alternate_consent_type_name
      );
      if( is_null( $db_alternate_consent_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a alternate_consent trigger for '.
            'alternate_consent type "%s" which does not exist.',
            $qnaire_alternate_consent_type_trigger->alternate_consent_type_name
          ),
          __METHOD__
        );
      }

      $db_question = $db_qnaire->get_question( $qnaire_alternate_consent_type_trigger->question_name );
      $qnaire_aconsent_type_trigger_class_name::create_from_object(
        $qnaire_alternate_consent_type_trigger,
        $db_question
      );
    }

    foreach( $qnaire_object->qnaire_proxy_type_trigger_list as $qnaire_proxy_type_trigger )
    {
      $db_proxy_type = NULL;
      if( $qnaire_proxy_type_trigger->proxy_type_name )
      {
        $db_proxy_type = $proxy_type_class_name::get_unique_record(
          'name',
          $qnaire_proxy_type_trigger->proxy_type_name
        );
        if( is_null( $db_proxy_type ) )
        {
          throw lib::create( 'exception\notice',
            sprintf(
              'Unable to import questionnaire since it has a proxy trigger '.
              'for proxy type "%s" which does not exist.',
              $qnaire_proxy_type_trigger->proxy_type_name
            ),
            __METHOD__
          );
        }
      }

      $db_question = $db_qnaire->get_question( $qnaire_proxy_type_trigger->question_name );
      $qnaire_proxy_type_trigger_class_name::create_from_object( $qnaire_proxy_type_trigger, $db_question );
    }

    foreach( $qnaire_object->qnaire_equipment_type_trigger_list as $qnaire_equipment_type_trigger )
    {
      $db_equipment_type = NULL;
      if( $qnaire_equipment_type_trigger->equipment_type_name )
      {
        $db_equipment_type = $equipment_type_class_name::get_unique_record(
          'name',
          $qnaire_equipment_type_trigger->equipment_type_name
        );
        if( is_null( $db_equipment_type ) )
        {
          throw lib::create( 'exception\notice',
            sprintf(
              'Unable to import questionnaire since it has a equipment trigger '.
              'for equipment type "%s" which does not exist.',
              $qnaire_equipment_type_trigger->equipment_type_name
            ),
            __METHOD__
          );
        }
      }

      $db_question = $db_qnaire->get_question( $qnaire_equipment_type_trigger->question_name );
      $qnaire_equipment_type_trigger_class_name::create_from_object(
        $qnaire_equipment_type_trigger,
        $db_question
      );
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

  /**
   * Returns the directory that uploaded response data to this qnaire is written to
   */
  public function get_data_directory()
  {
    return sprintf( '%s/%s', RESPONSE_DATA_PATH, $this->name );
  }

  /**
   * Returns the old data directory (used by save() before updating the record)
   */
  protected function get_old_data_directory()
  {
    return sprintf( '%s/%s', RESPONSE_DATA_PATH, $this->get_passive_column_value( 'name' ) );
  }
}
