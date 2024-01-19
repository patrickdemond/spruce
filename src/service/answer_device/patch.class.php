<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\answer_device;
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
      $data = $this->get_file_as_object();
      if( array_key_exists( 'status', $data ) )
      {
        $db_answer = $this->get_leaf_record()->get_answer();
        $out_of_sync = $db_answer->get_response()->get_out_of_sync(
          'cancelled' == $data['status'] ? 'abort device' : 'update device',
          $db_answer
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
