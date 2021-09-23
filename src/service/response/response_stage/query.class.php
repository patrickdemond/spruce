<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response\response_stage;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Replace parent method
   */
  protected function prepare()
  {
    parent::prepare();

    // update the status of the response before we respond with a list
    $this->get_parent_record()->update_status();
  }
}
