<?php
/**
 * ui.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace pine\ui;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Application extension to ui class
 */
class ui extends \cenozo\ui\ui
{
  /**
   * TODO: document
   */
  public function get_interface( $maintenance = false, $error = NULL )
  {
    $session = lib::create( 'business\session' );

    // If we're loading the qnaire run then show a special interface if we're logged in as the qnaire user
    $db_response = $session->get_response();
    if( !is_null( $db_response ) )
    {
      $setting_manager = lib::create( 'business\setting_manager' );
      $qnaire_username = $setting_manager->get_setting( 'utility', 'qnaire_username' );
      $db_user = $session->get_user();

      if( !is_null( $db_user ) && $qnaire_username == $db_user->name )
      {
        // prepare the framework module list (used to identify which modules are provided by the framework)
        $framework_module_list = $this->get_framework_module_list();
        sort( $framework_module_list );

        // prepare the module list (used to create all necessary states needed by the active role)
        $this->build_module_list();
        ksort( $this->module_list );

        // create the json strings for the interface
        $module_array = array();
        foreach( $this->module_list as $module ) $module_array[$module->get_subject()] = $module->as_array();
        $framework_module_string = util::json_encode( $framework_module_list );
        $module_string = util::json_encode( $module_array );

        // build the interface
        ob_start();
        include( dirname( __FILE__ ).'/qnaire_interface.php' );
        return ob_get_clean();
      }
    }

    return parent::get_interface( $maintenance, $error );
  }

  /**
   * Extends the sparent method
   */
  protected function build_module_list()
  {
    parent::build_module_list();

    $module = $this->get_module( 'qnaire' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'response' );
      $module->add_child( 'qnaire_description' );
      $module->add_child( 'module' );
      $module->add_child( 'attribute' );
    }

    $module = $this->get_module( 'module' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'module_description' );
      $module->add_child( 'page' );
    }

    $module = $this->get_module( 'page' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'page_description' );
      $module->add_child( 'question' );
      $module->add_action( 'render', '/{identifier}' );
    }

    $module = $this->get_module( 'question' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'question_description' );
      $module->add_child( 'question_option' );
    }

    $module = $this->get_module( 'question_option' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'question_option_description' );
    }

    $module = $this->get_module( 'response' );
    if( !is_null( $module ) )
    {
      $module->add_action( 'run', '/{token}', true );
    }
  }

  /**
   * Extends the sparent method
   */
  protected function build_listitem_list()
  {
    parent::build_listitem_list();

    $this->add_listitem( 'Questionnaires', 'qnaire' );
    $this->remove_listitem( 'Participants' );
  }
}
