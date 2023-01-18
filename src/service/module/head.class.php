<?php
/**
 * head.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\module;
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

    $this->columns['stage_rank'] = array(
      'data_type' => 'int',
      'default' => NULL,
      'required' => '1'
    );
  }
}
