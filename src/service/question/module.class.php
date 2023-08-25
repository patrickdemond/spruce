<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question;
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

    // add the total number of question_options
    if( $select->has_column( 'question_option_count' ) )
      $this->add_count_column( 'question_option_count', 'question_option', $select, $modifier );

    $modifier->join( 'page', 'question.page_id', 'page.id' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $modifier->left_join( 'device', 'question.device_id', 'device.id' );
    $modifier->left_join( 'equipment_type', 'question.equipment_type_id', 'equipment_type.id' );
    $modifier->left_join( 'lookup', 'question.lookup_id', 'lookup.id' );

    $db_question = $this->get_resource();
    if( !is_null( $db_question ) )
    {
      $db_qnaire = $db_question->get_qnaire();

      // questions appear in preconditions proceeded by a $ and followed by either a $ (direct reference), : (option) or . (function)
      $match = sprintf( '\\$%s[$:.]', $db_question->name );

      if( $select->has_column( 'module_precondition_dependencies' ) )
      {
        // get a list of all modules which have a precondition referring to this question
        $module_sel = lib::create( 'database\select' );
        $module_sel->add_column( 'name' );
        $module_mod = lib::create( 'database\modifier' );
        $module_mod->where( 'module.precondition', 'RLIKE', $match );

        $module_list = array();
        foreach( $db_qnaire->get_module_list( $module_sel, $module_mod ) as $module ) $module_list[] = $module['name'];
        $select->add_constant(
          0 < count( $module_list ) ? implode( ', ', $module_list ) : NULL,
          'module_precondition_dependencies'
        );
      }

      if( $select->has_column( 'page_precondition_dependencies' ) )
      {
        // get a list of all pages which have a precondition referring to this question
        $page_sel = lib::create( 'database\select' );
        $page_sel->add_table_column( 'page', 'name' );
        $page_mod = lib::create( 'database\modifier' );
        $page_mod->join( 'module', 'qnaire.id', 'module.qnaire_id' );
        $page_mod->join( 'page', 'module.id', 'page.module_id' );
        $page_mod->where( 'page.precondition', 'RLIKE', $match );

        $page_list = array();
        foreach( $db_qnaire->select( $page_sel, $page_mod ) as $page ) $page_list[] = $page['name'];
        $select->add_constant(
          0 < count( $page_list ) ? implode( ', ', $page_list ) : NULL,
          'page_precondition_dependencies'
        );
      }

      if( $select->has_column( 'question_precondition_dependencies' ) )
      {
        // get a list of all questions which have a precondition referring to this question
        $question_sel = lib::create( 'database\select' );
        $question_sel->add_table_column( 'question', 'name' );
        $question_mod = lib::create( 'database\modifier' );
        $question_mod->join( 'module', 'qnaire.id', 'module.qnaire_id' );
        $question_mod->join( 'page', 'module.id', 'page.module_id' );
        $question_mod->join( 'question', 'page.id', 'question.page_id' );
        $question_mod->where( 'question.precondition', 'RLIKE', $match );

        $question_list = array();
        foreach( $db_qnaire->select( $question_sel, $question_mod ) as $question ) $question_list[] = $question['name'];
        $select->add_constant(
          0 < count( $question_list ) ? implode( ', ', $question_list ) : NULL,
          'question_precondition_dependencies'
        );
      }

      if( $select->has_column( 'question_option_precondition_dependencies' ) )
      {
        // get a list of all question options which have a precondition referring to this question
        $question_option_sel = lib::create( 'database\select' );
        $question_option_sel->add_table_column( 'question', 'name', 'qname' );
        $question_option_sel->add_table_column( 'question_option', 'name', 'oname' );
        $question_option_mod = lib::create( 'database\modifier' );
        $question_option_mod->join( 'module', 'qnaire.id', 'module.qnaire_id' );
        $question_option_mod->join( 'page', 'module.id', 'page.module_id' );
        $question_option_mod->join( 'question', 'page.id', 'question.page_id' );
        $question_option_mod->join( 'question_option', 'question.id', 'question_option.question_id' );
        $question_option_mod->where( 'question_option.precondition', 'RLIKE', $match );

        $question_option_list = array();
        foreach( $db_qnaire->select( $question_option_sel, $question_option_mod ) as $question_option )
          $question_option_list[] = sprintf( '%s[%s]', $question_option['qname'], $question_option['oname'] );
        $select->add_constant(
          0 < count( $question_option_list ) ? implode( ', ', $question_option_list ) : NULL,
          'question_option_precondition_dependencies'
        );
      }
    }
  }
}
