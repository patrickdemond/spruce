<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\answer_extra;
use cenozo\lib, cenozo\log, pine\util;

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
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'answer', 'answer_extra.answer_id', 'answer.id' );
    $modifier->join( 'question_option', 'answer_extra.question_option_id', 'question_option.id' );

    if( $select->has_column( 'value' ) )
    {
      $select->add_column(
        'CASE question_option.extra '.
          'WHEN "boolean" THEN answer_extra.value_boolean '.
          'WHEN "number" THEN answer_extra.value_number '.
          'WHEN "string" THEN answer_extra.value_string '.
          'WHEN "text" THEN answer_extra.value_text '.
        'END',
        'value',
        false
      );
    }
  }
}
