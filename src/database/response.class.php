<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

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
    $qnaire_participant_trigger_class_name = lib::get_class_name( 'database\qnaire_participant_trigger' );
    $qnaire_consent_type_trigger_class_name = lib::get_class_name( 'database\qnaire_consent_type_trigger' );
    $qnaire_alternate_consent_type_trigger_class_name = lib::get_class_name( 'database\qnaire_alternate_consent_type_trigger' );
    $qnaire_proxy_type_trigger_class_name = lib::get_class_name( 'database\qnaire_proxy_type_trigger' );

    $session = lib::create( 'business\session' );

    $new = is_null( $this->id );
    $submitted = $this->has_column_changed( 'submitted' ) && $this->submitted;
    $db_respondent = NULL;
    $db_qnaire = NULL;

    // setup new responses
    if( $new )
    {
      $db_respondent = lib::create( 'database\respondent', $this->respondent_id );
      $db_qnaire = $db_respondent->get_qnaire();
      $db_current_response = $db_respondent->get_current_response();

      if( is_null( $this->qnaire_version ) && !is_null( $db_qnaire->version ) ) $this->qnaire_version = $db_qnaire->version;
      if( is_null( $db_qnaire->repeated ) && !is_null( $db_current_response ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Tried to create second response for participant %s answering qnaire "%s" which is not repeated.',
          $db_respondent->get_participant()->uid,
          $db_qnaire->name
        ), __METHOD__ );
      }
      else if( 0 < $db_qnaire->max_responses &&
               !is_null( $db_current_response ) &&
               $db_qnaire->max_responses <= $db_current_response->rank )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Tried to create more than the maximum allowed responses for participant %s answering qnaire "%s" (maximum responses %d).',
          $db_respondent->get_participant()->uid,
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

    // see if the qnaire exists as a script and apply the started/finished events if it does
    if( $new && 1 == $this->rank )
    {
      if( is_null( $db_respondent ) ) $db_respondent = $this->get_respondent();
      $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
      if( !is_null( $db_script ) ) $db_script->add_started_event( $this->get_participant(), $this->last_datetime );
    }
    else if( $submitted )
    {
      $db_participant = $this->get_participant();
      if( is_null( $db_respondent ) ) $db_respondent = $this->get_respondent();
      if( is_null( $db_qnaire ) ) $db_qnaire = $db_respondent->get_qnaire();

      if( is_null( $db_qnaire->max_responses ) || $this->rank == $db_qnaire->max_responses )
      {
        $db_respondent->end_datetime = util::get_datetime_object();
        $db_respondent->save();

        // now add the finished event, if there is one
        $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
        if( !is_null( $db_script ) ) $db_script->add_finished_event( $db_participant, $this->last_datetime );
      }

      // when submitting the response check if the respondent is done and remove any unsent mail
      $db_respondent->remove_unsent_mail();

      // update any triggered participant columns
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'qnaire_id', '=', $db_qnaire->id );
      foreach( $qnaire_participant_trigger_class_name::select_objects( $modifier ) as $db_qnaire_participant_trigger )
      {
        if( $this->check_trigger( $db_qnaire_participant_trigger ) )
        {
          $db_question = $db_qnaire_participant_trigger->get_question();

          if( $db_qnaire->debug )
          {
            log::info( sprintf(
              'Updating participant.%s to %s due to question "%s" having the value "%s" (questionnaire "%s")',
              $db_qnaire_participant_trigger->column_name,
              $db_qnaire_participant_trigger->value,
              $db_question->name,
              $db_qnaire_participant_trigger->answer_value,
              $db_qnaire->name
            ) );
          }

          // this is safe because the column_name is an enum type, so dangerous column names can't exist here
          $column_name = $db_qnaire_participant_trigger->column_name;
          // currently only boolean columns are supported
          $db_participant->$column_name = 'true' == $db_qnaire_participant_trigger->value;
          $db_participant->save();
        }
      }

      // create any triggered consent records
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'qnaire_id', '=', $db_qnaire->id );
      foreach( $qnaire_consent_type_trigger_class_name::select_objects( $modifier ) as $db_qnaire_consent_type_trigger )
      {
        if( $this->check_trigger( $db_qnaire_consent_type_trigger ) )
        {
          $db_question = $db_qnaire_consent_type_trigger->get_question();

          if( $db_qnaire->debug )
          {
            log::info( sprintf(
              'Creating new %s "%s" consent due to question "%s" having the value "%s" (questionnaire "%s")',
              $db_qnaire_consent_type_trigger->accept ? 'accept' : 'deny',
              $db_qnaire_consent_type_trigger->get_consent_type()->name,
              $db_question->name,
              $db_qnaire_consent_type_trigger->answer_value,
              $db_qnaire->name
            ) );
          }

          $db_consent = lib::create( 'database\consent' );
          $db_consent->participant_id = $db_participant->id;
          $db_consent->consent_type_id = $db_qnaire_consent_type_trigger->consent_type_id;
          $db_consent->accept = $db_qnaire_consent_type_trigger->accept;
          $db_consent->written = false;
          $db_consent->datetime = util::get_datetime_object();
          $db_consent->note = sprintf(
            'Created by Pine after questionnaire "%s" was completed with question "%s" having the value "%s"',
            $db_qnaire->name,
            $db_question->name,
            $db_qnaire_consent_type_trigger->answer_value
          );
          $db_consent->save();
        }
      }

      // create any triggered alternate_consent records
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'qnaire_id', '=', $db_qnaire->id );
      foreach( $qnaire_alternate_consent_type_trigger_class_name::select_objects( $modifier )
                 as $db_qnaire_alternate_consent_type_trigger )
      {
        if( $this->check_trigger( $db_qnaire_alternate_consent_type_trigger ) )
        {
          $db_question = $db_qnaire_alternate_consent_type_trigger->get_question();
          $db_answer = $answer_class_name::get_unique_record(
            array( 'response_id', 'question_id' ),
            array( $this->id, $db_question->id )
          );

          if( $db_qnaire->debug )
          {
            log::info( sprintf(
              'Creating new %s "%s" alternate consent due to question "%s" having the value "%s" (questionnaire "%s")',
              $db_qnaire_alternate_consent_type_trigger->accept ? 'accept' : 'deny',
              $db_qnaire_alternate_consent_type_trigger->get_alternate_consent_type()->name,
              $db_question->name,
              $db_qnaire_alternate_consent_type_trigger->answer_value,
              $db_qnaire->name
            ) );
          }

          if( is_null( $db_answer->alternate_id ) )
          {
            log::warning( sprintf(
              'Alternate consent trigger cannot create record since answer was provided by a participant and not an alternate. '.
              '(response=%d, question=%d)',
              $this->id,
              $db_answer->question_id
            ) );
          }
          else
          {
            $db_alternate_consent = lib::create( 'database\alternate_consent' );
            $db_alternate_consent->alternate_id = $db_answer->alternate_id;
            $db_alternate_consent->alternate_consent_type_id = $db_qnaire_alternate_consent_type_trigger->alternate_consent_type_id;
            $db_alternate_consent->accept = $db_qnaire_alternate_consent_type_trigger->accept;
            $db_alternate_consent->written = false;
            $db_alternate_consent->datetime = util::get_datetime_object();
            $db_alternate_consent->note = sprintf(
              'Created by Pine after questionnaire "%s" was completed with question "%s" having the value "%s"',
              $db_qnaire->name,
              $db_question->name,
              $db_qnaire_alternate_consent_type_trigger->answer_value
            );
            $db_alternate_consent->save();
          }
        }
      }

      // create any triggered proxy records
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'qnaire_id', '=', $db_qnaire->id );
      foreach( $qnaire_proxy_type_trigger_class_name::select_objects( $modifier ) as $db_qnaire_proxy_type_trigger )
      {
        if( $this->check_trigger( $db_qnaire_proxy_type_trigger ) )
        {
          $db_question = $db_qnaire_proxy_type_trigger->get_question();

          if( $db_qnaire->debug )
          {
            $db_proxy_type = $db_qnaire_proxy_type_trigger->get_proxy_type();
            log::info( sprintf(
              'Creating new "%s" proxy due to question "%s" having the value "%s" (questionnaire "%s")',
              is_null( $db_proxy_type ) ? 'empty' : $db_proxy_type->name,
              $db_question->name,
              $db_qnaire_proxy_type_trigger->answer_value,
              $db_qnaire->name
            ) );
          }

          $db_proxy = lib::create( 'database\proxy' );
          $db_proxy->participant_id = $db_participant->id;
          $db_proxy->proxy_type_id = $db_qnaire_proxy_type_trigger->proxy_type_id;
          $db_proxy->datetime = util::get_datetime_object();
          $db_proxy->user_id = $session->get_user()->id;
          $db_proxy->site_id = $session->get_site()->id;
          $db_proxy->role_id = $session->get_role()->id;
          $db_proxy->application_id = $session->get_application()->id;
          $db_proxy->note = sprintf(
            'Created by Pine after questionnaire "%s" was completed with question "%s" having the value "%s"',
            $db_qnaire->name,
            $db_question->name,
            $db_qnaire_proxy_type_trigger->answer_value
          );

          // save the proxy file ignoring runtime errors (that denotes a duplicate which we can ignore)
          try { $db_proxy->save(); } catch( \cenozo\exception\runtime $e ) {}
        }
      }
    }
  }

  /**
   * Convenience method to return this response's participant
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
        $db_next_page = $this->get_qnaire()->get_first_module()->get_first_page();
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
          log::warning( sprintf(
            'Tried to advance response for %s to the next page for question "%s", on the current page "%s", '.(
              is_null( $db_answer ) ? 'but the answer doesn\'t exist.' : 'but the answer is incomplete.'
            ),
            $this->get_participant()->uid,
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
    if( !is_null( $db_previous_page ) )
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

    $db_participant = $this->get_participant();

    foreach( $this->get_qnaire()->get_attribute_object_list() as $db_attribute )
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
        $db_response_attribute->value = $db_attribute->get_participant_value( $db_participant );
        $db_response_attribute->save();
      }
    }
  }

  /**
   * Compiles a question's or option's description
   * @param string $description
   * @param boolean $force Whether to force compile values even if they are on the current page
   */
  public function compile_description( $description, $force = false )
  {
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_description_class_name = lib::get_class_name( 'database\question_option_description' );

    $db_qnaire = $this->get_qnaire();

    // Keep converting attributes and questions until there are none left to convert
    // This has to be done in a loop since a question's description may contain other attributes or questions
    $attribute_test = preg_match_all(
      '/@[A-Za-z0-9_]+@/',
      $description,
      $attribute_matches
    );
    $question_test = preg_match_all(
      '/\$([A-Za-z0-9_]+)(:[A-Za-z0-9_]+|.extra\([^)]+\)|.count\(\))?\$/',
      $description,
      $question_matches
    );
    $ignore_question_list = array();

    while( $attribute_test || $question_test )
    {
      // convert attributes
      foreach( $attribute_matches[0] as $match )
      {
        $name = substr( $match, 1, -1 );
        $value = '';
        $db_attribute = $attribute_class_name::get_unique_record(
          array( 'qnaire_id', 'name' ),
          array( $db_qnaire->id, $name )
        );
        if( is_null( $db_attribute ) )
        {
          if( $db_qnaire->debug ) log::warning( sprintf( 'Invalid attribute "%s" found while compiling description', $name ) );
        }
        else
        {
          $db_response_attribute = $response_attribute_class_name::get_unique_record(
            array( 'response_id', 'attribute_id' ),
            array( $this->id, $db_attribute->id )
          );
          $value = $db_response_attribute->value;
        }

        $description = str_replace( $match, $value, $description );
      }

      // convert questions and question options
      foreach( $question_matches[1] as $index => $question_name )
      {
        $matched_expression = $question_matches[0][$index];

        $modifier = lib::create( 'database\modifier' );
        $modifier->where( 'exclusive', '=', false );
        $db_question = $db_qnaire->get_question( $question_name );
        if( is_null( $db_question ) || in_array( $db_question->type, ['comment', 'device'] ) )
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

          if( is_object( $value ) && property_exists( $value, 'dkna' ) && $value->dkna ) $compiled = '(no answer)';
          else if( is_object( $value ) && property_exists( $value, 'refuse' ) && $value->refuse ) $compiled = '(no answer)';
          else if( is_array( $value ) )
          {
            if( '.count' == $question_matches[2][$index] )
            {
              $compiled = count( $value );
            }
            else if( preg_match( '/.extra\((.*)\)/', $question_matches[2][$index], $extra_matches ) )
            {
              $extra_option_name = $extra_matches[1];
              $compiled = '';

              // we only used the matched option when getting the option's name or extra value
              foreach( $value as $option )
              {
                $db_question_option = lib::create( 'database\question_option', is_object( $option ) ? $option->id : $option );
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
                $db_question_option = lib::create( 'database\question_option', is_object( $option ) ? $option->id : $option );
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
                foreach( $question_option_description_class_name::select( $description_sel, $description_mod ) as $row )
                  $answers[] = $row['value'];
              }

              // append any extra option values to the list
              if( 0 < count( $raw_answer_list ) ) $answers = array_merge( $answers, $raw_answer_list );
              $compiled = implode( ', ', $answers );
            }
          }
          else if( is_null( $value ) ) $compiled = '';
          else if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
          else if( 'audio' == $db_question->type )
            $compiled = sprintf( '<audio controls class="full-width" style="height: 40px;" src="%s"></audio>', $value );
          else $compiled = $value;

          $description = str_replace( $matched_expression, $compiled, $description );
        }
      }

      // now determine if there are more attributes or questions to decode in the description
      $attribute_test = preg_match_all(
        '/@[A-Za-z0-9_]+@/',
        $description,
        $attribute_matches
      );
      $question_test = preg_match_all(
        '/\$([A-Za-z0-9_]+)(:[A-Za-z0-9_]+|.extra\([^)]+\)|.count\(\))?\$/',
        $description,
        $question_matches
      );
    }

    foreach( array_unique( $ignore_question_list ) as $question )
    {
      $description = str_replace( trim( $question, '$' ), $question, $description );
    }

    return $description;
  }

  /**
   * Determines whether a consent trigger should create a new record
   * @param database\record $db_trigger A qnaire_*_trigger record
   * @return boolean
   */
  private function check_trigger( $db_trigger )
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );

    $create = false;
    $db_question = $db_trigger->get_question();
    $db_answer = $answer_class_name::get_unique_record(
      array( 'response_id', 'question_id' ),
      array( $this->id, $db_question->id )
    );

    if( !is_null( $db_answer ) )
    {
      $answer_value = util::json_decode( $db_answer->value );
      $refuse_check = str_replace( ' ', '', $db_trigger->answer_value );
      if( in_array( $refuse_check, [ '{"dkna":true}', '{"refuse":true}' ] ) )
      {
        $create = $refuse_check == $db_answer->value;
      }
      else if( '{"dkna":true}' != $db_answer->value && '{"refuse":true}' != $db_answer->value )
      {
        if( 'boolean' == $db_question->type )
        {
          $create = ( 'true' === $db_trigger->answer_value && true === $answer_value ) ||
                    ( 'false' === $db_trigger->answer_value && false === $answer_value );
        }
        else if( 'list' == $db_question->type )
        {
          $db_question_option = $question_option_class_name::get_unique_record(
            array( 'question_id', 'name' ),
            array( $db_question->id, $db_trigger->answer_value )
          );
          $create = !is_null( $db_question_option ) && in_array( $db_question_option->id, $answer_value );
        }
        else if( 'number' == $db_question->type )
        {
          $create = (float)$db_trigger->answer_value == $answer_value;
        }
        else // all the rest need a simple comparison
        {
          $create = $db_trigger->answer_value === $answer_value;
        }
      }
    }

    return $create;
  }
}
