<?php
/**
 * stage.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * stage: record
 */
class stage extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'qnaire';

  /**
   * Returns the first module, or NULL of none is set
   * @return database\module
   */
  public function get_first_module()
  {
    return is_null( $this->first_module_id ) ? NULL : lib::create( 'database\module', $this->first_module_id );
  }

  /**
   * Returns the last module, or NULL of none is set
   * @return database\module
   */
  public function get_last_module()
  {
    return is_null( $this->last_module_id ) ? NULL : lib::create( 'database\module', $this->last_module_id );
  }

  /**
   * Returns the prev stage
   * @return database\stage
   */
  public function get_previous()
  {
    return static::get_unique_record( array( 'qnaire_id', 'rank' ), array( $this->qnaire_id, $this->rank - 1 ) );
  }

  /**
   * Returns the next stage
   * @return database\stage
   */
  public function get_next()
  {
    return static::get_unique_record( array( 'qnaire_id', 'rank' ), array( $this->qnaire_id, $this->rank + 1 ) );
  }

  /**
   * Clones another stage
   * @param database\stage $db_stage
   */
  public function clone_from( $db_stage )
  {
    $module_class_name = lib::get_class_name( 'database\module' );

    $this->precondition = $db_stage->precondition;

    // find the coinciding first/last modules by rank
    $this->first_module_id = $module_class_name::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $db_stage->get_first_module()->rank )
    )->id;

    $this->last_module_id = $module_class_name::get_unique_record(
      array( 'qnaire_id', 'rank' ),
      array( $this->qnaire_id, $db_stage->get_last_module()->rank )
    )->id;

    $this->save();
  }
}
