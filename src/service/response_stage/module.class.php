<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response_stage;
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

    $modifier->join( 'response', 'response_stage.response_id', 'response.id' );
    $modifier->join( 'stage', 'response_stage.stage_id', 'stage.id' );
    $modifier->left_join( 'page', 'response_stage.page_id', 'page.id' );
    $modifier->left_join( 'user', 'response_stage.user_id', 'user.id' );
    $modifier->left_join( 'deviation_type', 'response_stage.deviation_type_id', 'deviation_type.id' );
  }
}
