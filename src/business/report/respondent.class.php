<?php
/**
 * respondent.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\report;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Contact report
 */
class respondent extends \cenozo\business\report\base_report
{
  /**
   * Build the report
   * @access protected
   */
  protected function build()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    $modifier = lib::create( 'database\modifier' );
    $modifier->left_join( 'participant', 'respondent.participant_id', 'participant.id' );

    $select = lib::create( 'database\select' );
    $select->from( 'respondent' );
    $select->add_column( 'cohort.name', 'Cohort', false );
    $select->add_column( 'language.name', 'Language', false );
    $select->add_column( 'participant.uid', 'UID', false );
    $this->add_application_identifier_columns( $select, $modifier );
    $select->add_column( 'IF( response.submitted, "Yes", "No" )', 'Submitted', false );
    $select->add_column( $this->get_datetime_column( 'respondent.start_datetime' ), 'Start', false );
    $select->add_column( $this->get_datetime_column( 'response.last_datetime' ), 'Last', false );
    $select->add_column( $this->get_datetime_column( 'respondent.end_datetime' ), 'End', false );
    $select->add_column(
      sprintf(
        'CONCAT( "https://%s%s/respondent/run/", respondent.token )',
        $_SERVER['HTTP_HOST'],
        str_replace( '/api', '', ROOT_URL )
      ),
      'URL',
      false
    );

    $modifier->left_join( 'language', 'participant.language_id', 'language.id' );
    $modifier->left_join( 'cohort', 'participant.cohort_id', 'cohort.id' );
    $modifier->join( 'respondent_current_response', 'respondent.id', 'respondent_current_response.respondent_id' );
    $modifier->join( 'response', 'respondent_current_response.response_id', 'response.id' );

    // manually restrict to collections
    foreach( $this->get_restriction_list( true ) as $restriction )
    {
      if( 'collection' == $restriction['name'] )
      {
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'participant.id', '=', 'collection_has_participant.participant_id', false );
        $join_mod->where( 'collection_has_participant.collection_id', '=', $restriction['value'] );
        $modifier->join_modifier( 'collection_has_participant', $join_mod );
      }
    }

    // set up requirements
    $this->apply_restrictions( $modifier );

    $this->add_table_from_select( NULL, $respondent_class_name::select( $select, $modifier ) );
  }
}
