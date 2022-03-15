<?php
/**
 * device_data.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * device_data: record
 */ 
class device_data extends \cenozo\database\record
{
  /**
   * Returns the compiled data value based on the answer provided
   * 
   * Descriptions can have any of the participant.* values provided by the business\data_manager class,
   * or the answer to a question in the qnaire as defined by the business\expression_manager class.
   * @param database\answer $db_answer
   * @return string
   */
  public function get_compiled_value( $db_answer )
  {
    $db_response = $db_answer->get_response();
    $db_respondent = $db_response->get_respondent();

    if( '$' == $this->code[0] )
    {
      // see if we can evaluate as an expression
      $expression_manager = lib::create( 'business\expression_manager', $db_response );
      return $expression_manager->evaluate( $this->code );
    }

    // if none of the above matches then assume we want data from the data manager
    $data_manager = lib::create( 'business\data_manager' );
    return 0 === strpos( $this->code, 'participant.' )
           ? $data_manager->get_participant_value( $db_respondent->get_participant(), $this->code )
           : $data_manager->get_value( $this->code );
  }
}
