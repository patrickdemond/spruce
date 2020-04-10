<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\question;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function get_leaf_parent_relationship()
  {
    $relationship_class_name = lib::get_class_name( 'database\relationship' );
    return $relationship_class_name::MANY_TO_MANY;

  }

  /**
   * Extend parent method
   */
  protected function get_record_count()
  {
    // count all questions belonging to the parent qnaire
    $question_class_name = lib::get_class_name( 'database\question' );
    $modifier = clone $this->modifier;
    $modifier->where( 'qnaire.id', '=', $this->get_parent_record()->id );
    return $question_class_name::count( $modifier );
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    // list all questions belonging to the parent qnaire
    $question_class_name = lib::get_class_name( 'database\question' );
    $modifier = clone $this->modifier;
    $modifier->where( 'qnaire.id', '=', $this->get_parent_record()->id );
    if( $modifier->has_order( 'rank' ) )
    {
      $modifier->replace_order( 'rank', 'module.rank' );
      $modifier->order( 'page.rank' );
      $modifier->order( 'question.rank' );
    }
    return $question_class_name::select( $this->select, $modifier );
  }
}
