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
      'JOIN %s_description AS source ON destination.language_id = source.language_id '.
      'SET destination.value = source.value %s',
      $subject,
      $subject,
      $modifier->get_sql()
    );
    static::db()->execute( $sql );
  }
}
