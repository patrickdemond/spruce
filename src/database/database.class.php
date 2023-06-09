<?php
/**
 * database.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class database extends \cenozo\database\database
{
  /**
   * Extend parent method
   */
  public function get_unique_keys( $table_name )
  {
    // add artificial unique keys
    $unique_key_list = parent::get_unique_keys( $table_name );
    if( 'response' == $table_name )
      $unique_key_list['uq_qnaire_id_participant_id'] = array( 'qnaire_id', 'participant_id' );
    else if( 'question' == $table_name )
      $unique_key_list['uq_qnaire_id_name'] = array( 'qnaire_id', 'name' );
    return $unique_key_list;
  }
}
