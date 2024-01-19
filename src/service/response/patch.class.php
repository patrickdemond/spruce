<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      $db_response = $this->get_leaf_record();
      $data = $this->get_file_as_array();
      if( array_key_exists( 'checked_in', $data ) )
      {
        $out_of_sync = $db_response->get_out_of_sync(
          $data['checked_in'] ? 'check in response' : 'check out response'
        );
        if( !is_null( $out_of_sync ) )
        {
          $this->set_data( $out_of_sync );
          $this->get_status()->set_code( 409 );
        }
      }
    }
  }
}
