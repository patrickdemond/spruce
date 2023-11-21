<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\user\access;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function prepare()
  {
    $role_class_name = lib::get_class_name( 'database\role' );

    parent::prepare();

    if( $this->get_argument( 'interviewing_instance', false ) )
    {
      // grant the user access to the machine role
      $db_access = $this->get_leaf_record();
      $db_access->role_id = $role_class_name::get_unique_record( 'name', 'machine' )->id;
      $db_access->site_id = lib::create( 'business\session' )->get_site()->id;
    }
  }
}
