<?php
/**
 * head.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all head services
 */
class head extends \cenozo\service\head
{
  /**
   * Extends parent method
   */
  protected function setup()
  {
    parent::setup();

    $this->columns['checked_in'] = array(
      'data_type' => 'tinyint',
      'default' => '1',
      'required' => '1'
    );
  }
}
