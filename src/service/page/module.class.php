<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page;
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

    // add the total number of questions
    $this->add_count_column( 'question_count', 'question', $select, $modifier );

    // add the average time it takes to complete the module
    if( $select->has_column( 'average_time' ) )
    {
      $modifier->join( 'page_average_time', 'page.id', 'page_average_time.page_id' );
      $select->add_table_column( 'page_average_time', 'time', 'average_time' );
    }

    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $modifier->join( 'language', 'qnaire.base_language_id', 'base_language.id', '', 'base_language' );

    if( $select->has_column( 'module_prompts' ) )
    {
      $modifier->where( 'IFNULL( module_prompt_description.type, "prompt" )', '=', 'prompt' );
      $modifier->left_join(
        'module_description',
        'module.id',
        'module_prompt_description.module_id',
        'module_prompt_description'
      );
      $modifier->left_join(
        'language',
        'module_prompt_description.language_id',
        'module_prompt_language.id',
        'module_prompt_language'
      );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( '.
          '"`", '.
          'module_prompt_language.code, '.
          'IFNULL( module_prompt_description.value, "" ) '.
        ') SEPARATOR "`" )',
        'module_prompts',
        false
      );
    }

    if( $select->has_column( 'module_popups' ) )
    {
      $modifier->where( 'IFNULL( module_popup_description.type, "popup" )', '=', 'popup' );
      $modifier->left_join(
        'module_description',
        'module.id',
        'module_popup_description.module_id',
        'module_popup_description'
      );
      $modifier->left_join(
        'language',
        'module_popup_description.language_id',
        'module_popup_language.id',
        'module_popup_language'
      );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( '.
          '"`", '.
          'module_popup_language.code, '.
          'IFNULL( module_popup_description.value, "" ) '.
        ') SEPARATOR "`" )',
        'module_popups',
        false
      );
    }

    $db_page = $this->get_resource();
    if( !is_null( $db_page ) )
    {
      $select->add_constant( $db_page->get_module()->get_qnaire()->get_number_of_pages(), 'qnaire_pages', 'integer' );
      $select->add_constant( $db_page->get_overall_rank(), 'qnaire_page', 'integer' );
    }
  }
}
