<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\response;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function get_leaf_parent_relationship()
  {
    $relationship_class_name = lib::get_class_name( 'database\relationship' );
    return $relationship_class_name::MANY_TO_MANY;
  }

  /**
   * Extend parent method
   */
  protected function get_record_count()
  {
    // count all responses belonging to the parent qnaire
    $response_class_name = lib::get_class_name( 'database\response' );
    $modifier = clone $this->modifier;
    $modifier->where( 'respondent.qnaire_id', '=', $this->get_parent_record()->id );
    return $response_class_name::count( $modifier );
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    // if exporting data then we need the qnaire class to generate it for us
    if( $this->get_argument( 'export', false ) )
    {
      $modifier = lib::create( 'database\modifier');
      // make sure the response has at least one answer
      $modifier->join( 'respondent', 'response.respondent_id', 'respondent.id' );
      $modifier->join( 'participant', 'respondent.participant_id', 'participant.id' );
      $modifier->join( 'answer', 'response.id', 'answer.response_id' );
      $modifier->group( 'response.id' );
      $modifier->order( 'participant.uid' );
      $modifier->order( 'response.rank' );
      $modifier->limit( $this->modifier->get_limit() );
      $modifier->offset( $this->modifier->get_offset() );

      // get response data that is marked for export only
      $response_data = $this->get_parent_record()->get_response_data( $modifier, true );
      $data = $response_data['data'];
      foreach( $data as $index => $row ) $data[$index] = array_combine( $response_data['header'], $data[$index] );
      return $data;
    }
    else
    {
      // list all responses belonging to the parent qnaire
      $response_class_name = lib::get_class_name( 'database\response' );
      $modifier = clone $this->modifier;
      $modifier->where( 'respondent.qnaire_id', '=', $this->get_parent_record()->id );

      return $response_class_name::select( $this->select, $modifier );
    }
  }
}
