<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\user;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\user\module
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    // skip the parent validate method since it will not allow 
    $site_restricted_module = lib::get_class_name( 'service\site_restricted_module' );
    $site_restricted_module::validate();
  }

  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // remove the restriction on special roles
    $modifier->remove_where( 'user_join_special_access.user_id' );
  }
}
