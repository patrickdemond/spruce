<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response_attribute;
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

    $modifier->join( 'attribute', 'response_attribute.attribute_id', 'attribute.id' );
    $modifier->join( 'response', 'response_attribute.response_id', 'response.id' );
    $modifier->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $modifier->left_join( 'participant', 'respondent.participant_id', 'participant.id' );
    $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
  }
}
