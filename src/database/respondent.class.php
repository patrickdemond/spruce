<?php
/**
 * respondent.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * respondent: record
 */
class respondent extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // setup new respondents
    if( is_null( $this->id ) )
    {
      $db_participant = lib::create( 'database\participant', $this->participant_id );
      $this->token = static::generate_token();
    }

    parent::save();
  }

  /**
   * Returns this respondent's current response record
   * 
   * @return database\response
   * @access public
   */
  public function get_current_response()
  {
    // check the primary key value
    if( is_null( $this->id ) )
    {
      log::warning( 'Tried to query respondent with no primary key.' );
      return NULL;
    }

    $select = lib::create( 'database\select' );
    $select->from( 'respondent_current_response' );
    $select->add_column( 'response_id' );
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'respondent_id', '=', $this->id );

    $response_id = static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) );
    return $response_id ? lib::create( 'database\response', $response_id ) : NULL;
  }

  /**
   * Creates a unique token to be used for identifying a respondent
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
    if( !$created ) throw lib::create( 'exception\runtime', 'Unable to create unique respondent token.', __METHOD__ );
  }
}
