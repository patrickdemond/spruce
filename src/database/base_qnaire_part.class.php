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
   * Overrides the parent class
   */
  public function save()
  {
    if( $this->get_qnaire()->readonly ) throw lib::create( 'exception\notice',
      'You cannot make changes to this questionnaire because it is in read-only mode.',
      __METHOD__
    );

    $column_name = sprintf( '%s_id', static::$rank_parent );

    // if we've changed the rank parent id then re-order all other objects in the old parent which came after this record
    $old_rank_parent_id = NULL;
    $old_rank = NULL;
    if( $this->has_column_changed( $column_name ) )
    {
      $old_rank_parent_id = $this->get_passive_column_value( $column_name );
      $old_rank = $this->get_passive_column_value( 'rank' );
    }

    // make room in the ranks of the new parent
    if( !is_null( $old_rank_parent_id ) && !is_null( $old_rank ) )
    {
      $sql = sprintf(
        'UPDATE %s '.
        'SET rank = rank + 1 '.
        'WHERE %s = %d '.
        'AND rank >= %d '.
        'ORDER by rank DESC',
        static::get_table_name(),
        $column_name,
        $this->$column_name,
        $this->rank
      );
      static::db()->execute( $sql );
    }

    parent::save();

    // reorder the ranks in the old parent
    if( !is_null( $old_rank_parent_id ) && !is_null( $old_rank ) )
    {
      $sql = sprintf(
        'UPDATE %s '.
        'SET rank = rank - 1 '.
        'WHERE %s = %d '.
        'AND rank > %d',
        static::get_table_name(),
        $column_name,
        $old_rank_parent_id,
        $old_rank
      );
      static::db()->execute( $sql );
    }
  }

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

  /**
   * TODO: document
   */
  public function clone_from( $db_source )
  {
    $ignore_columns = array( 'id', 'update_timestamp', 'create_timestamp', static::$rank_parent.'_id', 'qnaire_id', 'rank', 'name' );
    foreach( $this->get_column_names() as $column_name )
      if( !in_array( $column_name, $ignore_columns ) )
        $this->$column_name = $db_source->$column_name;
    $this->save();

    // now copy the descriptions
    $subject = $this->get_table_name();
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( sprintf( 'destination.%s_id', $subject ), '=', $this->id );
    $modifier->where( sprintf( 'source.%s_id', $subject ), '=', $db_source->id );
    $sql = sprintf(
      'UPDATE %s_description AS destination '.
      'JOIN %s_description AS source ON destination.language_id = source.language_id AND destination.type = source.type '.
      'SET destination.value = source.value %s',
      $subject,
      $subject,
      $modifier->get_sql()
    );
    static::db()->execute( $sql );
  }
}
