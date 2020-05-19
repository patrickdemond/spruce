<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\self;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Special service for handling the get meta-resource
 */
class get extends \cenozo\service\self\get
{
  /**
   * Override parent method since self is a meta-resource
   */
  protected function create_resource( $index )
  {
    $setting_manager = lib::create( 'business\setting_manager' );

    $resource = parent::create_resource( $index );
    $resource['setting']['default_page_max_time'] = $setting_manager->get_setting( 'general', 'default_page_max_time' );

    return $resource;
  }
}
