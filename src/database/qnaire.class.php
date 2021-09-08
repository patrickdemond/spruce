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
      array( 'qnaire_id', 'rank' ),
      array( $this->id, $this->get_module_count() )
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
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );

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

    // copy all reminders
    foreach( $db_source_qnaire->get_reminder_object_list() as $db_source_reminder )
    {
      $db_reminder = lib::create( 'database\reminder' );
      $db_reminder->qnaire_id = $this->id;
      $db_reminder->offset = $db_source_reminder->offset;
      $db_reminder->unit = $db_source_reminder->unit;
      $db_reminder->save();

      foreach( $db_source_reminder->get_reminder_description_object_list() as $db_source_reminder_description )
      {
        $db_reminder_description = $reminder_description_class_name::get_unique_record(
          array( 'reminder_id', 'language_id', 'type' ),
          array( $db_reminder->id, $db_source_reminder_description->language_id, $db_source_reminder_description->type )
        );
        $db_reminder_description->value = $db_source_reminder_description->value;
        $db_reminder_description->save();
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
    foreach( $db_source_qnaire->get_deviation_type_list( $deviation_sel, $deviation_mod ) as $db_source_deviation_type )
    {
      $db_deviation_type = lib::create( 'database\deviation_type' );
      $db_deviation_type->qnaire_id = $this->id;
      $db_deviation_type->type = $db_source_devaition_type->type;
      $db_deviation_type->name = $db_source_devaition_type->name;
      $db_deviation_type->save();
    }

    // copy all consent confirms
    foreach( $db_source_qnaire->get_qnaire_consent_type_confirm_object_list() as $db_source_consent_type )
    {
      $db_question = $this->get_question( $db_source_consent_type->get_question()->name );
      $db_qnaire_consent_type_confirm = lib::create( 'database\qnaire_consent_type_confirm' );
      $db_qnaire_consent_type_confirm->qnaire_id = $this->id;
      $db_qnaire_consent_type_confirm->consent_type_id = $db_source_consent_type->consent_type_id;
      $db_qnaire_consent_type_confirm->save();
    }

    // copy all consent confirms
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
   * Updates all preconditions refering to a question name to a new name
   * @param string type One of "question" or "question_option"
   * @param string $old_name The question or option's old name
   * @param string $new_name The question or option's new name
   */
  public function update_name_in_preconditions( $type, $old_name, $new_name )
  {
    // The sql regex match depends on what type of change we're making
    // Questions will all start with a $ and end with either a $ (for direct references), : (for options), or . (for functions)
    // Stages will all start and end with a #
    $match = '';
    if( 'stage' == $type )
    {
      $match = sprintf( '#%s#', $old_name );
      $replace = sprintf( 'REPLACE( %%s.precondition, "#%s#", "#%s#" )', $old_name, $new_name );
    }
    else
    {
      $match = sprintf( 'question' == $type ? '\\$%s[$:.]' : ':%s\\$', $old_name );

      // The replacement syntax is also different for question or question-options
      $replace = 'question' == $type
               ? sprintf(
                   'REPLACE( REPLACE( REPLACE( %%s.precondition, "$%s$", "$%s$" ), "$%s:", "$%s:" ), "$%s.", "$%s." )',
                   $old_name, $new_name, $old_name, $new_name, $old_name, $new_name
                 )
               : sprintf( 'REPLACE( %%s.precondition, ":%s$", ":%s$" )', $old_name, $new_name );
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
                          array( 'identifier_id', 'value' ),
                          array( $db_identifier->id, $identifier )
                        )->get_participant();
      $db_respondent = $respondent_class_name::get_unique_record(
        array( 'qnaire_id', 'participant_id' ),
        array( $this->id, $db_participant->id )
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
    if( is_null( PARENT_INSTANCE_URL ) ) return 'This instance of Pine is not detached so there is no remote connection to test.';

    // test the beartooth connection
    $url = sprintf( '%s/api/appointment', $this->beartooth_url );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
    curl_setopt(
      $curl,
      CURLOPT_HTTPHEADER,
      array(
        sprintf(
          'Authorization: Basic %s',
          base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
        )
      )
    );

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
    else if( 300 <= $code )
    {
      return sprintf( 'Got response code %s when connecting to Beartooth server.', $code );
    }

    // now test the pine connection
    $url = sprintf(
      '%s/api/qnaire/name=%s?select={"column":["version"]}',
      PARENT_INSTANCE_URL,
      util::full_urlencode( $this->name )
    );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
    curl_setopt(
      $curl,
      CURLOPT_HTTPHEADER,
      array( sprintf(
        'Authorization: Basic %s',
        base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
      ) )
    );

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
    else if( 300 <= $code )
    {
      return sprintf( 'Got error code %s when connecting to parent Pine server.', $code );
    }

    return 'Successfully connected to Beartooth and parent Pine servers.';
  }

  /**
   * Synchronizes this qnaire with the qnaire belonging to the parent instance
   */
  public function sync_with_parent()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    $url = sprintf(
      '%s/api/qnaire/name=%s?select={"column":["version"]}',
      PARENT_INSTANCE_URL,
      util::full_urlencode( $this->name )
    );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
    curl_setopt(
      $curl,
      CURLOPT_HTTPHEADER,
      array( sprintf(
        'Authorization: Basic %s',
        base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
      ) )
    );

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when synchronizing qnaire with parent instance.  Message: %s',
                 curl_errno( $curl ),
                 curl_error( $curl ) ),
        __METHOD__
      );
    }

    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 401 == $code )
    {
      throw lib::create( 'exception\notice',
        'Unable to synchronize questionnaire, invalid Beartooth username and/or password.',
        __METHOD__
      );
    }
    else if( 404 == $code )
    {
      // ignore missing qnaires, it just means the parent doesn't have it
      log::info( sprintf( 'Questionnaire "%s" was not found in the parent instance, can\'t synchronize.', $this->name ) );
    }
    else if( 300 <= $code )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when synchronizing qnaire with parent instance.', $code ),
        __METHOD__
      );
    }
    else
    {
      $parent_qnaire = util::json_decode( $response );

      if( $this->version != $parent_qnaire->version )
      {
        // if the version is different then download the parent qnaire and apply it as a patch
        $old_version = $this->version;
        $new_version = $parent_qnaire->version;

        $url = sprintf(
          '%s/api/qnaire/name=%s?output=export&download=true',
          PARENT_INSTANCE_URL,
          util::full_urlencode( $this->name )
        );
        $curl = curl_init();
        curl_setopt( $curl, CURLOPT_URL, $url );
        curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
        curl_setopt(
          $curl,
          CURLOPT_HTTPHEADER,
          array( sprintf(
            'Authorization: Basic %s',
            base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
          ) )
        );

        $response = curl_exec( $curl );
        if( curl_errno( $curl ) )
        {
          throw lib::create( 'exception\runtime',
            sprintf( 'Got error code %s when synchronizing qnaire with parent instance (export).  Message: %s',
                     curl_errno( $curl ),
                     curl_error( $curl ) ),
            __METHOD__
          );
        }

        $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
        if( 300 <= $code )
        {
          throw lib::create( 'exception\runtime',
            sprintf( 'Got error code %s when synchronizing qnaire with parent instance (export).', $code ),
            __METHOD__
          );
        }
        else
        {
          $parent_qnaire = util::json_decode( $response );
          $this->process_patch( $parent_qnaire, true );
          log::info( sprintf(
            'Questionnaire "%s" has been upgraded from version "%s" to "%s".',
            $this->name,
            $old_version,
            $new_version
          ) );
        }
      }
    }
  }

  /**
   * Sends response data to a parent instance of Pine
   */
  public function export_response_data()
  {
    if( is_null( PARENT_INSTANCE_URL ) ) return;

    // encode all respondent and response data into an array
    $respondent_list = array();
    $respondent_mod = lib::create( 'database\modifier' );
    $respondent_mod->order( 'id' );
    foreach( $this->get_respondent_object_list( $respondent_mod ) as $db_respondent )
    {
      $respondent = array(
        'uid' => $db_respondent->get_participant()->uid,
        'token' => $db_respondent->token,
        'start_datetime' => $db_respondent->start_datetime->format( 'c' ),
        'end_datetime' => $db_respondent->end_datetime->format( 'c' ),
        'response_list' => array()
      );

      $response_mod = lib::create( 'database\modifier' );
      $response_mod->order( 'rank' );
      foreach( $db_respondent->get_response_object_list( $response_mod ) as $db_response )
      {
        $db_page = $db_response->get_page();
        $db_module = is_null( $db_page ) ? NULL : $db_page->get_module();
        $response = array(
          'rank' => $db_response->rank,
          'qnaire_version' => $db_response->qnaire_version,
          'language' => $db_response->get_language()->code,
          'module' => is_null( $db_module ) ? NULL : $db_module->name,
          'page' => is_null( $db_page ) ? NULL : $db_page->name,
          'submitted' => $db_response->submitted,
          'show_hidden' => $db_response->show_hidden,
          'start_datetime' => $db_response->start_datetime->format( 'c' ),
          'last_datetime' => $db_response->last_datetime->format( 'c' ),
          'answer_list' => array()
        );

        foreach( $db_response->get_answer_object_list() as $db_answer )
        {
          $response['answer_list'][] = array(
            'question' => $db_answer->get_question()->name,
            'language' => $db_answer->get_language()->code,
            'value' => $db_answer->value
          );
        }

        $respondent['response_list'][] = $response;
      }

      $respondent_list[] = $respondent;
    }

    $url = sprintf( '%s/api/qnaire/name=%s/respondent?operation=import', PARENT_INSTANCE_URL, util::full_urlencode( $this->name ) );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
    curl_setopt( $curl, CURLOPT_POST, true );
    curl_setopt( $curl, CURLOPT_POSTFIELDS, util::json_encode( $respondent_list ) );
    curl_setopt(
      $curl,
      CURLOPT_HTTPHEADER,
      array(
        sprintf(
          'Authorization: Basic %s',
          base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
        ),
        'Content-Type: application/json'
      )
    );

    $response = curl_exec( $curl );
    if( curl_errno( $curl ) )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when synchronizing qnaire with parent instance.  Message: %s',
                 curl_errno( $curl ),
                 curl_error( $curl ) ),
        __METHOD__
      );
    }

    $code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
    if( 401 == $code )
    {
      throw lib::create( 'exception\notice',
        'Unable to synchronize questionnaire, invalid Beartooth username and/or password.',
        __METHOD__
      );
    }
    else if( 404 == $code )
    {
      // ignore missing qnaires, it just means the parent doesn't have it
      log::info( sprintf( 'Questionnaire "%s" was not found in the parent instance, can\'t synchronize.', $this->name ) );
    }
    else if( 300 <= $code )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got error code %s when synchronizing qnaire with parent instance.', $code ),
        __METHOD__
      );
    }
  }

  /**
   * Imports response data from a child instance of Pine
   * @param array $respondent_list
   */
  public function import_response_data( $respondent_list )
  {
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $response_class_name = lib::get_class_name( 'database\response' );
    $language_class_name = lib::get_class_name( 'database\language' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $page_class_name = lib::get_class_name( 'database\page' );
    $answer_class_name = lib::get_class_name( 'database\answer' );

    foreach( $respondent_list as $respondent )
    {
      $db_participant = $participant_class_name::get_unique_record( 'uid', $respondent->uid );
      if( !is_null( $db_participant ) )
      {
        $db_respondent = $respondent_class_name::get_unique_record(
          array( 'qnaire_id', 'participant_id' ),
          array( $this->id, $db_participant->id )
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
              array( 'respondent_id', 'rank' ),
              array( $db_respondent->id, $response->rank )
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
            array( 'qnaire_id', 'name' ),
            array( $this->id, $response->module )
          );
          $db_page = is_null( $db_module )
                   ? NULL
                   : $page_class_name::get_unique_record(
                       array( 'module_id', 'name' ),
                       array( $db_module->id, $response->page )
                     );

          $db_response->language_id = $language_class_name::get_unique_record( 'code', $response->language )->id;
          if( !is_null( $db_page ) ) $db_response->page_id = $db_page->id;
          $db_response->submitted = $response->submitted;
          $db_response->show_hidden = $response->show_hidden;
          $db_response->start_datetime = $response->start_datetime;
          $db_response->last_datetime = $response->last_datetime;
          $db_response->save();

          foreach( $response->answer_list as $answer )
          {
            $db_question = $this->get_question( $answer->question );
            $db_answer = NULL;
            if( !$new_response )
            { // only bother to check for an existing answer if the response isn't new
              $db_answer = $answer_class_name::get_unique_record(
                array( 'response_id', 'question_id' ),
                array( $db_response->id, $db_question->id )
              );
            }

            if( is_null( $db_answer ) )
            {
              $db_answer = lib::create( 'database\answer' );
              $db_answer->response_id = $db_response->id;
              $db_answer->question_id = $db_question->id;
            }

            $db_answer->language_id = $language_class_name::get_unique_record( 'code', $answer->language )->id;
            $db_answer->value = $answer->value;
            $db_answer->save();
          }
        }
      }
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

    if( is_null( $this->beartooth_url ) || is_null( $this->beartooth_username ) || is_null( $this->beartooth_password ) )
    {
      throw lib::create( 'expression\runtime',
        'Tried to get respondents from Beartooth without a URL, username and password.',
        __METHOD__
      );
    }

    $url = sprintf( '%s/api/appointment', $this->beartooth_url );
    $curl = curl_init();
    curl_setopt( $curl, CURLOPT_URL, $url );
    curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
    curl_setopt(
      $curl,
      CURLOPT_HTTPHEADER,
      array(
        sprintf(
          'Authorization: Basic %s',
          base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
        )
      )
    );

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
    else if( 300 <= $code )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Got response code %s when getting appointment list from Beartooth.', $code ),
        __METHOD__
      );
    }

    foreach( util::json_decode( $response ) as $participant )
    {
      // see whether the participant already exists
      $db_participant = $participant_class_name::get_unique_record( 'uid', $participant->uid );
      if( is_null( $db_participant ) )
      {
        $db_cohort = $cohort_class_name::get_unique_record( 'name', $participant->cohort );
        $db_language = $language_class_name::get_unique_record( 'code', $participant->language );
        $db_region = $region_class_name::get_unique_record( 'name', $participant->province );

        // create the participant record
        $db_participant = lib::create( 'database\participant' );
        $db_participant->uid = $participant->uid;
        $db_participant->cohort_id = $db_cohort->id;
        $db_participant->language_id = $db_language->id;
        $db_participant->honorific = $participant->honorific;
        $db_participant->first_name = $participant->first_name;
        $db_participant->last_name = $participant->last_name;
        $db_participant->sex = $participant->gender;
        $db_participant->current_sex = $participant->gender;
        $db_participant->email = $participant->email;
        $db_participant->other_name = $participant->otherName;
        $db_participant->date_of_birth = $participant->dob;
        $db_participant->save();

        // create the address record
        $db_address = lib::create( 'database\address' );
        $db_address->participant_id = $db_participant->id;
        $db_address->rank = 1;
        $db_address->address1 = $participant->street;
        $db_address->city = $participant->city;
        $db_address->region_id = $db_region->id;
        $db_address->postcode = $participant->postcode;
        $db_address->save();
      }

      // add the participant's respondent file
      $db_respondent = $respondent_class_name::get_unique_record(
        array( 'qnaire_id', 'participant_id' ),
        array( $this->id, $db_participant->id )
      );

      if( is_null( $db_respondent ) )
      {
        $db_respondent = lib::create( 'database\respondent' );
        $db_respondent->qnaire_id = $this->id;
        $db_respondent->participant_id = $db_participant->id;
        $db_respondent->save();
      }
    }
  }

  /** 
   * Updates beartooth with respondents' complete status
   */
  public function send_respondents_to_beartooth()
  {
    if( is_null( $this->beartooth_url ) || is_null( $this->beartooth_username ) || is_null( $this->beartooth_password ) )
    {
      throw lib::create( 'expression\runtime',
        'Tried to send respondents to Beartooth without a URL, username and password.',
        __METHOD__
      );
    }

    // get a list of all completed respondents
    $respondent_list = array();
    $respondent_sel = lib::create( 'database\select' );
    $respondent_sel->add_table_column( 'participant', 'uid' );
    $respondent_sel->add_column( 'end_datetime' );
    $respondent_mod = lib::create( 'database\modifier' );
    $respondent_mod->join( 'participant', 'respondent.participant_id', 'participant.id' );
    $respondent_mod->where( 'end_datetime', '!=', NULL );
    foreach( $this->get_respondent_list( $respondent_sel, $respondent_mod ) as $respondent )
    {
      $respondent_list[] = array(
        'uid' => $respondent['uid'],
        'object' => array(
          'end_datetime' => $respondent['end_datetime']
        )
      );
    }

    if( 0 < count( $respondent_list ) )
    {
      $url = sprintf( '%s/api/pine', $this->beartooth_url );
      $curl = curl_init();
      curl_setopt( $curl, CURLOPT_URL, $url );
      curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
      curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
      curl_setopt( $curl, CURLOPT_CONNECTTIMEOUT, 5 );
      curl_setopt( $curl, CURLOPT_POST, true );
      curl_setopt( $curl, CURLOPT_POSTFIELDS, util::json_encode( $respondent_list ) );
      curl_setopt(
        $curl,
        CURLOPT_HTTPHEADER,
        array(
          sprintf(
            'Authorization: Basic %s',
            base64_encode( sprintf( '%s:%s', $this->beartooth_username, $this->beartooth_password ) )
          ),
          'Content-Type: application/json'
        )
      );

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
      else if( 300 <= $code )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Got response code %s when getting appointment list from Beartooth.', $code ),
          __METHOD__
        );
      }
    }
  }

  /**
   * Creates a batch of respondents as a single operation
   * @param array $identifier_list A list of participant identifiers to affect
   */
  public function mass_respondent( $db_identifier, $identifier_list )
  {
    ini_set( 'memory_limit', '1G' );
    set_time_limit( 900 ); // 15 minutes max

    $participant_class_name = lib::get_class_name( 'database\participant' );
    $participant_identifier_class_name = lib::get_class_name( 'database\participant_identifier' );

    foreach( $identifier_list as $identifier )
    {
      $db_participant = is_null( $db_identifier )
                      ? $participant_class_name::get_unique_record( 'uid', $identifier )
                      : $participant_identifier_class_name::get_unique_record(
                          array( 'identifier_id', 'value' ),
                          array( $db_identifier->id, $identifier )
                        )->get_participant();
      $db_respondent = lib::create( 'database\respondent' );
      $db_respondent->qnaire_id = $this->id;
      $db_respondent->participant_id = $db_participant->id;
      $db_respondent->save();
    }
  }

  /**
   * Returns an array of all questions belonging to this qnaire
   * @param boolean $descriptions If true then include module, page and question descriptions
   * @return array
   */
  public function get_all_questions( $descriptions = false )
  {
    $column_list = array();

    // determine which languages the qnaire uses
    $language_list = array();
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    foreach( $this->get_language_list( $language_sel ) as $language ) $language_list[] = $language['code'];

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
          $prompt_list = array( 'module_prompt' => array(), 'page_prompt' => array(), 'question_prompt' => array() );
          if( $descriptions )
          {
            // add the module's prompt
            $description_sel = lib::create( 'database\select' );
            $description_sel->add_table_column( 'language', 'code', 'language' );
            $description_sel->add_column( 'value' );
            $description_mod = lib::create( 'database\modifier' );
            $description_mod->join( 'language', 'module_description.language_id', 'language.id' );
            $description_mod->where( 'module_description.type', '=', 'prompt' );
            foreach( $db_module->get_module_description_list( $description_sel, $description_mod ) as $item )
              $prompt_list['module_prompt'][$item['language']] = $item['value'];

            // add the page's prompt
            $description_sel = lib::create( 'database\select' );
            $description_sel->add_table_column( 'language', 'code', 'language' );
            $description_sel->add_column( 'value' );
            $description_mod = lib::create( 'database\modifier' );
            $description_mod->join( 'language', 'page_description.language_id', 'language.id' );
            $description_mod->where( 'page_description.type', '=', 'prompt' );
            foreach( $db_page->get_page_description_list( $description_sel, $description_mod ) as $item )
              $prompt_list['page_prompt'][$item['language']] = $item['value'];

            // add the question's prompt
            $description_sel = lib::create( 'database\select' );
            $description_sel->add_table_column( 'language', 'code', 'language' );
            $description_sel->add_column( 'value' );
            $description_mod = lib::create( 'database\modifier' );
            $description_mod->join( 'language', 'question_description.language_id', 'language.id' );
            $description_mod->where( 'question_description.type', '=', 'prompt' );
            foreach( $db_question->get_question_description_list( $description_sel, $description_mod ) as $item )
              $prompt_list['question_prompt'][$item['language']] = $item['value'];
          }

          $option_mod = lib::create( 'database\modifier' );
          $option_mod->order( 'question_option.rank' );
          $option_list = $db_question->get_question_option_object_list( $option_mod );

          // only create a variable for all options if at least one is not exclusive
          $all_exclusive = true;
          if( 'list' == $db_question->type )
            foreach( $option_list as $db_option )
              if( !$db_option->exclusive ) $all_exclusive = false;

          // only create a single column for this question if there are no options or they are all exclusive
          if( $all_exclusive )
          {
            // get the base column name from the question's name
            $column_name = $db_question->name;

            // if it exists then add the qnaire's variable suffix to the question name
            if( !is_null( $this->variable_suffix ) ) $column_name = sprintf( '%s_%s', $column_name, $this->variable_suffix );

            $column_list[$column_name] = array(
              'module_name' => $db_module->name,
              'page_name' => $db_page->name,
              'question_name' => $db_question->name,
              'question_id' => $db_question->id,
              'type' => $db_question->type,
              'minimum' => $db_question->minimum,
              'maximum' => $db_question->maximum,
              'module_precondition' => $db_module->precondition,
              'page_precondition' => $db_page->precondition,
              'question_precondition' => $db_question->precondition
            );

            if( $descriptions ) $column_list[$column_name] = array_merge( $column_list[$column_name], $prompt_list );

            if( 0 < count( $option_list ) )
            {
              $column_list[$column_name]['option_list'] = array();
              foreach( $option_list as $db_option )
                $column_list[$column_name]['option_list'][] = array( 'id' => $db_option->id, 'name' => $db_option->name );
            }
          }

          foreach( $option_list as $db_option )
          {
            // add an additional column for all options if any are not exclusive, or for all which have extra data
            if( !$all_exclusive || $db_option->extra )
            {
              // get the base column name from the question's name and add the option's name as a suffix
              $column_name = sprintf( '%s_%s', $db_question->name, $db_option->name );

              // if it exists then add the qnaire's variable suffix to the question name
              if( !is_null( $this->variable_suffix ) ) $column_name = sprintf( '%s_%s', $column_name, $this->variable_suffix );

              $precondition = NULL;
              $precondition = $db_question->precondition;
              if( !is_null( $db_option->precondition ) )
              {
                if( is_null( $precondition ) ) $precondition = $db_option->precondition;
                else $precondition = sprintf( '(%s) && (%s)', $precondition, $db_option->precondition );
              }

              $column_list[$column_name] = array(
                'module_name' => $db_module->name,
                'page_name' => $db_page->name,
                'question_name' => $db_question->name,
                'question_option_name' => $db_option->name,
                'question_id' => $db_question->id,
                'type' => $db_question->type,
                'option_id' => $db_option->id,
                'type' => $db_question->type,
                'minimum' => $db_option->minimum,
                'maximum' => $db_option->maximum,
                'extra' => $db_option->extra,
                'all_exclusive' => $all_exclusive,
                'module_precondition' => $db_module->precondition,
                'page_precondition' => $db_page->precondition,
                'question_precondition' => $db_question->precondition,
                'question_option_precondition' => $db_option->precondition
              );

              if( $descriptions )
              {
                $column_list[$column_name] = array_merge( $column_list[$column_name], $prompt_list );

                $column_list[$column_name]['question_option_prompt'] = array();

                // add the question option's prompt
                $description_sel = lib::create( 'database\select' );
                $description_sel->add_table_column( 'language', 'code', 'language' );
                $description_sel->add_column( 'value' );
                $description_mod = lib::create( 'database\modifier' );
                $description_mod->join( 'language', 'question_option_description.language_id', 'language.id' );
                $description_mod->where( 'question_option_description.type', '=', 'prompt' );
                foreach( $db_option->get_question_option_description_list( $description_sel, $description_mod ) as $item )
                  $column_list[$column_name]['question_option_prompt'][$item['language']] = $item['value'];
              }
            }
          }

          // finally, if not all exclusive then create these options as columns as well
          if( !$all_exclusive )
          {
            // get the base column name from the question's name and add DK_NA as a suffix
            $column_name = sprintf( '%s_DK_NA', $db_question->name );

            // if it exists then add the qnaire's variable suffix to the question name
            if( !is_null( $this->variable_suffix ) ) $column_name = sprintf( '%s_%s', $column_name, $this->variable_suffix );

            $column_list[$column_name] = array(
              'module_name' => $db_module->name,
              'page_name' => $db_page->name,
              'question_name' => $db_question->name,
              'question_option_name' => 'DK_NA',
              'question_id' => $db_question->id,
              'type' => $db_question->type,
              'option_id' => 'dkna',
              'all_exclusive' => $all_exclusive,
              'module_precondition' => $db_module->precondition,
              'page_precondition' => $db_page->precondition,
              'question_precondition' => $db_question->precondition
            );

            if( $descriptions )
            {
              $column_list[$column_name] = array_merge( $column_list[$column_name], $prompt_list );

              $prompt = array();
              if( in_array( 'en', $language_list ) ) $prompt['en'] = 'Don\'t Know / No Answer';
              if( in_array( 'fr', $language_list ) ) $prompt['fr'] = 'Ne sais pas / pas de réponse';
              $column_list[$column_name]['question_option_prompt'] = $prompt;
            }

            // get the base column name from the question's name and add REFUSED as a suffix
            $column_name = sprintf( '%s_REFUSED', $db_question->name );

            // if it exists then add the qnaire's variable suffix to the question name
            if( !is_null( $this->variable_suffix ) ) $column_name = sprintf( '%s_%s', $column_name, $this->variable_suffix );

            $column_list[$column_name] = array(
              'module_name' => $db_module->name,
              'page_name' => $db_page->name,
              'question_name' => $db_question->name,
              'question_option_name' => 'REFUSED',
              'question_id' => $db_question->id,
              'type' => $db_question->type,
              'option_id' => 'refuse',
              'all_exclusive' => $all_exclusive,
              'module_precondition' => $db_module->precondition,
              'page_precondition' => $db_page->precondition,
              'question_precondition' => $db_question->precondition,
            );

            if( $descriptions )
            {
              $column_list[$column_name] = array_merge( $column_list[$column_name], $prompt_list );

              $prompt = array();
              if( in_array( 'en', $language_list ) ) $prompt['en'] = 'Prefer not to answer';
              if( in_array( 'fr', $language_list ) ) $prompt['fr'] = 'Préfère ne pas répondre';
              $column_list[$column_name]['question_option_prompt'] = $prompt;
            }
          }
        }
      }
    }

    return $column_list;
  }

  /**
   * Returns an array of all responses to this qnaire
   * @param database\modifier $modifier
   * @return array( 'header', 'data' )
   */
  public function get_response_data( $modifier = NULL )
  {
    ini_set( 'memory_limit', '1G' );
    set_time_limit( 900 ); // 15 minutes max

    $response_class_name = lib::get_class_name( 'database\response' );
    $column_list = $this->get_all_questions();

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
        $db_response->qnaire_version,
        $db_response->submitted ? 1 : 0,
        is_null( $db_response->start_datetime ) ? NULL : $db_response->start_datetime->format( 'c' ),
        is_null( $db_response->last_datetime ) ? NULL : $db_response->last_datetime->format( 'c' )
      );

      foreach( $column_list as $column_name => $column )
      {
        $row_value = NULL;

        if( array_key_exists( $column['question_id'], $answer_list ) )
        {
          $answer = util::json_decode( $answer_list[$column['question_id']] );
          if( is_object( $answer ) && property_exists( $answer, 'dkna' ) && $answer->dkna )
          {
            if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so set the value to no unless this is the DN_KA column
              $row_value = 'dkna' == $column['option_id'] ? 'YES' : 'NO';
            }
            else
            {
              $row_value = 'DK_NA';
            }
          }
          else if( is_object( $answer ) && property_exists( $answer, 'refuse' ) && $answer->refuse )
          {
            if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so set the value to no unless this is the REFUSED column
              $row_value = 'refuse' == $column['option_id'] ? 'YES' : 'NO';
            }
            else
            {
              $row_value = 'REFUSED';
            }
          }
          else
          {
            if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so every answer is its own variable
              if( 'dkna' == $column['option_id'] || 'refuse' == $column['option_id'] )
              {
                // whatever the answer is it isn't dkna or refused
                $row_value = 'NO';
              }
              else
              {
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
              else // date, number, string and text are all just direct answers
              {
                $row_value = $answer;
              }
            }
          }
        }

        $data_row[] = is_array( $row_value ) ? implode( ';', $row_value ) : $row_value;
      }

      $data[] = $data_row;
    }

    $header = array_keys( $column_list );
    array_unshift( $header, 'uid', 'rank', 'qnaire_version', 'submitted', 'start_datetime', 'last_datetime' );
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
    ini_set( 'memory_limit', '1G' );
    set_time_limit( 900 ); // 15 minutes max

    $language_class_name = lib::get_class_name( 'database\language' );
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $deviation_type_class_name = lib::get_class_name( 'database\deviation_type' );
    $reminder_description_class_name = lib::get_class_name( 'database\reminder_description' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $qnaire_consent_type_confirm_class_name = lib::get_class_name( 'database\qnaire_consent_type_confirm' );
    $qnaire_consent_type_trigger_class_name = lib::get_class_name( 'database\qnaire_consent_type_trigger' );
    $qnaire_description_class_name = lib::get_class_name( 'database\qnaire_description' );
    $module_class_name = lib::get_class_name( 'database\module' );
    $stage_class_name = lib::get_class_name( 'database\stage' );

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
                      $db_reminder_description = lib::create( 'database\reminder_description' );
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
                  foreach( $reminder_description_list as $description )
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
      else if( 'deviation_type_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        foreach( $patch_object->deviation_type_list as $deviation_type )
        {
          $db_deviation_type = $deviation_type_class_name::get_unique_record(
            array( 'qnaire_id', 'type', 'name' ),
            array( $this->id, $deviation_type->type, $deviation_type->name )
          );

          if( is_null( $db_deviation_type ) )
          {
            if( $apply )
            {
              $db_deviation_type = lib::create( 'database\deviation_type' );
              $db_deviation_type->qnaire_id = $this->id;
              $db_deviation_type->type = $deviation_type->type;
              $db_deviation_type->name = $deviation_type->name;
              $db_deviation_type->save();
            }
            else $add_list[] = $deviation_type;
          }
        }

        // check every item in this object for removals
        $remove_list = array();
        foreach( $this->get_deviation_type_object_list() as $db_deviation_type )
        {
          $found = false;
          foreach( $patch_object->deviation_type_list as $deviation_type )
          {
            if( $db_deviation_type->type == $deviation_type->type && $db_deviation_type->name == $deviation_type->name )
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['deviation_type_list'] = $diff_list;
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
              $properties = array_keys( get_object_vars( $db_module->process_patch( $module, $name_suffix, false ) ) );
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
            $name = $apply ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_module->name ) : $db_module->name;
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['module_list'] = $diff_list;
      }
      else if( 'stage_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->stage_list as $stage )
        {
          // match stage by name or rank
          $db_stage = $stage_class_name::get_unique_record( array( 'qnaire_id', 'name' ), array( $this->id, $stage->name ) );
          if( is_null( $db_stage ) )
          {
            // we may have renamed the stage, so see if it exists exactly the same under the same rank
            $db_stage = $stage_class_name::get_unique_record( array( 'qnaire_id', 'rank' ), array( $this->id, $stage->rank ) );
            if( !is_null( $db_stage ) )
            {
              // confirm that the name is the only thing that has changed
              $properties = array_keys( get_object_vars( $db_stage->process_patch( $stage, $name_suffix, false ) ) );
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
        $remove_list = array();
        $stage_mod = lib::create( 'database\modifier' );
        $stage_mod->order( 'rank' );
        foreach( $this->get_stage_object_list( $stage_mod ) as $db_stage )
        {
          $found = false;
          foreach( $patch_object->stage_list as $stage )
          {
            // see if the stage exists in the patch or if we're already changing the stage
            $name = $apply ? preg_replace( sprintf( '/_%s$/', $name_suffix ), '', $db_stage->name ) : $db_stage->name;
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['stage_list'] = $diff_list;
      }
      else if( 'qnaire_consent_type_confirm_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->qnaire_consent_type_confirm_list as $qnaire_consent_type_confirm )
        {
          $db_consent_type = $consent_type_class_name::get_unique_record( 'name', $qnaire_consent_type_confirm->consent_type_name );

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
              array( 'qnaire_id', 'consent_type_id' ),
              array( $this->id, $db_consent_type->id )
            );

            if( is_null( $db_qnaire_consent_type_confirm ) )
            {
              if( $apply )
              {
                $db_qnaire_consent_type_confirm = lib::create( 'database\qnaire_consent_type_confirm' );
                $db_qnaire_consent_type_confirm->qnaire_id = $this->id;
                $db_qnaire_consent_type_confirm->consent_type_id = $db_consent_type->id;
                $db_qnaire_consent_type_confirm->save();
              }
              else $add_list[] = $qnaire_consent_type_confirm;
            }
            else
            {
              // find and add all differences
              $diff = array();
              foreach( $qnaire_consent_type_confirm as $property => $value )
                if( 'consent_type_name' == $property &&
                    $db_qnaire_consent_type_confirm->$property != $qnaire_consent_type_confirm->$property )
                  $diff[$property] = $qnaire_consent_type_confirm->$property;

              if( 0 < count( $diff ) )
              {
                if( $apply ) $db_qnaire_consent_type_confirm->save();
                else $change_list[$qnaire_consent_type_confirm->consent_type_name] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = array();
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_consent_type_confirm_list'] = $diff_list;
      }
      else if( 'qnaire_consent_type_trigger_list' == $property )
      {
        // get a list of all questions with new names
        $change_question_name_list = array();
        if( array_key_exists( 'module_list', $difference_list ) && array_key_exists( 'change', $difference_list['module_list'] ) )
        {
          foreach( $difference_list['module_list']['change'] as $module_change )
          {
            if( property_exists( $module_change, 'page_list' ) && array_key_exists( 'change', $module_change->page_list ) )
            {
              foreach( $module_change->page_list['change'] as $page_change )
              {
                if( property_exists( $page_change, 'question_list' ) && array_key_exists( 'change', $page_change->question_list ) )
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

        // check every item in the patch object for additions and changes
        $add_list = array();
        $change_list = array();
        foreach( $patch_object->qnaire_consent_type_trigger_list as $qnaire_consent_type_trigger )
        {
          $db_consent_type = $consent_type_class_name::get_unique_record( 'name', $qnaire_consent_type_trigger->consent_type_name );

          if( is_null( $db_consent_type ) )
          {
            if( !$apply )
            {
              $error = new \stdClass();
              $error->WARNING = sprintf(
                'Consent Trigger for "%s" will be ignore since the consent type does not exist.',
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
              $db_question = $this->get_question( sprintf( '%s_%s', $qnaire_consent_type_trigger->question_name, $name_suffix ) );

            $db_qnaire_consent_type_trigger = $qnaire_consent_type_trigger_class_name::get_unique_record(
              array( 'qnaire_id', 'consent_type_id', 'question_id', 'accept' ),
              array( $this->id, $db_consent_type->id, $db_question->id, $qnaire_consent_type_trigger->accept )
            );

            if( is_null( $db_qnaire_consent_type_trigger ) )
            {
              if( $apply )
              {
                $db_qnaire_consent_type_trigger = lib::create( 'database\qnaire_consent_type_trigger' );
                $db_qnaire_consent_type_trigger->qnaire_id = $this->id;
                $db_qnaire_consent_type_trigger->consent_type_id = $db_consent_type->id;
                $db_qnaire_consent_type_trigger->question_id = $db_question->id;
                $db_qnaire_consent_type_trigger->answer_value = $qnaire_consent_type_trigger->answer_value;
                $db_qnaire_consent_type_trigger->accept = $qnaire_consent_type_trigger->accept;
                $db_qnaire_consent_type_trigger->save();
              }
              else $add_list[] = $qnaire_consent_type_trigger;
            }
            else
            {
              // find and add all differences
              $diff = array();
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
        $remove_list = array();
        foreach( $this->get_qnaire_consent_type_trigger_object_list() as $db_qnaire_consent_type_trigger )
        {
          $consent_type_name = $db_qnaire_consent_type_trigger->get_consent_type()->name;
          $changed_name = array_search( $db_qnaire_consent_type_trigger->get_question()->name, $change_question_name_list );
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

        $diff_list = array();
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_consent_type_trigger_list'] = $diff_list;
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
    else return (object)$difference_list;
  }

  /**
   * Exports or prints the qnaire
   * @param string $type One of "export" or "print"
   * @param boolean $return_value Whether to return the generated data or write it to a file
   * @return NULL|string|object
   */
  public function generate( $type = 'export', $return_value = false )
  {
    $qnaire_data = array(
      'base_language' => $this->get_base_language()->code,
      'name' => $this->name,
      'version' => $this->version,
      'variable_suffix' => $this->variable_suffix,
      'debug' => $this->debug,
      'readonly' => $this->readonly,
      'stages' => $this->stages,
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
      'deviation_type_list' => array(),
      'reminder_list' => array(),
      'qnaire_description_list' => array(),
      'module_list' => array(),
      'stage_list' => array(),
      'qnaire_consent_type_confirm_list' => array(),
      'qnaire_consent_type_trigger_list' => array()
    );

    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    foreach( $this->get_language_list( $language_sel ) as $item ) $qnaire_data['language_list'][] = $item['code'];

    $attribute_sel = lib::create( 'database\select' );
    $attribute_sel->add_column( 'name' );
    $attribute_sel->add_column( 'code' );
    $attribute_sel->add_column( 'note' );
    foreach( $this->get_attribute_list( $attribute_sel ) as $item ) $qnaire_data['attribute_list'][] = $item;

    $deviation_type_sel = lib::create( 'database\select' );
    $deviation_type_sel->add_column( 'type' );
    $deviation_type_sel->add_column( 'name' );
    foreach( $this->get_deviation_type_list( $deviation_type_sel ) as $item ) $qnaire_data['deviation_type_list'][] = $item;

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
      foreach( $db_reminder->get_reminder_description_list( $reminder_description_sel, $reminder_description_mod ) as $description )
        $item['reminder_description_list'][] = $description;

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
            'dkna_allowed' => $db_question->dkna_allowed,
            'refuse_allowed' => $db_question->refuse_allowed,
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

    $stage_sel = lib::create( 'database\select' );
    $stage_sel->add_column( 'rank' );
    $stage_sel->add_column( 'name' );
    $stage_sel->add_table_column( 'first_module', 'rank', 'first_module_rank' );
    $stage_sel->add_table_column( 'last_module', 'rank', 'last_module_rank' );
    $stage_sel->add_column( 'precondition' );
    $stage_mod = lib::create( 'database\modifier' );
    $stage_mod->join( 'module', 'stage.first_module_id', 'first_module.id', '', 'first_module' );
    $stage_mod->join( 'module', 'stage.last_module_id', 'last_module.id', '', 'last_module' );
    foreach( $this->get_stage_list( $stage_sel, $stage_mod ) as $item ) $qnaire_data['stage_list'][] = $item;

    $qnaire_confirm_sel = lib::create( 'database\select' );
    $qnaire_confirm_sel->add_table_column( 'consent_type', 'name', 'consent_type_name' );
    $qnaire_confirm_mod = lib::create( 'database\modifier' );
    $qnaire_confirm_mod->join( 'consent_type', 'qnaire_consent_type_confirm.consent_type_id', 'consent_type.id' );
    foreach( $this->get_qnaire_consent_type_confirm_list( $qnaire_confirm_sel, $qnaire_confirm_mod ) as $item )
      $qnaire_data['qnaire_consent_type_confirm_list'][] = $item;

    $qnaire_trigger_sel = lib::create( 'database\select' );
    $qnaire_trigger_sel->add_table_column( 'consent_type', 'name', 'consent_type_name' );
    $qnaire_trigger_sel->add_table_column( 'question', 'name', 'question_name' );
    $qnaire_trigger_sel->add_column( 'answer_value' );
    $qnaire_trigger_sel->add_column( 'accept' );
    $qnaire_trigger_mod = lib::create( 'database\modifier' );
    $qnaire_trigger_mod->join( 'consent_type', 'qnaire_consent_type_trigger.consent_type_id', 'consent_type.id' );
    $qnaire_trigger_mod->join( 'question', 'qnaire_consent_type_trigger.question_id', 'question.id' );
    foreach( $this->get_qnaire_consent_type_trigger_list( $qnaire_trigger_sel, $qnaire_trigger_mod ) as $item )
      $qnaire_data['qnaire_consent_type_trigger_list'][] = $item;

    if( 'export' == $type )
    {
      $filename = sprintf( '%s/%s.json', QNAIRE_EXPORT_PATH, $this->id );
      $contents = util::json_encode( $qnaire_data, JSON_PRETTY_PRINT );
    }
    else // print
    {
      $filename = sprintf( '%s/%s.txt', QNAIRE_PRINT_PATH, $this->id );
      $contents = sprintf(
        "%s (%s)\n",
        $qnaire_data['name'],
        is_null( $qnaire_data['version'] ) ? 'no version specified' : sprintf( 'version %s', $qnaire_data['version'] )
      )
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

            if( array_key_exists( 'question_option_list', $question ) && 0 < count( $question['question_option_list'] ) )
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
    $language_class_name = lib::get_class_name( 'database\language' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $module_class_name = lib::get_class_name( 'database\module' );

    $default_page_max_time = lib::create( 'business\setting_manager' )->get_setting( 'general', 'default_page_max_time' );

    $db_qnaire = lib::create( 'database\qnaire' );
    $db_qnaire->base_language_id = $language_class_name::get_unique_record( 'code', $qnaire_object->base_language )->id;
    $db_qnaire->name = $qnaire_object->name;
    $db_qnaire->version = property_exists( $qnaire_object, 'version' ) ? $qnaire_object->version : NULL;
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

    foreach( $qnaire_object->deviation_type_list as $deviation_type )
    {
      $db_deviation_type = lib::create( 'database\deviation_type' );
      $db_deviation_type->qnaire_id = $db_qnaire->id;
      $db_deviation_type->type = $deviation_type->type;
      $db_deviation_type->name = $deviation_type->name;
      $db_deviation_type->save();
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
          $db_question->dkna_allowed = $question_object->dkna_allowed;
          $db_question->refuse_allowed = $question_object->refuse_allowed;
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

    foreach( $qnaire_object->stage_list as $stage )
    {
      $db_stage = lib::create( 'database\stage' );
      $db_stage->qnaire_id = $db_qnaire->id;
      $db_stage->rank = $stage->rank;
      $db_stage->name = $stage->name;
      $db_stage->first_module_id = $module_class_name::get_unique_record(
        array( 'qnaire_id', 'rank' ),
        array( $db_qnaire->id, $stage->first_module_rank )
      )->id;
      $db_stage->last_module_id = $module_class_name::get_unique_record(
        array( 'qnaire_id', 'rank' ),
        array( $db_qnaire->id, $stage->last_module_rank )
      )->id;
      $db_stage->precondition = $stage->precondition;
      $db_stage->save();
    }

    foreach( $qnaire_object->qnaire_consent_type_confirm_list as $qnaire_consent_type_confirm )
    {
      $db_consent_type = $consent_type_class_name::get_unique_record( 'name', $qnaire_consent_type_confirm->consent_type_name );
      if( is_null( $db_consent_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a consent confirm for consent type "%s" which does not exist.',
            $qnaire_consent_type_confirm->consent_type_name
          ),
          __METHOD__
        );
      }

      $db_qnaire_consent_type_confirm = lib::create( 'database\qnaire_consent_type_confirm' );
      $db_qnaire_consent_type_confirm->qnaire_id = $db_qnaire->id;
      $db_qnaire_consent_type_confirm->consent_type_id = $db_consent_type->id;
      $db_qnaire_consent_type_confirm->save();
    }

    foreach( $qnaire_object->qnaire_consent_type_trigger_list as $qnaire_consent_type_trigger )
    {
      $db_consent_type = $consent_type_class_name::get_unique_record( 'name', $qnaire_consent_type_trigger->consent_type_name );
      if( is_null( $db_consent_type ) )
      {
        throw lib::create( 'exception\notice',
          sprintf(
            'Unable to import questionnaire since it has a consent trigger for consent type "%s" which does not exist.',
            $qnaire_consent_type_trigger->consent_type_name
          ),
          __METHOD__
        );
      }

      $db_question = $db_qnaire->get_question( $qnaire_consent_type_trigger->question_name );
      $db_qnaire_consent_type_trigger = lib::create( 'database\qnaire_consent_type_trigger' );
      $db_qnaire_consent_type_trigger->qnaire_id = $db_qnaire->id;
      $db_qnaire_consent_type_trigger->consent_type_id = $db_consent_type->id;
      $db_qnaire_consent_type_trigger->question_id = $db_question->id;
      $db_qnaire_consent_type_trigger->answer_value = $qnaire_consent_type_trigger->answer_value;
      $db_qnaire_consent_type_trigger->accept = $qnaire_consent_type_trigger->accept;
      $db_qnaire_consent_type_trigger->save();
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
