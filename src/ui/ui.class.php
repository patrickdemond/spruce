<?php
/**
 * ui.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace linden\ui;
use cenozo\lib, cenozo\log, linden\util;

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
      $module->add_child( 'attribute' );
      $module->add_child( 'module' );
    }

    $module = $this->get_module( 'module' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'page' );
      $module->add_child( 'requisite_group' );
    }

    $module = $this->get_module( 'page' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'question' );
      $module->add_child( 'requisite_group' );
      $module->add_action( 'render', '/{identifier}' );
    }

    $module = $this->get_module( 'question' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'question_answer' );
      $module->add_child( 'requisite_group' );
    }

    $module = $this->get_module( 'requisite_group' );
    if( !is_null( $module ) )
    {
      $module->add_child( 'requisite' );
      $module->add_child( 'requisite_group' );
    }
  }

  /**
   * Extends the sparent method
   */
  protected function build_listitem_list()
  {
    parent::build_listitem_list();

    $this->add_listitem( 'Questionnaires', 'qnaire' );
  }
}
