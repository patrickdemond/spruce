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
    if( $changing_name )
    {
      $question_name = $this->get_question()->name;

      // question options will take the form of $QUESTION:OPTION$ or $QUESTION.extra(OPTION)$
      $regex = sprintf(
        '\\$(%s((\\.extra\\( *%s *\\))|(:%s)))\\$',
        $question_name,
        $old_name,
        $old_name
      );
      $replace_list = [
        sprintf( '$%s:%%s$', $question_name ),
        sprintf( '$%s.extra(%%s)$', $question_name )
      ];

      $this->replace_in_qnaire( $regex, $replace_list, $old_name, $this->name );
    }
  }

  /**
   * Overview parent method
   */
  public function get_qnaire()
  {
    return $this->get_question()->get_qnaire();
  }

  /** 
   * Returns a list of all qnaire records dependent on this question's name
   * @return associative array
   */
  public function get_dependent_records()
  {
    $question_name = $this->get_question()->name;

    // question options will take the form of $QUESTION:OPTION$ or $QUESTION.extra(OPTION)$
    return parent::get_qnaire_dependent_records( sprintf(
      '\\$(%s((\\.extra\\( *%s *\\))|(:%s)))\\$',
      $question_name,
      $this->name,
      $this->name
    ) );
  }
}
