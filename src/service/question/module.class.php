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
      $db_page = $db_question->get_page();
      $db_module = $db_page->get_module();

      // module details
      if( $select->has_column( 'previous_module_id' ) )
      {
        $db_previous_module = $db_module->get_previous_module();
        $select->add_constant( is_null( $db_previous_module ) ? NULL : $db_previous_module->id, 'previous_module_id', 'integer' );
      }
      if( $select->has_column( 'next_module_id' ) )
      {
        $db_next_module = $db_module->get_next_module();
        $select->add_constant( is_null( $db_next_module ) ? NULL : $db_next_module->id, 'next_module_id', 'integer' );
      }
      if( $select->has_column( 'last_module' ) )
      {
        $select->add_constant( $db_module->is_last(), 'last_module', 'boolean' );
      }

      // page details
      if( $select->has_column( 'previous_page_id' ) )
      {
        $db_previous_page = $db_page->get_previous_page();
        $select->add_constant( is_null( $db_previous_page ) ? NULL : $db_previous_page->id, 'previous_page_id', 'integer' );
      }
      if( $select->has_column( 'next_page_id' ) )
      {
        $db_next_page = $db_page->get_next_page();
        $select->add_constant( is_null( $db_next_page ) ? NULL : $db_next_page->id, 'next_page_id', 'integer' );
      }
      if( $select->has_column( 'last_page' ) )
      {
        $select->add_constant( $db_page->is_last(), 'last_page', 'boolean' );
      }

      // question details
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
      if( $select->has_column( 'last_question' ) )
      {
        $select->add_constant( $db_question->is_last(), 'last_question', 'boolean' );
      }
    }
  }
}
