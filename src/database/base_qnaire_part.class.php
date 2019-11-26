<?php
/**
 * base_qnaire_part.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * base_qnaire_part: abstract class for module, page, question and question_option
 */
abstract class base_qnaire_part extends \cenozo\database\has_rank
{
  /**
   * TODO: document
   */
  public function get_previous()
  {
    $column_name = sprintf( '%s_id', static::$rank_parent );
    return static::get_unique_record(
      array( $column_name, 'rank' ),
      array( $this->$column_name, $this->rank - 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_next()
  {
    $column_name = sprintf( '%s_id', static::$rank_parent );
    return static::get_unique_record(
      array( $column_name, 'rank' ),
      array( $this->$column_name, $this->rank + 1 )
    );
  }
}
