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
   * Override the parent method
   */
  public function save()
  {
    $script_class_name = lib::get_class_name( 'database\script' );

    $new = is_null( $this->id );
    $submitted = $this->has_column_changed( 'submitted' ) && $this->submitted;
    $db_respondent = NULL;

    // setup new responses
    if( $new )
    {
      $db_respondent = lib::create( 'database\respondent', $this->respondent_id );
      $db_qnaire = $db_respondent->get_qnaire();
      $db_current_response = $db_respondent->get_current_response();

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

    parent::save();

    // create the new response's attributes
    if( $new ) $this->create_attributes();

    // see if the qnaire exists as a script and apply the started/finished events if it does
    if( $new || $submitted )
    {
      if( is_null( $db_respondent ) ) $db_respondent = $this->get_respondent();
      $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
      if( !is_null( $db_script ) )
      {
        if( $new ) $db_script->add_started_event( $this->get_participant(), $this->last_datetime );
        else if( $submitted ) $db_script->add_finished_event( $this->get_participant(), $this->last_datetime );
      }
    }

    // when submitting the response remove any pending email reminders
    if( $submitted )
    {
      $db_reminder_mail = $this->get_respondent()->get_reminder_mail( $this->rank );
      if( !is_null( $db_reminder_mail ) && is_null( $db_reminder_mail->sent_datetime ) ) $db_reminder_mail->delete();
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
   * Moves the response to the next valid page
   * 
   * @access public
   */
  public function move_to_next_page()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response to the next page.' );
      return;
    }

    $db_page = $this->get_page();
    $db_next_page = NULL;

    if( is_null( $db_page ) )
    { // the qnaire has never been started
      $db_next_page = $this->get_qnaire()->get_first_module()->get_first_page();
      $this->page_id = $db_next_page->id;
      $this->start_datetime = util::get_datetime_object();
      $this->save();
    }
    else // we've already started the qnaire
    {
      // make sure that all questions on the current page are finished
      $complete = true;

      $object_list = array();
      $question_mod = lib::create( 'database\modifier' );
      $question_mod->where( 'type', '!=', 'comment' ); // comments don't have answers
      foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
      {
        $db_answer = $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $this->id, $db_question->id )
        );

        if( !$db_answer->is_complete() )
        {
          log::warning( sprintf(
            'Tried to advance response for %s to the next page but question, "%s", on the current page, "%s", is incomplete.',
            $this->get_participant()->uid,
            $db_question->name,
            $db_page->name
          ) );

          $complete = false;
          break;
        }

        $object_list[] = array( 'question' => $db_question, 'answer' => $db_answer );
      }

      if( $complete )
      {
        // before proceeding remove any empty option values
        foreach( $object_list as $objects )
          if( 'list' == $objects['question']->type )
            $db_answer->remove_empty_answer_values();

        // record the time spent on the page (add time if there is already time set)
        $db_page_time = $page_time_class_name::get_unique_record(
          array( 'response_id', 'page_id' ),
          array( $this->id, $db_page->id )
        );
        if( is_null( $db_page_time->time ) ) $db_page_time->time = 0;
        $microtime = microtime();
        $db_page_time->time += (
          util::get_datetime_object()->getTimestamp() + substr( $microtime, 0, strpos( $microtime, ' ' ) ) -
          $db_page_time->datetime->getTimestamp() - $db_page_time->microtime
        );
        $db_page_time->save();

        $db_next_page = $db_page->get_next_for_response( $this );
        if( is_null( $db_next_page ) )
        {
          $this->page_id = NULL;
          $this->submitted = true;
        }
        else
        {
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
   * Moves the response to the previous valid page
   * 
   * @access public
   */
  public function move_to_previous_page()
  {
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response to the previous page.' );
      return;
    }

    $db_page = $this->get_page();

    // record the time spent on the page (add time if there is already time set)
    $db_page_time = $page_time_class_name::get_unique_record(
      array( 'response_id', 'page_id' ),
      array( $this->id, $db_page->id )
    );
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
      $this->page_id = $db_previous_page->id;
      $this->save();

      // set the datetime that this page was started
      $db_previous_page_time = $page_time_class_name::get_unique_record(
        array( 'response_id', 'page_id' ),
        array( $this->id, $db_previous_page->id )
      );

      $microtime = microtime();
      $db_previous_page_time->datetime = util::get_datetime_object();
      $db_previous_page_time->microtime = substr( $microtime, 0, strpos( $microtime, ' ' ) );
      $db_previous_page_time->save();
    }
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
   * TODO: document
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
   * TODO: document
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
   * TODO: document
   */
  public function create_attributes()
  {
    $db_participant = $this->get_participant();

    foreach( $this->get_qnaire()->get_attribute_object_list() as $db_attribute )
    {
      $db_response_attribute = lib::create( 'database\response_attribute' );
      $db_response_attribute->response_id = $this->id;
      $db_response_attribute->attribute_id = $db_attribute->id;
      $db_response_attribute->value = $db_attribute->get_participant_value( $db_participant );
      $db_response_attribute->save();
    }
  }

  /**
   * TODO: document
   */
  public function compile_description( $description )
  {
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $answer_class_name = lib::get_class_name( 'database\answer' );

    $db_qnaire = $this->get_qnaire();

    // convert attributes
    preg_match_all( '/@[A-Za-z0-9_]+@/', $description, $matches );
    foreach( $matches[0] as $match )
    {
      $attribute_name = substr( $match, 1, -1 );
      $db_attribute = $attribute_class_name::get_unique_record(
        array( 'qnaire_id', 'name' ),
        array( $db_qnaire->id, $attribute_name )
      );

      if( is_null( $db_attribute ) )
      {
        if( !$db_qnaire->debug )
        {
          log::warning( sprintf( 'Invalid attribute "%s" found while compiling description', $attribute_name ) );
          $description = str_replace( $match, '', $description );
        }
      }
      else
      {
        $db_response_attribute = $response_attribute_class_name::get_unique_record(
          array( 'response_id', 'attribute_id' ),
          array( $this->id, $db_attribute->id )
        );
        $description = str_replace( $match, $db_response_attribute->value, $description );
      }
    }

    // convert questions
    preg_match_all( '/\$[A-Za-z0-9_]+\$/', $description, $matches );
    foreach( $matches[0] as $match )
    {
      $question_name = substr( $match, 1, -1 );
      $db_question = $db_qnaire->get_question( $question_name );
      if( is_null( $db_question ) || 'comment' == $db_question->type || 'list' == $db_question->type )
      {
        if( !$db_qnaire->debug )
        {
          log::warning( sprintf( 'Invalid question "%s" found while compiling description', $question_name ) );
          $description = str_replace( $match, '', $description );
        }
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
        else if( is_null( $value ) ) $compiled = '';
        else if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
        else $compiled = $value;

        $description = str_replace( $match, $compiled, $description );
      }
    }

    return $description;
  }
}
