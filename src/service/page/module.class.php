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

      // We need to determine whether we should not restrict the next page by stage
      // This will depend on whether the qnaire is in respondent mode or not, which can be detected by checking
      // to see if the resource path has "token=" in it
      $ignore_stages = !preg_match( '/^token=([^;\/]+)/', $this->service->get_resource_value( 0 ) );

      // Only bother if the qnaire uses stages
      if( $ignore_stages && $db_page->get_qnaire()->stages )
      {
        // Only update the value if it's NULL (possibly because it's at the end of a stage but not the end of the qnaire)
        if( $select->has_alias( 'previous_id' ) && is_null( $select->get_alias_column( 'previous_id' ) ) )
        {
          $db_previous_record = $db_page->get_previous( true ); // get the previous page by ignoring stages
          $select->add_constant( is_null( $db_previous_record ) ? NULL : $db_previous_record->id, 'previous_id', 'integer' );
        }

        // Only update the value if it's NULL (possibly because it's at the end of a stage but not the end of the qnaire)
        if( $select->has_alias( 'next_id' ) && is_null( $select->get_alias_column( 'next_id' ) ) )
        {
          $db_next_record = $db_page->get_next( true ); // get the next page by ignoring stages
          $select->add_constant( is_null( $db_next_record ) ? NULL : $db_next_record->id, 'next_id', 'integer' );
        }
      }
    }
  }
}
