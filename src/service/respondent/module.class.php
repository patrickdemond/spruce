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
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
    $modifier->join( 'participant', 'respondent.participant_id', 'participant.id' );
    if( $select->has_table_columns( 'response' ) ||
        $select->has_table_columns( 'language' ) ||
        $select->has_table_columns( 'module' ) ||
        $select->has_table_columns( 'page' ) )
    {
      $modifier->join( 'respondent_current_response', 'respondent.id', 'respondent_current_response.respondent_id' );
      $modifier->join( 'response', 'respondent_current_response.response_id', 'response.id' );

      if( $select->has_table_columns( 'language' ) )
        $modifier->join( 'language', 'response.language_id', 'language.id' );
      if( $select->has_table_columns( 'page' ) || $select->has_table_columns( 'module' ) )
      {
        $modifier->left_join( 'page', 'response.page_id', 'page.id' );
        $modifier->left_join( 'module', 'page.module_id', 'module.id' );
      }
    }

    if( is_null( $this->get_resource() ) )
    {
      // add the total time spent
      $this->add_count_column( 'response_count', 'response', $select, $modifier );
    }
    else
    {
      // include the participant first/last/uid as supplemental data
      $select->add_column(
        'CONCAT( participant.first_name, " ", participant.last_name, " (", participant.uid, ")" )',
        'formatted_participant_id',
        false
      );

      if( $select->has_column( 'introductions' ) )
      {
        // join to the introductions
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'qnaire.id', '=', 'introduction.qnaire_id', false );
        $join_mod->where( 'introduction.type', '=', 'introduction' );
        $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'introduction' );
        $modifier->join( 'language', 'introduction.language_id', 'introduction_language.id', '', 'introduction_language' );
        $select->add_column(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", introduction_language.code, IFNULL( introduction.value, "" ) ) SEPARATOR "`" )',
          'introductions',
          false
        );

        $modifier->group( 'qnaire.id' );
      }

      if( $select->has_column( 'conclusions' ) )
      {
        // join to the conclusions
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'qnaire.id', '=', 'conclusion.qnaire_id', false );
        $join_mod->where( 'conclusion.type', '=', 'conclusion' );
        $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'conclusion' );
        $modifier->join( 'language', 'conclusion.language_id', 'conclusion_language.id', '', 'conclusion_language' );
        $select->add_column(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", conclusion_language.code, IFNULL( conclusion.value, "" ) ) SEPARATOR "`" )',
          'conclusions',
          false
        );
      }
    }
  }
}
