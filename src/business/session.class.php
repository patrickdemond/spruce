<?php
/**
 * session.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace pine\business;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Application extension to session class
 */
class session extends \cenozo\business\session
{
  /**
   * Extends the parent method
   */
  public function login( $username = NULL, $db_site = NULL, $db_role = NULL )
  {
    $setting_manager = lib::create( 'business\setting_manager' );
    $respondent_username = $setting_manager->get_setting( 'utility', 'respondent_username' );

    $is_respondent = false;
    if( !$this->is_shutdown() )
    {
      // If a JWT exists then we're already logged in.
      if( !is_null( $this->jwt ) )
      {
        $db_access = lib::create( 'database\access', $this->jwt->get_data( 'access_id' ) );

        if( $respondent_username == $db_access->get_user()->name )
        {
          // don't record activity from the qnaire user
          $this->no_activity = true;

          // we're logged in as the qnaire user, log out if we're loading anything other than a response
          if( !array_key_exists( 'REDIRECT_URL', $_SERVER ) ||
              ( 0 == preg_match( '#/api/#', $_SERVER['REDIRECT_URL'] ) && is_null( $this->get_response() ) ) )
          {
            $this->logout();
          }
        }
      }
      // If there is no JWT and we're viewing a response then automatically log in as the respondent user
      else if( is_null( $username ) &&
               is_null( $db_site ) &&
               is_null( $db_role ) &&
               !is_null( $this->get_response() ) )
      {
        $is_respondent = true;
      }
    }

    return $is_respondent ?
      parent::login( $respondent_username ) :
      parent::login( $username, $db_site, $db_role );
  }

  /**
   * Determines whether the active response is part of a qnaire that has stages
   * 
   * Note that this will work even if the the get_response() method returns a NULL response, so long as
   * we're pointing at the respondent/run URL
   */
  public function get_qnaire_has_stages()
  {
    if( is_null( $this->qnaire_has_stages ) ) $this->get_response();
    return $this->qnaire_has_stages;
  }

  /**
   * Determines whether a response is currently active
   */
  public function get_response()
  {
    if( false === $this->db_response )
    {
      $this->qnaire_has_stages = false;
      $this->db_response = NULL;

      // remove the front part of the url so we are left with the request only
      if( array_key_exists( 'REDIRECT_URL', $_SERVER ) )
      {
        $self_path = substr( $_SERVER['PHP_SELF'], 0, strrpos( $_SERVER['PHP_SELF'], '/' ) + 1 );
        $path = str_replace( $self_path, '', $_SERVER['REDIRECT_URL'] );
        if( preg_match( '#^respondent/run/([^/]+)$#', $path, $matches ) )
        {
          $respondent_class_name = lib::get_class_name( 'database\respondent' );
          $db_respondent = $respondent_class_name::get_unique_record( 'token', $matches[1] );
          if( !is_null( $db_respondent ) )
          {
            $this->qnaire_has_stages = $db_respondent->get_qnaire()->stages;

            // do not allow auto-login of qnaire with stages
            if( !$this->qnaire_has_stages )
              $this->db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response( true );
          }
        }
      }
    }

    return $this->db_response;
  }

  /**
   * Stores whether the current response's qnaire uses stages
   * @var boolean
   */
  private $qnaire_has_stages = NULL;

  /**
   * Stores the response associated with the current session (if there is one)
   * @var database\response
   */
  private $db_response = false;
}
