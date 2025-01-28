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

    $is_new = is_null( $this->id );
    $is_checking_in = $this->has_column_changed( 'checked_in' ) && $this->checked_in;
    $is_submitting = $this->has_column_changed( 'submitted' ) && $this->submitted;
    $db_respondent = NULL;
    $db_participant = NULL;
    $db_qnaire = NULL;

    // setup new responses
    if( !$is_new )
    {
      $db_qnaire = $this->get_qnaire();
      $db_respondent = $this->get_respondent();
      $db_participant = $db_respondent->get_participant();
    }
    else
    {
      $db_respondent = lib::create( 'database\respondent', $this->respondent_id );
      $db_qnaire = $db_respondent->get_qnaire();
      $db_participant = $db_respondent->get_participant();
      $db_current_response = $db_respondent->get_current_response();

      if( is_null( $this->qnaire_version ) && !is_null( $db_qnaire->version ) )
        $this->qnaire_version = $db_qnaire->version;

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

    // if checked in then make sure the start datetime is set
    if( $this->checked_in && !$this->start_datetime ) $this->start_datetime = util::get_datetime_object();

    parent::save();

    // create the response's attributes
    // NOTE: we don't do this if the respondent is finished as that indicates that indicates the response
    // record is being imported and is already complete
    $importing = !is_null( $db_respondent ) && !is_null( $db_respondent->end_datetime );
    if( $is_new && !$importing )
    {
      if( !$this->create_attributes() )
      {
        if( $db_qnaire->attributes_mandatory && 0 < count( $session->attribute_error_list ) )
        {
          // delete the response and throw a notice exception since we cannot proceed without attributes
          $this->delete();

          $message = 'Unable to proceed as the server was unable to load the required attributes.';
          if( $db_qnaire->debug ) $message .= "\n".implode( "\n", $session->attribute_error_list );
          throw lib::create( 'exception\notice', $message, __METHOD__ );
        }
      }
    }

    $db_effective_site = $session->get_effective_site();
    $db_effective_user = $session->get_effective_user();

    // if this is a stage-based qnaire and we've just checked in, check for started events
    if( 1 == $this->rank && $db_qnaire->stages && $is_checking_in )
    {
      // only add events if no stages have been opened (to make sure we're not re-checking-in)
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'response_stage.start_datetime', '!=', NULL );
      if( 0 == $this->get_response_stage_count( $modifier ) )
      {
        // see if the qnaire exists as a script and apply the started events if it does
        $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_qnaire->id );
        if( !is_null( $db_script ) && !is_null( $db_participant ) )
        {
          $db_script->add_started_event(
            $db_participant,
            $this->last_datetime,
            $session->get_effective_site(),
            $session->get_effective_user()
          );
        }
      }
    }

    if( $is_submitting )
    {
      if( is_null( $db_qnaire->max_responses ) || $this->rank == $db_qnaire->max_responses )
      {
        // set the respondent's end datetime and add finished events (but only if the respondent is still open)
        if( is_null( $db_respondent->end_datetime ) )
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
      }

      if( !is_null( $db_participant ) )
      {
        // when submitting the response check if the respondent is done and remove any unsent mail
        $db_respondent->remove_unsent_mail();

        // execute all qnaire triggers
        foreach( $db_qnaire->get_qnaire_participant_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_collection_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_consent_type_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_event_type_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_alternate_consent_type_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_proxy_type_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
        foreach( $db_qnaire->get_qnaire_equipment_type_trigger_object_list() as $db_trigger )
          $db_trigger->execute( $this );
      }

      if( $db_qnaire->stages )
      {
        // remove any open answer stage records
        $answer_device_class_name = lib::get_class_name( 'database\answer_device' );
        $answer_device_mod = lib::create( 'database\modifier' );
        $answer_device_mod->join( 'answer', 'answer_device.answer_id', 'answer.id' );
        $answer_device_mod->where( 'answer.response_id', '=', $this->id );
        $answer_device_mod->where( 'answer_device.end_datetime', '=', NULL );
        foreach( $answer_device_class_name::select_objects( $answer_device_mod ) as $db_answer_device )
          $db_answer_device->delete();
      }
    }

    // If the respondent's start date comes after the first response's start date then we have to back
    // it up and warn in the log that there's a datetime mismatch
    if(
      1 == $this->rank &&
      !is_null( $this->start_datetime ) &&
      $db_respondent->start_datetime > $this->start_datetime
    ) {
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
   * Updates the status of the response (this is only used by stage-based qnaires)
   */
  public function update_status()
  {
    if( !$this->get_qnaire()->stages )
    {
      log::warning( 'Tried to update the status of response whose qnaire has no stages.' );
      return false;
    }

    // update the status of all response stages
    $response_stage_mod = lib::create( 'database\modifier' );
    $response_stage_mod->join( 'stage', 'response_stage.stage_id', 'stage.id' );
    $response_stage_mod->order( 'stage.rank' );
    foreach( $this->get_response_stage_object_list( $response_stage_mod ) as $db_response_stage )
      $db_response_stage->update_status();

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
    $script_class_name = lib::get_class_name( 'database\script' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response forward.' );
      return;
    }

    $session = lib::create( 'business\session' );
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

        // see if the qnaire exists as a script and apply the started events if it does
        if( 1 == $this->rank )
        {
          $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $this->get_qnaire()->id );
          $db_participant = $this->get_participant();
          if( !is_null( $db_script ) && !is_null( $db_participant ) )
          {
            $db_script->add_started_event(
              $db_participant,
              $this->last_datetime,
              $session->get_effective_site(),
              $session->get_effective_user()
            );
          }
        }
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
              sprintf( 'anonymous respondent %d', $this->respondent_id ) :
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
    if( !$this->get_qnaire()->stages )
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
   * 
   * @param boolean $replace Replace any existing attribute values
   */
  public function create_attributes( $replace = false )
  {
    $success = true;

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

      if( $replace || is_null( $db_response_attribute ) )
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
          $success = false;
        }

        if( is_null( $db_response_attribute ) )
        {
          $db_response_attribute = lib::create( 'database\response_attribute' );
          $db_response_attribute->response_id = $this->id;
          $db_response_attribute->attribute_id = $db_attribute->id;
          $db_response_attribute->value = $value;
          $db_response_attribute->save();
        }
        else if( $success ) // only change an existing attribute if successful
        {
          $db_response_attribute->value = $value;
          $db_response_attribute->save();
        }
      }
    }

    return $success;
  }

  /**
   * Generates the qnaire report for this response and returns the local filename of the resulting PDF
   * @return string
   */
  public function generate_report()
  {
    $qnaire_report_class_name = lib::get_class_name( 'database\qnaire_report' );
    $answer_class_name = lib::get_class_name( 'database\answer' );

    $db_qnaire = $this->get_qnaire();
    $language = $this->get_language()->code;

    // get the qnaire report for the response's qnaire and language
    $db_qnaire_report = $qnaire_report_class_name::get_unique_record(
      array( 'qnaire_id', 'language_id' ),
      array( $this->get_respondent()->qnaire_id, $this->language_id )
    );
    if( is_null( $db_qnaire_report ) ) return NULL;

    // create the data and stamp arrays to apply to the PDF template
    $data = [];
    $stamp_list = [];

    $data_sel = lib::create( 'database\select' );
    $data_sel->add_column( 'name' );
    $data_sel->add_column( 'code' );
    foreach( $db_qnaire_report->get_qnaire_report_data_list( $data_sel ) as $report_data )
    {
      // check if this is a signature
      $matches = [];
      $re = '/^signature\((.*)\)$/';
      if( preg_match( $re, $report_data['code'], $matches ) )
      {
        $args = [];
        foreach( explode( ', ', $matches[1] ) as $arg ) $args[] = trim( $arg );
        if( 6 != count( $args ) )
        {
          log::error( sprintf(
            'Report data "%s" must contain exactly 6 parameters: question name, page, left, bottom, right, top.',
            $report_data['code']
          ) );
          continue;
        }
        $question_name = $args[0];
        $page = $args[1];
        $left = $args[2];
        $bottom = $args[3];
        $right = $args[4];
        $top = $args[5];

        $db_question = $db_qnaire->get_question( $question_name );
        if( is_null( $db_question ) )
        {
          log::error( sprintf(
            'Report data "%s" contains question "%s" which does not exist.',
            $question_name
          ) );
          continue;
        }

        $db_answer = $answer_class_name::get_unique_record(
          ['response_id', 'question_id'],
          [$this->id, $db_question->id]
        );
        if( !is_null( $db_answer ) && 'null' != $db_answer->value )
        {
          $stamp_filename = sprintf(
            '%s/%s',
            $db_answer->get_data_directory(),
            $answer_class_name::TYPE_FILENAME['signature']
          );
          if( file_exists( $stamp_filename ) ) $stamp_list[] = [
            'filename' => $stamp_filename,
            'page' => $page,
            'left' => $left,
            'bottom' => $bottom,
            'right' => $right,
            'top' => $top,
          ];
        }
      }
      else
      {
        // compile variables as if they were default answers (forced in case the question is on the same page)
        $value = NULL;
        try
        {
          $value = strtoupper( $this->compile_expression( $report_data['code'], true ) );

          // PDF checkboxes require the answer to be "Yes" (case is important)
          if( 'YES' == $value ) $value = 'Yes';
        }
        catch( \cenozo\exception\runtime $e )
        {
          // if the qnaire is in debug mode print the error to the log
          if( $db_qnaire->debug ) log::warning( $e->get_raw_message() );
        }

        $data[$report_data['name']] = is_null( $value ) || 'NULL' == $value ? 'N/A' : $value;
      }
    }

    // write the PDF template to disk (it's the only way for the pdf_writer class to read it)
    $report_filename = sprintf( '%s/qnaire_report_%d.pdf', TEMP_PATH, $this->id );
    $error = $db_qnaire_report->fill_and_write_form( $report_filename, $data, $stamp_list );
    if( $error )
    {
      $db_respondent = $this->get_respondent();
      $db_participant = $db_respondent->get_participant();
      throw lib::create( 'exception\runtime',
        sprintf(
          'Failed to generate PDF qnaire "%s" report for %s%s',
          $db_qnaire->name,
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
   * Gets the reason a response is out of sync (not in the expected state), returning NULL if there is none
   * 
   * The following operations can be tested:
   *   "set answer": check if the answer can be changed
   *   "set response comments": check if we can change the response's comments
   *   "reopen response": check if a stage-based response can be reopened
   *   "check in response": check if the response can be checked in
   *   "check out response": check if the response can be checked out (returned to check-in)
   *   "proceed response": check if the response can proceed to the next page (or finish the questionnaire)
   *   "backup response": check if the response can backup to the previous page
   *   "jump response": check if the response can jump to a different module
   *   "fast forward stage": check if the response can fast forward to the end of the stage
   *   "rewind stage": check if the response can rewind to the beginning of the stage
   *   "launch device": check if the device associated with the provided answer record can be launched
   *   "abort device": check if the device associated with the provided answer record can be launched
   * 
   * @param string $operation The operation to check for
   * @param database\record $db_object The relevant database object
   * @return string
   */
  public function get_out_of_sync( $operation, $db_object = NULL )
  {
    $db_qnaire = $this->get_qnaire();
    if( 'reopen response' == $operation )
    {
      if( !$this->submitted ) return 'The response as is already open.';
      return NULL;
    }

    // only the reopen operation can be done once the response is completed
    if( $this->submitted ) return 'The response has been completed.';

    if( 'check in response' == $operation )
    {
      if( $this->checked_in ) return 'The response is already checked in.';
      return NULL;
    }

    // when using stages, only the check in response operation can be done when not checked in
    if( $db_qnaire->stages && !$this->checked_in ) return 'The response is not checked in.';

    if( 'check out response' == $operation )
    {
      $db_current_response_stage = $this->get_current_response_stage();
      return (
        !is_null( $db_current_response_stage ) ?
        sprintf( 'The response is in the %s stage.', $db_current_response_stage->get_stage()->name ) :
        NULL
      );
    }

    if( in_array( $operation, ['set answer', 'abort device', 'launch device'] ) )
    {
      if( !is_a( $db_object, lib::get_class_name( 'database\answer' ) ) )
        throw lib::create( 'exception\argument', 'db_object', $db_object, __METHOD__ );

      $db_answer = $db_object;
      $db_question = $db_answer->get_question();

      if( $db_qnaire->stages )
      {
        $db_current_response_stage = $this->get_current_response_stage();
        if( is_null( $db_current_response_stage ) ) return 'The response is on the stage-selection page.';
        if( $db_current_response_stage->stage_id != $db_question->get_page()->get_module()->get_stage()->id )
        {
          return sprintf(
            'The response is in another stage (%s).',
            $db_current_response_stage->get_stage()->name
          );
        }

        return (
          $this->page_id != $db_question->page_id ?
          'The response is no longer on this page.' :
          NULL
        );
      }

      return (
        $db_question->page_id != $this->page_id ?
        'The response is not on the correct page.' :
        NULL
      );
    }

    if( in_array( $operation, ['proceed response', 'backup response'] ) )
    {
      if( is_null( $db_object ) )
      {
        return is_null( $this->get_page() ) ? NULL : 'The response is no longer on the introduction page.';
      }

      if( !is_a( $db_object, lib::get_class_name( 'database\page' ) ) )
        throw lib::create( 'exception\argument', 'db_object', $db_object, __METHOD__ );

      $db_page = $db_object;
      $db_current_page = $this->get_page();
      return (
        is_null( $db_current_page ) || $db_page->id != $db_current_page->id ?
        'The response is no longer on this page.' :
        NULL
      );
    }

    if( in_array( $operation, ['jump response', 'fast forward stage', 'rewind stage'] ) )
    {
      if( $db_qnaire->stages )
      {
        $db_current_response_stage = $this->get_current_response_stage();
        return (
          is_null( $db_current_response_stage ) ?
          'The response is on the stage-selection page.' :
          NULL
        );
      }
    }

    return NULL;
  }

  /**
   * Compiles an expression
   * @param string $expression
   * @param boolean $force Whether to force compile values even if the are on the current page
   * @return string
   */
  public function compile_expression( $expression, $force = false )
  {
    $expression_manager = lib::create( 'business\expression_manager', $this );

    // first, compile anything enclosed by backticks
    $matches = [];
    if( preg_match_all( '/`([^`]*)`/', $expression, $matches ) )
    {
      foreach( $matches[0] as $index => $description )
      {
        $expression = str_replace(
          $description,
          $this->compile_description( $matches[1][$index], $force ),
          $expression
        );
      }
    }

    // now replace string(A) with "B" (where B => A with \ and " replaced with \\ and \", respectively
    $matches = [];
    if( preg_match_all( '/string\((.*)\)/U', $expression, $matches ) )
    {
      foreach( $matches[0] as $index => $exp )
      {
        $replace = str_replace( ['\\','"'], ['\\\\','\"'], $matches[1][$index] );
        $expression = str_replace( $exp, $replace, $expression );
      }
      $expression = sprintf( '"%s"', $expression );
    }

    // now evaluate the expression
    $value = $expression_manager->evaluate( $expression );
    if( is_numeric( $value ) ) $value = round( $value, 2 );
    return $value;
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
          $compiled = NULL;

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
          else if( in_array( $db_question->type, ['audio', 'signature'] ) && !is_null( $db_answer ) )
          {
            $compiled = $db_answer->get_data_html_element();
          }
          else if( 'device' == $db_question->type )
          {
            $compiled = util::json_encode( $this->compile_device( $value, $question_matches[2][$index] ) );
          }
          else if( 'lookup' == $db_question->type )
          {
            $compiled = $this->compile_lookup( $value, $question_matches[2][$index] );
          }
          else
          {
            $compiled = $value;
          }

          if( !is_null( $compiled ) ) $description = str_replace( $matched_expression, $compiled, $description );
        }
      }

      if( is_numeric( $description ) ) $description = round( $description, 2 );

      // convert respondent data
      foreach( $respondent_matches[1] as $index => $respondent_property )
      {
        $matched_expression = $respondent_matches[0][$index];

        $compiled = '';
        if( 'token' == $respondent_property )
        {
          $compiled = $this->get_respondent()->token;
        }
        else if( 'interview_type' == $respondent_property )
        {
          $compiled = is_null( $this->interview_type ) ? '' : $this->interview_type;
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

      // NOTE: the .length property in flow/jsonpath is broken so we have to emulate it here
      // TODO: once replacing flow with another lib this can be removed
      $is_length = false;
      $matches = [];
      if( preg_match( '/(.+)\.length$/', $object_path, $matches ) )
      {
        $is_length = true;
        $object_path = $matches[1];
      }

      $data = (new JSONPath( $value ))->find( sprintf( '$.%s', $object_path ) )->data();
      if( !is_array( $data ) || 0 == count( $data ) )
      {
        log::warning( sprintf( 'Tried to get device data using invalid path "%s".', $object_path ) );
      }
      else
      {
        $compiled = (new JSONPath( $value ))->find( sprintf( '$.%s', $object_path ) )->data()[0];
        if( $is_length ) $compiled = count( $compiled );
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
