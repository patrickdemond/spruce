<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\report;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Contact report
 */
class response extends \cenozo\business\report\base_report
{
  /**
   * Build the report
   * @access protected
   */
  protected function build()
  {
    $modifier = lib::create( 'database\modifier' );

    // parse the restriction details
    $db_qnaire = NULL;
    $submitted = NULL;
    foreach( $this->get_restriction_list( false ) as $restriction )
    {
      if( 'qnaire' == $restriction['name'] ) $db_qnaire = lib::create( 'database\qnaire', $restriction['value'] );
      else if( 'submitted' == $restriction['name'] ) $modifier->where( 'response.submitted', '=', $restriction['value'] );
    }

    // manually restrict to collections
    foreach( $this->get_restriction_list( true ) as $restriction )
    {
      if( 'collection' == $restriction['name'] )
      {
        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'respondent.participant_id', '=', 'collection_has_participant.participant_id', false );
        $join_mod->where( 'collection_has_participant.collection_id', '=', $restriction['value'] );
        $modifier->join_modifier( 'collection_has_participant', $join_mod );
      }
    }

    $response_data = $db_qnaire->get_response_data( $modifier );
    $this->add_table( NULL, $response_data['header'], $response_data['data'] );
  }
}
