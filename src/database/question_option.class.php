<?php
/**
 * question_option.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * question_option: record
 */
class question_option extends base_qnaire_part
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'question';

  /**
   * Overview parent method
   */
  public function save()
  {
    $changing_name = !is_null( $this->id ) && $this->has_column_changed( 'name' );
    $old_name = $this->get_passive_column_value( 'name' );

    parent::save();

    // update all preconditions if the question's name is changing
    if( $changing_name ) $this->get_qnaire()->update_name_in_preconditions( $this, $old_name );
  }

  /**
   * Overview parent method
   */
  public function get_qnaire()
  {
    return $this->get_question()->get_qnaire();
  }
}
