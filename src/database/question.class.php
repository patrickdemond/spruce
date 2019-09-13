<?php
/**
 * question.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\database;
use cenozo\lib, cenozo\log, linden\util;

/**
 * question: record
 */
class question extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'page';

  /**
   * TODO: document
   */
  public function get_previous_question()
  {
    return static::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->page_id, $this->rank - 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_next_question()
  {
    return static::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->page_id, $this->rank + 1 )
    );
  }
}
