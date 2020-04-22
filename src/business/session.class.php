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
   * TODO: document
   */
  public function login( $username = NULL, $db_site = NULL, $db_role = NULL )
  {
    if( !$this->is_shutdown() )
    {
      $setting_manager = lib::create( 'business\setting_manager' );
      $qnaire_username = $setting_manager->get_setting( 'utility', 'qnaire_username' );

      // If an access.id exists in the session then we're already logged in.
      // Check if we're logged in as the qnaire user and if so then logout if loading anything other than the response run page
      if( array_key_exists( 'access.id', $_SESSION ) )
      {
        try
        {
          $db_access = lib::create( 'database\access', $_SESSION['access.id'] );
          if( $qnaire_username == $db_access->get_user()->name )
          {
            // we're logged in as the qnaire user, log out if we're loading anything other than a response
            if( !array_key_exists( 'REDIRECT_URL', $_SERVER ) ||
                ( 0 == preg_match( '#/api/#', $_SERVER['REDIRECT_URL'] ) && is_null( $this->get_response() ) ) )
            {
              unset( $_SESSION['access.id'] );
              $this->logout();
            }
          }
        }
        catch( \cenozo\exception\runtime $e ) {} // ignore invalid access ids
      }
      // If there is no access.id in the session then we're not logged in.
      // If we're looking at the response run page then automatically log in as the qnaire user
      else if( is_null( $username ) && !is_null( $this->get_response() ) )
      {
        // log in as the qnaire user
        $username = $qnaire_username;
      }
    }

    return parent::login( $username, $db_site, $db_role );
  }

  /**
   * TODO: document
   */
  public function get_response()
  {
    if( false === $this->db_response )
    {
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
          $this->db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response( true );
        }
      }
    }

    return $this->db_response;
  }

  /**
   * TODO: document
   */
  public function initialize( $no_activity = false )
  {
    // turn off activity when using the special access role
    $setting_manager = lib::create( 'business\setting_manager' );
    $respondent_access_id = $setting_manager->get_setting( 'general', 'respondent_access_id' );
    if( array_key_exists( 'access.id', $_SESSION ) &&
        !is_null( $respondent_access_id ) &&
        $_SESSION['access.id'] == $respondent_access_id ) $no_activity = true;
    parent::initialize( $no_activity );
  }

  /**
   * TODO: document
   */
  private $db_response = false;
}
