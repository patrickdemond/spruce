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

    // update the status of all response stages before we respond with a list
    foreach( $this->get_parent_record()->get_response_stage_object_list() as $db_response_stage )
      $db_response_stage->update_status();
  }
}
