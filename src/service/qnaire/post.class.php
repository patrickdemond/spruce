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
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( 300 > $this->get_status()->get_code() )
    {
      $post_array = $this->get_file_as_array();

      // if the qnaire is repeated the offset must be >= 1
      if( array_key_exists( 'repeat_offset', $post_array ) )
      {
        $db_qnaire = $this->get_leaf_record();
        if( ( array_key_exists( 'repeated', $post_array ) && !is_null( $post_array['repeated'] ) ) ||
            !is_null( $db_qnaire->repeated ) )
        {
          if( 1 > $post_array['repeat_offset'] )
          {
            $this->status->set_code( 306 );
            $this->set_data( 'The repeat offset must be greater than or equal to 1.' );
          }
        }
      }

      // if the qnaire is repeated the offset must be >= 1
      if( array_key_exists( 'max_responses', $post_array ) )
      {
        if( 0 > $post_array['max_responses'] )
        {
          $this->status->set_code( 306 );
          $this->set_data( 'The maximum number of responses must be greater than or equal to 0.' );
        }
      }
    }
  }

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
  protected function execute()
  {
    if( $this->get_argument( 'import', false ) )
    {
      $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
      $this->set_data( $qnaire_class_name::import( util::json_decode( $this->get_file_as_raw() ) ) );
    }
    else
    {
      parent::execute();
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
