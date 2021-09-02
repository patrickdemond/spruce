<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire_consent_type_trigger;
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

    $modifier->join( 'qnaire', 'qnaire_consent_type_trigger.qnaire_id', 'qnaire.id' );
    $modifier->join( 'consent_type', 'qnaire_consent_type_trigger.consent_type_id', 'consent_type.id' );
    $modifier->join( 'question', 'qnaire_consent_type_trigger.question_id', 'question.id' );

    $db_qnaire_consent_type_trigger = $this->get_resource();
    if( !is_null( $db_qnaire_consent_type_trigger ) )
    {
      // include the question name and type as supplemental data
      $select->add_column(
        'CONCAT( question.name, " (", question.type, ")" )',
        'formatted_question_id',
        false
      );
    }
  }
}
