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
   * Overview parent method
   */
  public function save()
  {
    $changing_name = !is_null( $this->id ) && $this->has_column_changed( 'name' );
    $old_name = $this->get_passive_column_value( 'name' );

    parent::save();

    // update all preconditions if the stage's name is changing
    if( $changing_name ) $this->get_qnaire()->update_name_in_preconditions( 'stage', $old_name, $this->name ); 
  }

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
   * Returns the total number of pages in the stage
   * @return integer
   */
  public function get_number_of_pages()
  {
    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_constant( 'COUNT(*)', 'total', 'integer', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->where( 'module.qnaire_id', '=', $this->qnaire_id );
    $modifier->where( 'module.rank', '>=', $this->get_first_module()->rank );
    $modifier->where( 'module.rank', '<=', $this->get_last_module()->rank );
    return static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
  }

  /**
   * Extend parent method since stage and module do not have a regular 1 to N relationship
   */
  public function get_record_list( $record_type, $select = NULL, $modifier = NULL, $return_alternate = '', $distinct = false )
  {
    $return_value = NULL;

    if( 'module' == $record_type )
    {
      $module_class_name = lib::get_class_name( 'database\module' );

      if( !is_null( $select ) && !is_a( $select, lib::get_class_name( 'database\select' ) ) )
        throw lib::create( 'exception\argument', 'select', $select, __METHOD__ );
      if( !is_null( $modifier ) && !is_a( $modifier, lib::get_class_name( 'database\modifier' ) ) )
        throw lib::create( 'exception\argument', 'modifier', $modifier, __METHOD__ );
      if( !is_string( $return_alternate ) )
        throw lib::create( 'exception\argument', 'return_alternate', $return_alternate, __METHOD__ );

      if( is_null( $modifier ) ) $modifier = lib::create( 'database\modifier' );

      $modifier->wrap_where();
      $modifier->where( 'module.qnaire_id', '=', $this->qnaire_id );
      $modifier->where( 'module.rank', '>=', $this->get_first_module()->rank );
      $modifier->where( 'module.rank', '<=', $this->get_last_module()->rank );
      $modifier->order( 'module.rank' );

      if( 'count' == $return_alternate )
      {
        $return_value = $module_class_name::count( $modifier, $distinct );
      }
      else
      {
        $return_value = 'object' == $return_alternate
                      ? $module_class_name::select_objects( $modifier )
                      : $module_class_name::select( $select, $modifier );
      }
    }
    else
    {
      $return_value = parent::get_record_list( $record_type, $select, $modifier, $return_alternate, $distinct );
    }

    return $return_value;
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
