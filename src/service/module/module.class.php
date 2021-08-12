<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\module;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \pine\service\base_qnaire_part_module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // add the total number of pages
    $this->add_count_column( 'page_count', 'page', $select, $modifier );

    // add the average time it takes to complete the module
    if( $select->has_column( 'average_time' ) )
    {
      $modifier->join( 'module_average_time', 'module.id', 'module_average_time.module_id' );
      $select->add_table_column( 'module_average_time', 'time', 'average_time' );
    }

    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    // joining to the stage is unusual since stages give a first/last module, not a list of all modules
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'module.qnaire_id', '=', 'stage.qnaire_id', false );
    $join_mod->where( 'module.rank', '>=', '( SELECT rank FROM module WHERE id = stage.first_module_id )', false );
    $join_mod->where( 'module.rank', '<=', '( SELECT rank FROM module WHERE id = stage.last_module_id )', false );
    $modifier->join_modifier( 'stage', $join_mod, 'left' );

    $db_module = $this->get_resource();
    if( !is_null( $db_module ) )
    {
      if( $select->has_column( 'first_page_id' ) )
      {
        $first_page_id = NULL;
        $db_first_page = $db_module->get_first_page();
        if( !is_null( $db_first_page) ) $first_page_id = $db_first_page->id;
        $select->add_constant( $first_page_id, 'first_page_id', 'integer' );
      }
    }

    if( $select->has_column( 'prompts' ) )
    {
      $modifier->where( 'IFNULL( prompt_description.type, "prompt" )', '=', 'prompt' );
      $modifier->left_join(
        'module_description',
        'module.id',
        'prompt_description.module_id',
        'prompt_description'
      );
      $modifier->left_join(
        'language',
        'prompt_description.language_id',
        'prompt_language.id',
        'prompt_language'
      );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( '.
          '"`", '.
          'prompt_language.code, '.
          'IFNULL( prompt_description.value, "" ) '.
        ') SEPARATOR "`" )',
        'prompts',
        false
      );
    }

    if( $select->has_column( 'popups' ) )
    {
      $modifier->where( 'IFNULL( popup_description.type, "popup" )', '=', 'popup' );
      $modifier->left_join(
        'module_description',
        'module.id',
        'popup_description.module_id',
        'popup_description'
      );
      $modifier->left_join(
        'language',
        'popup_description.language_id',
        'popup_language.id',
        'popup_language'
      );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( '.
          '"`", '.
          'popup_language.code, '.
          'IFNULL( popup_description.value, "" ) '.
        ') SEPARATOR "`" )',
        'popups',
        false
      );
    }
  }
}
