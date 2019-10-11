<?php
/**
 * attribute.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * attribute: record
 */
class attribute extends \cenozo\database\record
{
  public function get_participant_value( $db_participant )
  {
    $data_manager = lib::create( 'business\data_manager' );
    return 0 === strpos( $this->code, 'participant.' ) ?
      $data_manager->get_participant_value( $db_participant, $this->code ) :
      $data_manager->get_value( $this->code );
  }
}
