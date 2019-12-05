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
class response extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    $script_class_name = lib::get_class_name( 'database\script' );

    $new = is_null( $this->id );
    $submitted = $this->has_column_changed( 'submitted' ) && $this->submitted;

    // setup new responses
    if( $new )
    {
      $db_participant = lib::create( 'database\participant', $this->participant_id );
      $this->language_id = $db_participant->language_id;
      $this->token = static::generate_token();
    }

    parent::save();

    // see if the qnaire exists as a script and apply the started/finished events if it does
    if( $new || $submitted )
    {
      $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $this->qnaire_id );
      if( !is_null( $db_script ) )
      {
        if( $new ) $db_script->add_started_event( $this->get_participant(), $this->last_datetime );
        else if( $submitted ) $db_script->add_finished_event( $this->get_participant(), $this->last_datetime );
      }
    }
  }

  /**
   * Moves the response to the next valid page
   * 
   * TODO: page restriction logic still needs to be applied here
   * @access public
   */
  public function move_to_next_page()
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );

    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response to the next page.' );
      return;
    }

    $db_page = $this->get_page();
    
    if( is_null( $db_page ) )
    { // the qnaire has never been started
      $this->page_id = $this->get_qnaire()->get_first_module()->get_first_page()->id;
      $this->start_datetime = util::get_datetime_object();
      $this->save();
    }
    else // we've already started the qnaire
    {
      // make sure that all questions on the current page are finished
      $complete = true;

      $object_list = array();
      foreach( $db_page->get_question_object_list() as $db_question )
      {
        $db_answer = $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $this->id, $db_question->id )
        );

        if( !$db_answer->is_complete() )
        {
          log::warning( sprintf(
            'Tried to advance response for %s to the next page but the current page "%s" is incomplete.',
            $this->get_participant()->uid,
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
  }

  /**
   * Moves the response to the previous valid page
   * 
   * TODO: page restriction logic still needs to be applied here
   * @access public
   */
  public function move_to_previous_page()
  {
    if( $this->submitted )
    {
      log::warning( 'Tried to move submitted response to the previous page.' );
      return;
    }

    $db_previous_page = $this->get_page()->get_previous_for_response( $this );
    if( !is_null( $db_previous_page ) )
    {
      $this->page_id = $db_previous_page->id;
      $this->save();
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
   * Creates a unique token to be used for identifying a response
   * 
   * @access private
   */
  private static function generate_token()
  {
    $created = false;
    $count = 0;
    while( 100 > $count++ )
    {
      $token = sprintf(
        '%s-%s-%s-%s',
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) )
      );

      // make sure it isn't already in use
      if( null == static::get_unique_record( 'token', $token ) ) return $token;
    }

    // if we get here then something is wrong
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique response token.', __METHOD__ );
  }

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
}
