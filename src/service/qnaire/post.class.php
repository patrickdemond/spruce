<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function prepare()
  {
    parent::prepare();

    $db_qnaire = $this->get_leaf_record();
    if( is_null( $db_qnaire->base_language_id ) )
    {
      $language_class_name = lib::get_class_name( 'database\language' );
      $db_default_language = $language_class_name::get_unique_record( 'code', 'en' );
      $db_qnaire->base_language_id = $db_default_language->id;
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
      $this->get_leaf_record()->clone_from( lib::create( 'database\qnaire', $clone_id ) );
    }
  }
}
