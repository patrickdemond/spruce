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
   * Determines if this is the last stage
   * @return boolean
   */
  public function is_last_stage()
  {
    $select = lib::create( 'database\select' );
    $select->add_column( 'MAX( rank )', 'max_rank', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'qnaire_id', '=', $this->qnaire_id );
    $list = static::select( $select, $modifier );
    return current( $list )['max_rank'] == $this->rank;
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
   * Returns the stage's first module for a response
   * 
   * @param database\response $db_response
   * @return database\module
   */
  public function get_first_module_for_response( $db_response )
  {
    // start by getting the first module
    $db_first_module = $this->get_first_module();
    if( is_null( $db_first_module ) ) return NULL;

    // Get the first module whose precondition meets the response (starting by testing the first)
    $db_module = $db_first_module->get_next_for_response( $db_response, true );

    // It's possible there is no next module, or the module doesn't belong to this stage
    if( is_null( $db_module ) || $this->id != $db_module->get_stage()->id ) return NULL;

    return $db_module;
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

  /**
   * Applies a patch file to the stage and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param string $name_suffix A temporary string used to prevent name collisions
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $name_suffix, $apply = false )
  {
    $module_class_name = lib::get_class_name( 'database\module' );
    $difference_list = array();

    foreach( $patch_object as $property => $value )
    {
      $different = false;
      if( in_array( $property, ['first_module_rank', 'last_module_rank'] ) )
      {
        $db_module = 'first_module_rank' == $property ? $this->get_first_module() : $this->get_last_module();
        $different = is_null( $db_module ) || $patch_object->$property != $db_module->rank;
      }
      else $different = $patch_object->$property != $this->$property;

      if( $different )
      {
        if( $apply )
        {
          if( in_array( $property, ['first_module_rank', 'last_module_rank'] ) )
          {
            $column_name = str_replace( '_rank', '_id', $property );
            $this->$column_name = $module_class_name::get_unique_record(
              array( 'qnaire_id', 'rank' ),
              array( $this->qnaire_id, $patch_object->$property )
            )->id;
          }
          else
          {
            $this->$property = 'name' == $property
                             ? sprintf( '%s_%s', $patch_object->$property, $name_suffix )
                             : $patch_object->$property;
          }
        }
        else $difference_list[$property] = $patch_object->$property;
      }
    }

    if( $apply )
    {
      $this->save();
      return null;
    }
    else return 0 == count( $difference_list ) ? NULL : (object)$difference_list;
  }
}

