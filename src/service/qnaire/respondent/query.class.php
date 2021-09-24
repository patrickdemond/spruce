<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\respondent;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  protected function setup()
  {
    parent::setup();

    // remove purged respondents before proceeding
    $setting_manager = lib::create( 'business\setting_manager' );
    if( $setting_manager->get_setting( 'general', 'detached' ) )
      $this->get_parent_record()->delete_purged_respondents();
  }
}
