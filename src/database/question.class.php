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
  public function get_qnaire()
  {
    return $this->get_page()->get_qnaire();
  }

  /**
   * TODO: document
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
   * TODO: document
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

}
