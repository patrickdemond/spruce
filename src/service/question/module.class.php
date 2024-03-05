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

      if( $select->has_column( 'qnaire_dependencies' ) )
      {
        $dependent_records = $db_question->get_dependent_records();
        $select->add_constant(
          util::json_encode( 0 < count( $dependent_records ) ? $dependent_records : null ),
          'qnaire_dependencies'
        );
      }
    }
  }
}
