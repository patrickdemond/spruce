<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

abstract class base_qnaire_part_patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    $db_qnaire = $this->get_leaf_record()->get_qnaire();

    if( $db_qnaire->readonly ) throw lib::create(
      'exception\notice',
      'The operation cannot be completed because the questionnaire is in read-only mode.',
      __METHOD__
    );

    $data = $this->get_file_as_array();
    if( array_key_exists( 'precondition', $data ) )
    {
      // validate the precondition
      $expression_manager = lib::create( 'business\expression_manager', $db_qnaire );
      $error = $expression_manager->validate( $data['precondition'] );
      if( !is_null( $error ) )
      {
        $this->set_data( $error );
        $this->status->set_code( 306 );
      }
    }
  }
}
