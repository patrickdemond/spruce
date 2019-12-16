<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

abstract class base_qnaire_part_post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function prepare()
  {
    parent::prepare();

    $clone_id = $this->get_argument( 'clone', NULL );
    if( !is_null( $clone_id ) )
    {
      $subject = $this->get_leaf_subject();
      $subject_class_name = lib::get_class_name( sprintf( 'database\%s', $subject ) );
      $record = $this->get_leaf_record();
      $clone_record = lib::create( sprintf( 'database\%s', $subject ), $clone_id );
      $ignore_columns = array(
        'id', 'update_timestamp', 'create_timestamp', $subject_class_name::get_rank_parent(), 'qnaire_id', 'rank', 'name'
      );
      
      foreach( $record->get_column_names() as $column_name )
        if( !in_array( $column_name, $ignore_columns ) )
          $record->$column_name = $clone_record->$column_name;
    }
  }

  /**
   * Extends parent method
   */
  protected function finish()
  {
    parent::finish();

    $clone_id = $this->get_argument( 'clone', NULL );
    if( !is_null( $clone_id ) )
    {
      $subject = $this->get_leaf_subject();
      $record = $this->get_leaf_record();
      $clone_record = lib::create( sprintf( 'database\%s', $subject ), $clone_id );
      $record->copy_descriptions( $clone_record );
    }
  }
}
