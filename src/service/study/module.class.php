<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\study;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\study\module
{
  /**
   * Extends the parent method
   */
  public function validate()
  {
    $util_class_name = lib::get_class_name( 'util' );

    parent::validate();

    if( !$this->service->may_continue() ) return;

    // if a version is provided then make sure they match
    $pine_version_header = $util_class_name::get_header( 'Pine-Version' );
    if( !is_null( $pine_version_header ) )
    {
      $setting_manager = lib::create( 'business\setting_manager' );
      $version = $setting_manager->get_setting( 'general', 'version' );
      $build = $setting_manager->get_setting( 'general', 'build' );

      $parts = explode( ' ', $pine_version_header );
      $remote_version = $parts[0];
      $remote_build = $parts[1];

      if( $remote_version != $version || $remote_build != $build )
      {
        $this->get_status()->set_code( 306 );
        $this->set_data( 'Your software is out of date.  Please update and try again.' );
      }
    }
  }

  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // stratum_data is used by child instances when synchronizing with parents
    if( $select->has_column( 'stratum_data' ) )
    {
      $this->add_list_column(
        'stratum_data',
        'stratum',
        'CONCAT_WS( "$$", stratum.name, IFNULL( stratum.description, "" ) )',
        $select,
        $modifier,
        NULL,
        NULL,
        NULL,
        '&&',
        false
      );
    }
  }
}
