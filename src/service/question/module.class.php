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
    $answer_class_name = lib::get_class_name( 'database\answer' );

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
      if( $select->has_column( 'qnaire_dependencies' ) )
      {
        $dependent_records = $db_question->get_dependent_records();
        $select->add_constant(
          util::json_encode( 0 < count( $dependent_records ) ? $dependent_records : null ),
          'qnaire_dependencies'
        );
      }

      if( $select->has_column( 'answer_summary' ) )
      {
        $answer_summary = [];
        if( 'boolean' == $db_question->type )
        {
          $answer_sel = lib::create( 'database\select' );
          $answer_sel->add_table_column( 'answer', 'value' );
          $answer_sel->add_column( 'COUNT(*)', 'total', false );
          $answer_mod = lib::create( 'database\modifier' );
          $answer_mod->where( 'answer.value', '!=', 'null' );
          $answer_mod->group( 'answer.value' );
          foreach( $db_question->get_answer_list( $answer_sel, $answer_mod ) as $answer )
          {
            $value = $answer['value'];
            if( $answer_class_name::DKNA == $value ) $value = 'DKNA';
            else if( $answer_class_name::REFUSE == $value ) $value = 'REFUSE';
            else if( 'true' == $value ) $value = 'Yes';
            else if( 'false' == $value ) $value = 'No';
            $answer_summary[$value] = $answer['total'];
          }
        }
        else if( 'list' == $db_question->type )
        {
          // first loop through all options and count each that was selected
          $option_sel = lib::create( 'database\select' );
          $option_sel->add_table_column( 'question_option', 'id' );
          $option_sel->add_table_column( 'question_option', 'name' );
          foreach( $db_question->get_question_option_list( $option_sel ) as $question_option )
          {
            $answer_sel = lib::create( 'database\select' );
            $answer_sel->add_column( 'COUNT(*)', 'total', false );
            $answer_mod = lib::create( 'database\modifier' );
            $answer_mod->where( sprintf( 'JSON_SEARCH( value, "one", %d )', $question_option['id'] ), '!=', NULL );
            $answer_mod->group( 'answer.value' );
            foreach( $db_question->get_answer_list( $answer_sel, $answer_mod ) as $answer )
            {
              $answer_summary[$question_option['name']] = $answer['total'];
            }
          }

          // now count the DKKA and REFUSE values
          $answer_sel = lib::create( 'database\select' );
          $answer_sel->add_table_column( 'answer', 'value' );
          $answer_sel->add_column( 'COUNT(*)', 'total', false );
          $answer_mod = lib::create( 'database\modifier' );
          $answer_mod->where( 'answer.value', 'IN', [$answer_class_name::DKNA, $answer_class_name::REFUSE] );
          $answer_mod->group( 'answer.value' );
          foreach( $db_question->get_answer_list( $answer_sel, $answer_mod ) as $answer )
          {
            $value = $answer['value'];
            if( $answer_class_name::DKNA == $value ) $value = 'DKNA';
            else if( $answer_class_name::REFUSE == $value ) $value = 'REFUSE';
            $answer_summary[$value] = $answer['total'];
          }
        }

        $select->add_constant( util::json_encode( $answer_summary ), 'answer_summary' );
      }
    }
  }
}
