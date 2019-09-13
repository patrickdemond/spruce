<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\database;
use cenozo\lib, cenozo\log, linden\util;

/**
 * module: record
 */
class module extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'qnaire';

  /**
   * TODO: document
   */
  public function get_previous_module()
  {
    return static::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $this->rank - 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_next_module()
  {
    return static::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $this->rank + 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_first_page()
  {
    $page_class_name = lib::get_class_name( 'database\page' );
    return $page_class_name::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_last_page()
  {
    $page_class_name = lib::get_class_name( 'database\page' );
    return $page_class_name::get_unique_record(
      array( 'module_id', 'rank' ),
      array( $this->id, $this->get_page_count() )
    );
  }
}
