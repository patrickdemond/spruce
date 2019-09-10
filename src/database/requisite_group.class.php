<?php
/**
 * requisite_group.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\database;
use cenozo\lib, cenozo\log, linden\util;

/**
 * requisite_group: record
 */
class requisite_group extends \cenozo\database\has_rank
{
  /** 
   * Override parent method
   */
  public function save()
  {
    // figure out whether module, page, question, or requisite_group is the rank parent
    if( !is_null( $this->module_id ) ) static::$rank_parent = 'module';
    else if( !is_null( $this->page_id ) ) static::$rank_parent = 'page';
    else if( !is_null( $this->question_id ) ) static::$rank_parent = 'question';
    else static::$rank_parent = 'requisite_group';
    parent::save();
    static::$rank_parent = NULL;
  }

  /** 
   * Override parent method
   */
  public function delete()
  {
    // figure out whether module, page, question, or requisite_group is the rank parent
    if( !is_null( $this->module_id ) ) static::$rank_parent = 'module';
    else if( !is_null( $this->page_id ) ) static::$rank_parent = 'page';
    else if( !is_null( $this->question_id ) ) static::$rank_parent = 'question';
    else static::$rank_parent = 'requisite_group';
    parent::delete();
    static::$rank_parent = NULL;
  }

  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = NULL;
}
