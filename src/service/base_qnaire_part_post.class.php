<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

abstract class base_qnaire_part_post extends \cenozo\service\post
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    $clone_id = $this->get_argument( 'clone', NULL );
    if( !is_null( $clone_id ) )
    {
      $record = $this->get_leaf_record();
      if( $record->get_qnaire()->readonly ) throw lib::create(
        'exception\notice',
        'The operation cannot be completed because the questionnaire is in read-only mode.',
        __METHOD__
      );
    }
  }

  /**
   * Extends parent method
   */
  protected function finish()
  {
    parent::finish();

    $clone_id = $this->get_argument( 'clone', NULL );
    if( !is_null( $clone_id ) )
    {
      $subject = $this->get_leaf_subject();
      $this->get_leaf_record()->clone_from( lib::create( sprintf( 'database\%s', $subject ), $clone_id ) );
    }
  }
}
