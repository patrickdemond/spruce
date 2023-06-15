<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\answer_device;
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

    $modifier->join( 'answer', 'answer_device.answer_id', 'answer.id' );
    $modifier->join( 'question', 'answer.question_id', 'question.id' );
    $modifier->join( 'device', 'question.device_id', 'device.id' );
    $modifier->join( 'response', 'answer.response_id', 'response.id' );
    $modifier->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
  }
}
