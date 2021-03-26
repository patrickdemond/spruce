<?php
/**
 * question.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * question: record
 */
class question extends base_qnaire_part
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'page';

  /**
   * Overview parent method
   */
  public function save()
  {
    parent::save();

    // remove all question options if the question's type isn't list
    if( 'list' != $this->type && 0 < $this->get_question_option_count() ) $this->remove_question_option( NULL );
  }

  /**
   * Overview parent method
   */
  public function get_qnaire()
  {
    return $this->get_page()->get_qnaire();
  }

  /**
   * Returns the previous question (even if it is on the previous page)
   * @return database\question
   */
  public function get_previous()
  {
    $db_previous_question = parent::get_previous();

    if( is_null( $db_previous_question ) )
    {
      $db_previous_page = $this->get_page()->get_previous();
      if( !is_null( $db_previous_page ) ) $db_previous_question = $db_previous_page->get_last_question();
    }

    return $db_previous_question;
  }

  /**
   * Returns the next question (event if it is on the next page)
   */
  public function get_next()
  {
    $db_next_question = parent::get_next();

    if( is_null( $db_next_question ) )
    {
      $db_next_page = $this->get_page()->get_next();
      if( !is_null( $db_next_page ) ) $db_next_question = $db_next_page->get_first_question();
    }

    return $db_next_question;
  }

  /**
   * Clones another question
   * @param database\question $db_source_question
   */
  public function clone_from( $db_source_question )
  {
    parent::clone_from( $db_source_question );

    // replace all existing question options with those from the clone source
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'question_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM question_option %s', $modifier->get_sql() ) );

    foreach( $db_source_question->get_question_option_object_list() as $db_source_question_option )
    {
      $db_question_option = lib::create( 'database\question_option' );
      $db_question_option->question_id = $this->id;
      $db_question_option->rank = $db_source_question_option->rank;
      $db_question_option->name = $db_source_question_option->name;
      $db_question_option->clone_from( $db_source_question_option );
    }
  }
}
