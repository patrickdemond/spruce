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
