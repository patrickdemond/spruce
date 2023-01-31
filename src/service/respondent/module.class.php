<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    $detached = lib::create( 'business\setting_manager' )->get_setting( 'general', 'detached' );

    parent::prepare_read( $select, $modifier );

    $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
    $modifier->join( 'participant', 'respondent.participant_id', 'participant.id' );

    $modifier->join( 'respondent_current_response', 'respondent.id', 'respondent_current_response.respondent_id' );
    $modifier->left_join( 'response', 'respondent_current_response.response_id', 'response.id' );

    if( $select->has_column( 'status' ) )
    {
      $column_value = 
        'IF( respondent.end_datetime IS NOT NULL, "Completed", '.
        'IF( response.id IS NULL, "Not Started", '.
        'IF( response.page_id IS NOT NULL, "In Progress", '.
        'IF( NOT qnaire.stages, "Introduction", '.
        'IF( NOT response.checked_in, "Checking In", "Stage Selection" ) ) ) ) )';

      // add the export status if the application is detached
      if( $detached ) $column_value = sprintf( 'IF( respondent.export_datetime IS NOT NULL, "Exported", %s )', $column_value );

      $select->add_column( $column_value, 'status', false );
    }

    if( $select->has_column( 'page_progress' ) )
    {
      $select->add_column(
        'CONCAT( '.
          'IF( '.
            'response.submitted, '.
            'qnaire.total_pages, '.
            'IF( response.page_id IS NULL, 0, response.current_page_rank ) '.
          '), '.
          '" of ", qnaire.total_pages '.
        ')',
        'page_progress',
        false
      );
    }

    if( $select->has_table_columns( 'language' ) ||
        $select->has_table_columns( 'module' ) ||
        $select->has_table_columns( 'page' ) )
    {
      if( $select->has_table_columns( 'language' ) )
        $modifier->left_join( 'language', 'response.language_id', 'language.id' );
      if( $select->has_table_columns( 'page' ) || $select->has_table_columns( 'module' ) )
      {
        $modifier->left_join( 'page', 'response.page_id', 'page.id' );
        $modifier->left_join( 'module', 'page.module_id', 'module.id' );
      }
    }

    if( $select->has_table_columns( 'response_stage' ) )
    {
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'response.id', '=', 'response_stage.response_id', false );
      $join_mod->where( 'response_stage.status', '=', 'active' );
      $modifier->join_modifier( 'response_stage', $join_mod, 'left' );
    }

    $db_respondent = $this->get_resource();
    if( is_null( $db_respondent ) )
    {
      $this->add_count_column( 'response_count', 'response', $select, $modifier );
    }
    else
    {
      $db_qnaire = $db_respondent->get_qnaire();

      if( $select->has_column( 'has_devices' ) )
        $select->add_constant( 0 < $db_qnaire->get_device_count(), 'has_devices', 'boolean' );

      if( $select->has_column( 'sends_mail' ) )
        $select->add_constant( $db_qnaire->sends_mail(), 'sends_mail', 'boolean' );

      // include the participant first/last/uid as supplemental data
      $select->add_column(
        'CONCAT( participant.first_name, " ", participant.last_name, " (", participant.uid, ")" )',
        'formatted_participant_id',
        false
      );

      if( $select->has_column( 'completed' ) )
        $select->add_constant( $db_respondent->is_complete(), 'completed', 'boolean' );

      if( $select->has_column( 'introduction_list' ) )
      {
        // join to the introduction descriptions
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'qnaire.id', '=', 'introduction.qnaire_id', false );
        $join_mod->where( 'introduction.type', '=', 'introduction' );
        $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'introduction' );
        $modifier->join( 'language', 'introduction.language_id', 'introduction_language.id', '', 'introduction_language' );
        $select->add_column(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", introduction_language.code, IFNULL( introduction.value, "" ) ) SEPARATOR "`" )',
          'introduction_list',
          false
        );

        $modifier->group( 'qnaire.id' );
      }

      if( $select->has_column( 'conclusion_list' ) )
      {
        // join to the conclusion descriptions
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'qnaire.id', '=', 'conclusion.qnaire_id', false );
        $join_mod->where( 'conclusion.type', '=', 'conclusion' );
        $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'conclusion' );
        $modifier->join( 'language', 'conclusion.language_id', 'conclusion_language.id', '', 'conclusion_language' );
        $select->add_column(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", conclusion_language.code, IFNULL( conclusion.value, "" ) ) SEPARATOR "`" )',
          'conclusion_list',
          false
        );
      }

      if( $select->has_column( 'closed_list' ) )
      {
        // join to the close descriptions
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'qnaire.id', '=', 'closed.qnaire_id', false );
        $join_mod->where( 'closed.type', '=', 'closed' );
        $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'closed' );
        $modifier->join( 'language', 'closed.language_id', 'closed_language.id', '', 'closed_language' );
        $select->add_column(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", closed_language.code, IFNULL( closed.value, "" ) ) SEPARATOR "`" )',
          'closed_list',
          false
        );
      }
    }
  }
}
