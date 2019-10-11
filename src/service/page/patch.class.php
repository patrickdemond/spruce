<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    $data = $this->get_file_as_array();
    if( array_key_exists( 'precondition', $data ) )
    {
      $db_qnaire = $this->get_leaf_record()->get_module()->get_qnaire();

      // validate the precondition
      $expression_manager = lib::create( 'business\expression_manager' );
      $error = $expression_manager->validate( $db_qnaire, $data['precondition'] );
      if( !is_null( $error ) )
      {
        $this->set_data( $error );
        $this->status->set_code( 306 );
      }
    }
  }
}
