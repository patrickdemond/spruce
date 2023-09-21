<?php
/**
 * response_stage_pause.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * response_stage_pause: record
 */
class response_stage_pause extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // for new records, automatically set the start datetime to now if it isn't set
    if( is_null( $this->id ) && is_null( $this->start_datetime ) )
      $this->start_datetime = util::get_datetime_object();

    parent::save();
  }
}
