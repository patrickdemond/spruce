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
   * Extends the sparent method
   */
  protected function build_module_list()
  {
    parent::build_module_list();

    $module = $this->get_module( 'qnaire' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'response' );
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
      $module->add_action( 'run', '/{token}' );
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
