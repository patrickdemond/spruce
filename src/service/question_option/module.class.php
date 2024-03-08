<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question_option;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \pine\service\base_qnaire_part_module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'question', 'question_option.question_id', 'question.id' );
    $modifier->join( 'page', 'question.page_id', 'page.id' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    $db_question_option = $this->get_resource();
    if( !is_null( $db_question_option ) )
    {
      $db_qnaire = $db_question_option->get_qnaire();

      // question options appear in preconditions proceeded  by a : and followed by a $
      $match = sprintf(
        '\\$(%s((\\.extra\\( *%s *\\))|(:%s)))\\$',
        $db_question_option->get_question()->name,
        $db_question_option->name,
        $db_question_option->name
      );

      if( $select->has_column( 'qnaire_dependencies' ) )
      {
        $dependent_records = $db_question_option->get_dependent_records();
        $select->add_constant(
          util::json_encode( 0 < count( $dependent_records ) ? $dependent_records : null ),
          'qnaire_dependencies'
        );
      }
    }
  }
}
