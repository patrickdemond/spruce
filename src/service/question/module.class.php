<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\question;
use cenozo\lib, cenozo\log, linden\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    $modifier->join( 'page', 'question.page_id', 'page.id' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    $db_question = $this->get_resource();
    if( !is_null( $db_question ) )
    {
      if( $select->has_column( 'previous_question_id' ) )
      {
        $db_previous_question = $db_question->get_previous_question();
        $select->add_constant( is_null( $db_previous_question ) ? NULL : $db_previous_question->id, 'previous_question_id', 'integer' );
      }
      if( $select->has_column( 'next_question_id' ) )
      {
        $db_next_question = $db_question->get_next_question();
        $select->add_constant( is_null( $db_next_question ) ? NULL : $db_next_question->id, 'next_question_id', 'integer' );
      }
    }
  }
}
