<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util, \Flow\JSONPath\JSONPath;

/**
 * response: record
 */
class response extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'respondent';

  /**
   * Override parent method
   */
  public static function get_record_from_identifier( $identifier )
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    if( 1 == preg_match( '/^[^=;]+=[^=;]+(;[^=;]+=[^=;]+)*$/', $identifier ) )
    { // see if the identifier has exactly two keys: qnaire_id and participant_id
      $columns = array();
      $values = array();
      foreach( explode( ';', $identifier ) as $part )
      {
        $pair = explode( '=', $part );
        if( 2 == count( $pair ) )
        {
          $columns[] = $pair[0];
          $values[] = $pair[1];
        }
      }

      if( in_array( 'qnaire_id', $columns ) && in_array( 'participant_id', $columns ) )
      {
        // return the current response for the respondent corresponding with the provided participant/qnaire ids
        $db_respondent = $respondent_class_name::get_unique_record( $columns, $values );
        return is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
      }
    }

    return parent::get_record_from_identifier( $identifier );
  }

  /**
   * Override the parent method
   */
  public function save()
  {
    $script_class_name = lib::get_class_name( 'database\script' );

    $session = lib::create( 'business\session' );

    $new = is_null( $this->id );
    $submitted = $this->has_column_changed( 'submitted' ) && $this->submitted;
    $db_respondent = NULL;
    $db_participant = NULL;
    $db_qnaire = NULL;

    // setup new responses
    if( $new )
    {
      $db_respondent = lib::create( 'database\respondent', $this->respondent_id );
      $db_participant = $db_respondent->get_participant();
      $db_qnaire = $db_respondent->get_qnaire();
      $db_current_response = $db_respondent->get_current_response();

      if( is_null( $this->qnaire_version ) && !is_null( $db_qnaire->version ) ) $this->qnaire_version = $db_qnaire->version;
      if( is_null( $db_qnaire->repeated ) && !is_null( $db_current_response ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Tried to create second response for %s answering qnaire "%s" which is not repeated.',
          is_null( $db_participant ) ?
            sprintf( 'anonymous respondent %d', $db_respondent->id ) :
            sprintf( 'participant %s', $db_participant->uid ),
          $db_qnaire->name
        ), __METHOD__ );
      }
      else if( 0 < $db_qnaire->max_responses &&
               !is_null( $db_current_response ) &&
               $db_qnaire->max_responses <= $db_current_response->rank )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Tried to create more than the maximum allowed responses for %s answering qnaire "%s" (maximum responses %d).',
          is_null( $db_participant ) ?
            sprintf( 'anonymous respondent %d', $db_respondent->id ) :
            sprintf( 'participant %s', $db_participant->uid ),
          $db_qnaire->name,
          $db_qnaire->max_responses
        ), __METHOD__ );
      }

      // let the respondent figure out what the language should be
      $this->language_id = $db_respondent->get_language()->id;
      $this->rank = is_null( $db_current_response ) ? 1 : $db_current_response->rank + 1;
    }

    // if the cheked in then make sure the start datetime is set
    if( $this->checked_in && !$this->start_datetime ) $this->start_datetime = util::get_datetime_object();

    parent::save();

    // create the response's attributes
    if( $new ) $this->create_attributes();

    if( is_null( $db_respondent ) ) $db_respondent = $this->get_respondent();

    $db_effective_site = $session->get_effective_site();
    $db_effective_user = $session->get_effective_user();

    // see if the qnaire exists as a script and apply the started/finished events if it does
    if( $new && 1 == $this->rank )
    {
      if( is_null( $db_participant ) ) $db_participant = $db_respondent->get_participant();
      if( !is_null( $db_participant ) )
      {
        $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
        if( !is_null( $db_script ) )
        {
          $db_script->add_started_event(
            $db_participant,
            $this->last_datetime,
            $db_effective_site,
            $db_effective_user
          );
        }
      }
    }
    else if( $submitted )
    {
      if( is_null( $db_participant ) ) $db_participant = $db_respondent->get_participant();
      if( is_null( $db_qnaire ) ) $db_qnaire = $db_respondent->get_qnaire();

      if( is_null( $db_qnaire->max_responses ) || $this->rank == $db_qnaire->max_responses )
      {
        $db_respondent->end_datetime = util::get_datetime_object();
        $db_respondent->save();

        // now add the finished event to non-anonymous respondents
        if( !is_null( $db_participant ) )
        {
          $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
          if( !is_null( $db_script ) )
          {
            $db_script->add_finished_event(
              $db_participant,
              $this->last_datetime,
              $db_effective_site,
              $db_effective_user
            );
          }
        }
      }

      // nothing left to do for anonymous respondents
      if( is_null( $db_participant ) ) return;

      // when submitting the response check if the respondent is done and remove any unsent mail
      $db_respondent->remove_unsent_mail();

      // execute all qnaire triggers
      foreach( $db_qnaire->get_qnaire_participant_trigger_object_list() as $db_trigger )
        $db_trigger->execute( $this );
      foreach( $db_qnaire->get_qnaire_consent_type_trigger_object_list() as $db_trigger )
        $db_trigger->execute( $this );
      foreach( $db_qnaire->get_qnaire_alternate_consent_type_trigger_object_list() as $db_trigger )
        $db_trigger->execute( $this );
      foreach( $db_qnaire->get_qnaire_proxy_type_trigger_object_list() as $db_trigger )
        $db_trigger->execute( $this );
      foreach( $db_qnaire->get_qnaire_equipment_type_trigger_object_list() as $db_trigger )
        $db_trigger->execute( $this );
    }

    // The respondent may have a date in the future, so make sure to back it up to the response's start
    // if it comes after it
    if( !is_null( $this->start_datetime ) && $db_respondent->start_datetime > $this->start_datetime )
    {
      $db_respondent->start_datetime = $this->start_datetime;
      $db_respondent->save();
    }
  }

  /**
   * Override the parent method
   */
  public function delete()
  {
    // Note: we must delete all files associated with this response
    $db_respondent = $this->get_respondent();

    parent::delete();

    if( !is_null( $db_respondent ) )
    {
      $data_dir_list = glob(
        sprintf(
          '%s/*/%s',
          $db_respondent->get_qnaire()->get_data_directory(),
          is_null( $db_respondent->participant_id ) ? $db_respondent->id : $db_respondent->get_participant()->uid
        ),
        GLOB_ONLYDIR
      );
      foreach( $data_dir_list as $dir )
      {
        // delete all files in the directory
        foreach( glob( sprintf( '%s/*', $dir ) ) as $file ) if( is_file( $file ) ) unlink( $file );

        // now delete the directory itself
        rmdir( $dir );
      }
    }
  }

  /**
   * Convenience method to return this response's participant (NULL if respondent is anonymous)
   * @return database\participant $db_participant
   */
  public function get_participant()
  {
    return $this->get_respondent()->get_participant();
  }

  /**
   * Convenience method to return this response's qnaire
   * @return database\qnaire $db_qnaire
   */
  public function get_qnaire()
  {
    return $this->get_respondent()->get_qnaire();
  }

  /**
   * Convenience method to return this response's active response-stage
   * @return database\response_stage $db_response_stage
   */
  public function get_current_response_stage()
  {
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'status', '=', 'active' );
    $response_stage_list = $this->get_response_stage_object_list( $modifier );
    return 0 < count( $response_stage_list ) ? current( $response_stage_list ) : NULL;
  }

  /**
   * Updates the status of the response (this is only used by stage-based qnaires
   */
  public function update_status()
  {
    if( !$this->get_respondent()->get_qnaire()->stages )
    {
      log::warning( 'Tried to update the status of response whose qnaire has no stages.' );
      return false;
    }

    // update the status of all response stages
    $response_stage_mod = lib::create( 'database\modifier' );
    $response_stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
    $response_stage_mod->order( 'stage.rank' );
    foreach( $this->get_response_stage_object_list( $response_stage_mod ) as $db_response_stage ) $db_response_stage->update_status();

    // update the submitted status if there are no incomplete stages left
    if( !$this->has_unfinished_stages() )
    {
      $this->page_id = NULL;
      $this->submitted = true;
      $this->save();
    }
  }

  /**
   * Moves the response forward, either to the next page, conclusion or stage list
   * 
   * @access public
   */
  public function move_forward()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response forward.' );
      return;
    }

    $db_page = $this->get_page();
    $db_next_page = NULL;

    if( is_null( $db_page ) )
    { // we're not currently viewing a page
      if( $this->get_qnaire()->stages )
      {
        $this->stage_selection = true;
      }
      else
      {
        $db_next_module = $this->get_qnaire()->get_first_module_for_response( $this );
        $db_next_page = is_null( $db_next_module )
                      ? NULL
                      : $db_next_module->get_first_page_for_response( $this );
        if( is_null( $db_next_page ) )
        {
          throw lib::create( 'exception\runtime',
            'Unable to start questionnaire as there are no valid pages to display.',
            __METHOD__
          );
        }

        $this->page_id = $db_next_page->id;
        $this->start_datetime = util::get_datetime_object();
      }

      $this->save();
    }
    else // we've already started the qnaire
    {
      // make sure that all questions on the current page are finished
      $complete = true;

      $question_list = array();
      $question_sel = lib::create( 'database\select' );
      $question_sel->add_column( 'id' );
      $question_sel->add_column( 'name' );
      $question_sel->add_column( 'type' );
      $question_mod = lib::create( 'database\modifier' );
      $question_mod->where( 'type', 'NOT IN', ['comment', 'device'] ); // comments and devices don't have answers
      foreach( $db_page->get_question_list( $question_sel, $question_mod ) as $question )
      {
        $db_answer = $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $this->id, $question['id'] )
        );

        if( is_null( $db_answer ) || !$db_answer->is_complete() )
        {
          $db_participant = $this->get_participant();
          log::warning( sprintf(
            'Tried to advance %s to the next page for question "%s", on the current page "%s", '.(
              is_null( $db_answer ) ? 'but the answer doesn\'t exist.' : 'but the answer is incomplete.'
            ),
            is_null( $db_participant ) ?
              sprintf( 'anonymous respondent %d', $db_respondent->id ) :
              sprintf( 'participant %s', $db_participant->uid ),
            $question['name'],
            $db_page->name
          ) );

          $complete = false;
          break;
        }

        $question_list[] = array( 'type' => $question['type'], 'answer' => $db_answer );
      }

      if( $complete )
      {
        // before proceeding remove any empty option values
        foreach( $question_list as $object )
          if( 'list' == $object['type'] )
            $object['answer']->remove_empty_answer_values();

        // record the time spent on the page (add time if there is already time set)
        $db_page_time = $page_time_class_name::get_unique_record(
          array( 'response_id', 'page_id' ),
          array( $this->id, $db_page->id )
        );
        if( is_null( $db_page_time->datetime ) ) $db_page_time->datetime = util::get_datetime_object();
        if( is_null( $db_page_time->time ) ) $db_page_time->time = 0;
        $microtime = microtime();
        $db_page_time->time += (
          util::get_datetime_object()->getTimestamp() + substr( $microtime, 0, strpos( $microtime, ' ' ) ) -
          $db_page_time->datetime->getTimestamp() - $db_page_time->microtime
        );
        $db_page_time->save();

        $stages = $this->get_qnaire()->stages;
        $db_next_page = $db_page->get_next_for_response( $this );
        if( is_null( $db_next_page ) )
        {
          $submitted = true;
          if( $stages )
          {
            // we've moved past the last page in the stage, so mark it as complete
            $db_current_response_stage = $this->get_current_response_stage();
            $db_current_response_stage->complete();

            // don't submit this response if there is an unfinished stage
            $submitted = !$this->has_unfinished_stages();
          }

          if( $submitted )
          {
            $this->page_id = NULL;
            $this->submitted = true;
          }
        }
        else
        {
          if( $stages )
          {
            $db_current_response_stage = $this->get_current_response_stage();
            $db_current_response_stage->page_id = $db_next_page->id;
            $db_current_response_stage->save();
          }
          $this->page_id = $db_next_page->id;
        }
        $this->save();
      }
    }

    // set the datetime/microtime that this page was started
    if( !is_null( $db_next_page ) )
    {
      $db_next_page_time = $page_time_class_name::get_unique_record(
        array( 'response_id', 'page_id' ),
        array( $this->id, $db_next_page->id )
      );

      if( is_null( $db_next_page_time ) )
      {
        $db_next_page_time = lib::create( 'database\page_time' );
        $db_next_page_time->response_id = $this->id;
        $db_next_page_time->page_id = $db_next_page->id;
      }

      $microtime = microtime();
      $db_next_page_time->datetime = util::get_datetime_object();
      $db_next_page_time->microtime = substr( $microtime, 0, strpos( $microtime, ' ' ) );
      $db_next_page_time->save();
    }
  }

  /**
   * Moves the response backward, either to the previous page or stage list
   * 
   * @access public
   */
  public function move_backward()
  {
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response backward.' );
      return;
    }

    $db_page = $this->get_page();

    // record the time spent on the page (add time if there is already time set)
    $db_page_time = $page_time_class_name::get_unique_record(
      array( 'response_id', 'page_id' ),
      array( $this->id, $db_page->id )
    );
    if( is_null( $db_page_time->datetime ) ) $db_page_time->datetime = util::get_datetime_object();
    if( is_null( $db_page_time->time ) ) $db_page_time->time = 0;
    $microtime = microtime();
    $db_page_time->time += (
      util::get_datetime_object()->getTimestamp() + substr( $microtime, 0, strpos( $microtime, ' ' ) ) -
      $db_page_time->datetime->getTimestamp() - $db_page_time->microtime
    );
    $db_page_time->save();

    $db_previous_page = $db_page->get_previous_for_response( $this );
    if( is_null( $db_previous_page ) )
    {
      throw lib::create( 'exception\runtime',
        'Unable to move to the previous page as there are no valid pages to display.',
        __METHOD__
      );
    }
    else
    {
      if( $this->get_qnaire()->stages )
      {
        $db_current_response_stage = $this->get_current_response_stage();
        $db_current_response_stage->page_id = $db_previous_page->id;
        $db_current_response_stage->save();
      }

      $this->page_id = $db_previous_page->id;
      $this->save();

      // set the datetime that this page was started
      $db_previous_page_time = $page_time_class_name::get_unique_record(
        array( 'response_id', 'page_id' ),
        array( $this->id, $db_previous_page->id )
      );

      if( is_null( $db_previous_page_time ) ) {
        $db_previous_page_time = lib::create( 'database\page_time' );
        $db_previous_page_time->response_id = $this->id;
        $db_previous_page_time->page_id = $db_previous_page->id;
      }

      $microtime = microtime();
      $db_previous_page_time->datetime = util::get_datetime_object();
      $db_previous_page_time->microtime = substr( $microtime, 0, strpos( $microtime, ' ' ) );
      $db_previous_page_time->save();
    }
  }

  /**
   * Determine if any stage is left unfinished
   * @return boolean
   */
  public function has_unfinished_stages()
  {
    if( !$this->get_respondent()->get_qnaire()->stages )
    {
      log::warning( 'Tried to test if response with no stages has any unfinished stages.' );
      return false;
    }

    $response_stage_mod = lib::create( 'database\modifier' );
    $response_stage_mod->where( 'status', 'IN', array( 'not ready', 'ready', 'active', 'paused' ) );
    return 0 < $this->get_response_stage_count( $response_stage_mod );
  }

  /**
   * Change's the response's current language, including all questions on the current page
   * @param database\language $db_language
   * @access public
   */
  public function set_language( $db_language )
  {
    $this->language_id = $db_language->id;
    $this->save();

    // also update the language for all answers from the current page
    if( !is_null( $this->id ) && !is_null( $this->page_id ) )
    {
      $pre_mod = lib::create( 'database\modifier' );
      $pre_mod->join( 'question', 'answer.question_id', 'question.id' );

      $post_mod = lib::create( 'database\modifier' );
      $post_mod->where( 'answer.response_id', '=', $this->id );
      $post_mod->where( 'question.page_id', '=', $this->page_id );

      static::db()->execute( sprintf(
        'UPDATE answer %s SET language_id = %d %s',
        $pre_mod->get_sql(),
        $db_language->id,
        $post_mod->get_sql()
      ) );
    }
  }

  /**
   * Removes all answers in this response belonging to a module
   * @param database\module $db_module
   */
   public function delete_answers_in_module( $db_module )
   {
     $question_sel = lib::create( 'database\select' );
     $question_sel->from( 'question' );
     $question_sel->add_column( 'id' );
     $question_mod = lib::create( 'database\modifier' );
     $question_mod->join( 'page', 'question.page_id', 'page.id' );
     $question_mod->where( 'page.module_id', '=', $db_module->id );
     $question_sql = sprintf( '%s %s', $question_sel->get_sql(), $question_mod->get_sql() );

     $modifier = lib::create( 'database\modifier' );
     $modifier->where( 'response_id', '=', $this->id );
     $modifier->where( 'question_id', 'IN', $question_sql, false );
     $sql = sprintf( 'DELETE FROM answer %s', $modifier->get_sql() );
     static::db()->execute( $sql );
   }

  /**
   * Removes all answers in this response belonging to a page
   * @param database\page $db_page
   */
  public function delete_answers_in_page( $db_page )
  {
    $question_sel = lib::create( 'database\select' );
    $question_sel->from( 'question' );
    $question_sel->add_column( 'id' );
    $question_mod = lib::create( 'database\modifier' );
    $question_mod->where( 'question.page_id', '=', $db_page->id );
    $question_sql = sprintf( '%s %s', $question_sel->get_sql(), $question_mod->get_sql() );

    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'response_id', '=', $this->id );
    $modifier->where( 'question_id', 'IN', $question_sql, false );
    $sql = sprintf( 'DELETE FROM answer %s', $modifier->get_sql() );
    static::db()->execute( $sql );
  }

  /**
   * Creates all attributes for the response
   */
  public function create_attributes()
  {
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );

    $session = lib::create( 'business\session' );
    $db_participant = $this->get_participant();
    $db_qnaire = $this->get_qnaire();

    foreach( $db_qnaire->get_attribute_object_list() as $db_attribute )
    {
      $db_response_attribute = $response_attribute_class_name::get_unique_record(
        array( 'response_id', 'attribute_id' ),
        array( $this->id, $db_attribute->id )
      );

      if( is_null( $db_response_attribute ) )
      {
        // collect errors found while trying to get the participant's attribute value
        $value = NULL;
        try
        {
          // participant-specific attributes will always be NULL for anonymous respondents
          $value = $db_attribute->get_participant_value( $db_participant );
        }
        catch( \cenozo\exception\argument $e )
        {
          log::warning( sprintf(
            'Error while getting attribute value for %s, questionnaire "%s", attribute "%s".%s%s',
            is_null( $db_participant ) ?
              sprintf( 'anonymous respondent %d', $db_respondent->id ) :
              sprintf( 'participant %s', $db_participant->uid ),
            $db_qnaire->name,
            $db_attribute->name,
            "\n",
            $e->get_raw_message()
          ) );
          $session->attribute_error_list[$db_attribute->name] = $e->get_raw_message();
        }

        $db_response_attribute = lib::create( 'database\response_attribute' );
        $db_response_attribute->response_id = $this->id;
        $db_response_attribute->attribute_id = $db_attribute->id;
        $db_response_attribute->value = $value;
        $db_response_attribute->save();
      }
    }
  }

  /**
   * Generates the qnaire report for this response and returns the local filename of the resulting PDF
   * @return string
   */
  public function generate_report()
  {
    $qnaire_report_class_name = lib::get_class_name( 'database\qnaire_report' );

    $language = $this->get_language()->code;

    // get the qnaire report for the response's qnaire and language
    $db_qnaire_report = $qnaire_report_class_name::get_unique_record(
      array( 'qnaire_id', 'language_id' ),
      array( $this->get_respondent()->qnaire_id, $this->language_id )
    );
    if( is_null( $db_qnaire_report ) ) return NULL;

    // create the data array to apply to the PDF template
    $data = [];
    $data_sel = lib::create( 'database\select' );
    $data_sel->add_column( 'name' );
    $data_sel->add_column( 'code' );
    foreach( $db_qnaire_report->get_qnaire_report_data_list( $data_sel ) as $report_data )
    {
      $data[$report_data['name']] = $this->compile_description( $report_data['code'], true );
    }

    // write the PDF template to disk (it's the only way for the pdf_writer class to read it)
    $report_filename = sprintf( '%s/qnaire_report_%d.pdf', TEMP_PATH, $this->id );
    $error = $db_qnaire_report->fill_and_write_form( $data, $report_filename );
    if( $error )
    {
      $db_respondent = $this->get_respondent();
      $db_participant = $db_respondent->get_participant();
      throw lib::create( 'exception\runtime',
        sprintf(
          'Failed to generate PDF qnaire "%s" report for %s%s',
          $db_respondent->get_qnaire()->name,
          is_null( $db_participant ) ?
            sprintf( 'anonymous respondent %d', $db_respondent->id ) :
            sprintf( 'participant %s', $db_participant->uid ),
          "\n".$error
        ),
        __METHOD__
      );
    }

    return $report_filename;
  }

  /**
   * Compiles a default answer
   * @param string $default_answer
   * @return string
   */
  public function compile_default_answer( $default )
  {
    $expression_manager = lib::create( 'business\expression_manager', $this );

    // default answers enclosed in single or double quotes must be compiled as strings (descriptions)
    $matches = [];
    if( preg_match( '/^(\'(.*)\')|("(.*)")$/', $default, $matches ) )
    {
      // the expression inside the quotes will either be in index 2 or 4 (for single or double quotes)
      return $this->compile_description( $matches[2] ? $matches[2] : $matches[4] );
    }

    return $expression_manager->compile( $default );
  }

  /**
   * Compiles a question's or option's description
   * @param string $description
   * @param boolean $force Whether to force compile values even if they are on the current page
   */
  public function compile_description( $description, $force = false )
  {
    // do nothing to empty descriptions
    if( is_null( $description ) || 0 == strlen( $description ) ) return;

    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_description_class_name = lib::get_class_name( 'database\question_option_description' );

    $db_qnaire = $this->get_qnaire();
    $db_participant = $this->get_participant();

    // Keep converting attributes, questions and respondent data until there are none left to convert
    // This has to be done in a loop since a question's description may contain other attributes or questions
    $attribute_regex =
      '/@([A-Za-z0-9_]+)('.
        '\.(name|description)\( *"?[^)"]+"? *\)'.
      ')?\@/';

    $attribute_matches = [];
    $attribute_test = preg_match_all(
      $attribute_regex,
      $description,
      $attribute_matches
    );

    $question_regex =
      '/\$([A-Za-z0-9_]+)('.
        ':[A-Za-z0-9_]+|'.
        '\.extra\([^)]+\)|'.
        '\.count\(\)|'.
        '\.(value|name|description)\( *"?[^)"]+"? *\)'.
      ')?\$/';

    $question_matches = [];
    $question_test = preg_match_all(
      $question_regex,
      $description,
      $question_matches
    );
    $ignore_question_list = array();

    $respondent_regex = '/\$respondent\.(.+)\$/';

    $respondent_matches = [];
    $respondent_test = preg_match_all(
      $respondent_regex,
      $description,
      $respondent_matches
    );

    while( $attribute_test || $question_test || $respondent_test )
    {
      // convert attributes
      foreach( $attribute_matches[1] as $index => $attribute_name )
      {
        // Note: embedded files also use @ as delimiters, so if the attribute isn't found it's likely
        // an embedded file and not an attribute (so we can safely ignore it)
        $value = '';
        $db_attribute = $attribute_class_name::get_unique_record(
          array( 'qnaire_id', 'name' ),
          array( $db_qnaire->id, $attribute_name )
        );
        if( !is_null( $db_attribute ) )
        {
          $db_response_attribute = $response_attribute_class_name::get_unique_record(
            array( 'response_id', 'attribute_id' ),
            array( $this->id, $db_attribute->id )
          );

          if( is_null( $db_response_attribute ) )
          {
            $db_response_attribute = lib::create( 'database\response_attribute' );
            $db_response_attribute->response_id = $this->id;
            $db_response_attribute->attribute_id = $db_attribute->id;
            // participant-specific attributes will always be NULL for anonymous respondents
            $db_response_attribute->value = $db_attribute->get_participant_value( $db_participant );
            $db_response_attribute->save();
          }

          // if we matched for .name() or .description then make sure to compile as a lookup
          $value = 3 <= count( $attribute_matches )
                 ? $this->compile_lookup( $db_response_attribute->value, $attribute_matches[2][$index] )
                 : $db_response_attribute->value;
        }

        $description = str_replace( $attribute_matches[0][$index], $value, $description );
      }

      // convert questions and question options
      foreach( $question_matches[1] as $index => $question_name )
      {
        $matched_expression = $question_matches[0][$index];
        $db_question = $db_qnaire->get_question( $question_name );
        if( is_null( $db_question ) || 'comment' == $db_question->type )
        {
          $warning = sprintf(
            'Invalid question "%s" found while compiling description: %s',
            $question_name,
            is_null( $db_question )
              ? 'question doesn\'t exist'
              : sprintf( 'question type "%s" does not have a value', $db_question->type )
          );
          $description = str_replace(
            $matched_expression,
            $db_qnaire->debug ? '<b><i>WARNING: '.$warning.'</i></b>' : '',
            $description
          );
          log::warning( $warning );
        }
        else if( $db_question->page_id == $this->page_id && !$force )
        {
          // Do not compile questions that are on the same page, let the frontend do this dynamically instead
          // Instead, remove the $'s around the expression and put them back in after the loop is done
          $ignore_question_list[] = $matched_expression;
          $description = str_replace( $matched_expression, trim( $matched_expression, '$' ), $description );
        }
        else
        {
          $db_answer = $answer_class_name::get_unique_record(
            array( 'response_id', 'question_id' ),
            array( $this->id, $db_question->id )
          );
          $value = is_null( $db_answer ) ? NULL : util::json_decode( $db_answer->value );

          if( !is_null( $db_answer ) && $answer_class_name::DKNA == $db_answer->value )
          {
            $compiled = 'fr' == $this->get_language()->code
                      ? 'Ne sais pas / pas de réponse'
                      : 'Don\'t Know / No Answer';
          }
          else if( !is_null( $db_answer ) && $answer_class_name::REFUSE == $db_answer->value )
          {
            $compiled = 'fr' == $this->get_language()->code
                      ? 'Refus'
                      : 'Refused';
          }
          else if( is_array( $value ) )
          {
            $extra_matches = [];
            $selected_matches = [];
            if( '.count()' == $question_matches[2][$index] )
            {
              $compiled = count( $value );
            }
            else if( preg_match( '/\.extra\((.*)\)/', $question_matches[2][$index], $extra_matches ) )
            {
              $extra_option_name = $extra_matches[1];
              $compiled = '';

              // we only used the matched option when getting the option's name or extra value
              foreach( $value as $option )
              {
                $db_question_option = lib::create(
                  'database\question_option',
                  is_object( $option ) ? $option->id : $option
                );
                if( $extra_option_name == $db_question_option->name )
                {
                  $compiled = is_array( $option->value ) ? implode( ', ', $option->value ) : $option->value;
                  break;
                }
              }
            }
            else if( preg_match( '/:([A-Za-z0-9_]+)/', $question_matches[2][$index], $selected_matches ) )
            {
              $selected_option_name = $selected_matches[1];
              $compiled = 'false';

              // we only used the matched option when getting the option's name or extra value
              foreach( $value as $option )
              {
                $db_question_option = lib::create(
                  'database\question_option',
                  is_object( $option ) ? $option->id : $option
                );
                if( $selected_option_name == $db_question_option->name )
                {
                  $compiled = 'true';
                  break;
                }
              }
            }
            else
            {
              $question_option_id_list = array();
              $raw_answer_list = array();
              foreach( $value as $option )
              {
                // selected option may have additional details (so the answer is an object)
                if( is_object( $option ) )
                {
                  // the option's extra data may have multiple answers (an array) or a single answer
                  if( is_array( $option->value ) )
                  {
                    $raw_answer_list = array_merge( $raw_answer_list, $option->value );
                  }
                  else
                  {
                    $raw_answer_list[] = $option->value;
                  }
                }
                else
                {
                  $question_option_id_list[] = $option;
                }
              }

              $answers = array();
              if( 0 < count( $question_option_id_list ) )
              {
                // get the description of all selected options
                $description_sel = lib::create( 'database\select' );
                $description_sel->add_column( 'value' );
                $description_mod = lib::create( 'database\modifier' );
                $description_mod->where( 'question_option_id', 'IN', $question_option_id_list );
                $description_mod->where( 'type', '=', 'prompt' );
                $description_mod->where( 'language_id', '=', $this->language_id );
                $description_list = $question_option_description_class_name::select(
                  $description_sel,
                  $description_mod
                );
                foreach( $description_list as $row ) $answers[] = $row['value'];
              }

              // append any extra option values to the list
              if( 0 < count( $raw_answer_list ) ) $answers = array_merge( $answers, $raw_answer_list );
              $compiled = implode( ', ', $answers );
            }
          }
          else if( is_null( $value ) ) $compiled = '';
          else if( 'boolean' == $db_question->type )
          {
            if( 'fr' == $this->get_language()->code )
            {
              $compiled = $value ? 'Oui' : 'Non';
            }
            else
            {
              $compiled = $value ? 'Yes' : 'No';
            }
          }
          else if( 'audio' == $db_question->type && !is_null( $db_answer ) )
          {
            // audio files are stored on disk, not in the database
            $filename = sprintf( '%s/audio.wav', $db_answer->get_data_directory() );
            if( file_exists( $filename ) )
            {
              $file = file_get_contents( sprintf( '%s/audio.wav', $db_answer->get_data_directory() ) );
              if( false !== $file )
              {
                // send as a base64 encoded audio string for the <audio> tag's src attribute
                $value = sprintf( 'data:audio/wav;base64,%s', base64_encode( $file ) );
              }
            }
            $compiled = sprintf(
              '<audio controls class="full-width" style="height: 40px;" src="%s"></audio>',
              $value
            );
          }
          else if( 'device' == $db_question->type )
          {
            $compiled = util::json_encode( $this->compile_device( $value, $question_matches[2][$index] ) );
          }
          else if( 'lookup' == $db_question->type )
          {
            $compiled = $this->compile_lookup( $value, $question_matches[2][$index] );
          }
          else $compiled = $value;

          $description = str_replace( $matched_expression, $compiled, $description );
        }
      }


      // convert respondent data
      foreach( $respondent_matches[1] as $index => $respondent_property )
      {
        $matched_expression = $respondent_matches[0][$index];

        $compiled = '';
        if( 'token' == $respondent_property )
        {
          $compiled = $this->get_respondent()->token;
        }
        else if( 'language' == $respondent_property )
        {
          $compiled = $this->get_language()->code;
        }
        else if( 'start_date' == $respondent_property )
        {
          $compiled = is_null( $this->start_datetime ) ? '' : $this->start_datetime->format( 'Y-m-d' );
        }
        else
        {
          log::warning( sprintf( 'Tried to compile invalid respondent code "%s".', $matched_expression ) );
        }

        $description = str_replace( $matched_expression, $compiled, $description );
      }

      // now determine if there are more attributes or questions to decode in the description
      $attribute_test = preg_match_all(
        $attribute_regex,
        $description,
        $attribute_matches
      );
      $question_test = preg_match_all(
        $question_regex,
        $description,
        $question_matches
      );
      $respondent_test = preg_match_all(
        $respondent_regex,
        $description,
        $respondent_matches
      );
    }

    foreach( array_unique( $ignore_question_list ) as $question )
    {
      $description = str_replace( trim( $question, '$' ), $question, $description );
    }

    return $description;
  }

  /**
   * Converts a device expression (eg: VAR.value(<PATH>))
   * @param string $value The data returned by the device
   * @param string $expression The expression to compile (EG: .value("device"))
   * @return string
   */
  private function compile_device( $value, $expression )
  {
    $device_class_name = lib::get_class_name( 'database\device' );

    // start with the full value returned by the device
    $compiled = $value;

    // if there is no identifier then we do nothing
    if( is_null( $compiled ) ) return $compiled;

    $device_matches = [];
    $match = preg_match(
      '/\.value\( *"?([^)"]+)"? *\)/',
      $expression,
      $device_matches
    );
    if( $match )
    {
      $compiled = NULL;
      $object_path = $device_matches[1];
      $data = (new JSONPath( $value ))->find( sprintf( '$.%s', $object_path ) )->data();
      if( !is_array( $data ) || 0 == count( $data ) )
      {
        log::error( sprintf( 'Tried to get device data using invalid path "%s".', $object_path ) );
      }
      else
      {
        $compiled = (new JSONPath( $value ))->find( sprintf( '$.%s', $object_path ) )->data()[0];
      }
    }

    return $compiled;
  }

  /**
   * Converts a lookup expression (eg: VAR.name("lookup") or VAR.description("lookup"))
   * @param string $identifier The identifier of the lookup_item being referenced
   * @param string $expression The expression to compile (EG: .name("lookup") or .description("lookup"))
   * @return string
   */
  private function compile_lookup( $identifier, $expression )
  {
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $db_qnaire = $this->get_qnaire();

    // start with the identifier as a default (in case the lookup below doesn't work)
    $compiled = $identifier;

    // if there is no identifier then we do nothing
    if( is_null( $compiled ) ) return $compiled;

    $lookup_matches = [];
    $match = preg_match(
      '/\.(name|description)\( *"?([^)"]+)"? *\)/',
      $expression,
      $lookup_matches
    );
    if( $match )
    {
      $property = $lookup_matches[1]; // either name or description
      $lookup_name = $lookup_matches[2]; // the name of the lookup
      $db_lookup = $lookup_class_name::get_unique_record( 'name', $lookup_name );
      if( !is_null( $db_lookup ) )
      {
        $db_lookup_item = $lookup_item_class_name::get_unique_record(
          array( 'lookup_id', 'identifier' ),
          array( $db_lookup->id, $identifier )
        );

        if( !is_null( $db_lookup_item ) )
        {
          $compiled = $db_lookup_item->$property;
        }
        else if( $db_qnaire->debug )
        {
          log::warning( sprintf(
            'Lookup identifier "%s" not found while compiling description',
            $identifier
          ) );
        }
      }
      else if( $db_qnaire->debug )
      {
        log::warning( sprintf(
          'Invalid lookup "%s" found while compiling description',
          $lookup_name
        ) );
      }
    }

    return $compiled;
  }
}
