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
    $response_stage_class_name = lib::get_class_name( 'database\response_stage' );

    parent::prepare_read( $select, $modifier );

    $modifier->join( 'response', 'response_stage.response_id', 'response.id' );
    $modifier->join( 'stage', 'response_stage.stage_id', 'stage.id' );
    $modifier->left_join( 'page', 'response_stage.page_id', 'page.id' );
    $modifier->left_join( 'deviation_type', 'response_stage.deviation_type_id', 'deviation_type.id' );

    if( $select->has_column( 'elapsed' ) )
    {
      $modifier->left_join(
        'response_stage_pause',
        'response_stage.id',
        'response_stage_pause.response_stage_id'
      );
      $modifier->group( 'response_stage.id' );

      $select->add_column(
        $response_stage_class_name::get_elapsed_column(),
        'elapsed',
        false
      );
    }

    // If the token_check column is request then set it as false and leave it up to the
    // service\response\response_stage\query class to compute.  Note that this is not done in
    // service\response_stage\query so it would need to be added if required by that service.
    if( $select->has_column( 'token_check' ) )
    {
      // we'll need the precondition to evaluate
      $select->add_table_column( 'stage', 'token_check_precondition' );

      // and set the value to true by default
      $select->add_constant( true, 'token_check', 'boolean' );
    }
  }
}
