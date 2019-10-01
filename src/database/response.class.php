<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace spruce\database;
use cenozo\lib, cenozo\log, spruce\util;

/**
 * response: record
 */
class response extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // setup new responses
    if( is_null( $this->id ) )
    {
      $db_qnaire = lib::create( 'database\qnaire', $this->qnaire_id );
      $this->page_id = $db_qnaire->get_first_module()->get_first_page()->id;
      $this->token = static::generate_token();
      $this->start_datetime = util::get_datetime_object();
    }

    parent::save();
  }

  /**
   * Moves the response to the next valid page
   * 
   * TODO: page restriction logic still needs to be applied here
   * @access public
   */
   public function move_to_next_page()
   {
     $db_next_page = $this->get_page()->get_next_page();
     $this->page_id = is_null( $db_next_page ) ? NULL : $db_next_page->id;
     $this->save();
   }

  /**
   * Creates a unique token to be used for identifying a response
   * 
   * @access private
   */
  private static function generate_token()
  {
    $created = false;
    $count = 0;
    while( 100 > $count++ )
    {
      $token = sprintf(
        '%s-%s-%s-%s',
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) ),
        bin2hex( openssl_random_pseudo_bytes( 2 ) )
      );

      // make sure it isn't already in use
      if( null == static::get_unique_record( 'token', $token ) ) return $token;
    }

    // if we get here then something is wrong
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique response token.', __METHOD__ );
  }
}
