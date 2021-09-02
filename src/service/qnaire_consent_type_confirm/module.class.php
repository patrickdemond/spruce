<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire_consent_type_confirm;
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

    $modifier->join( 'qnaire', 'qnaire_consent_type_confirm.qnaire_id', 'qnaire.id' );
    $modifier->join( 'consent_type', 'qnaire_consent_type_confirm.consent_type_id', 'consent_type.id' );
  }
}
