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
    // compile variables as if they were in a description (forced in case the question is on the same page)
    $value = $db_answer->get_response()->compile_description( $this->code, true );

    // convert string representation of boolean values to boolean values
    if( 'false' == $value ) $value = false;
    else if( 'true' == $value ) $value = true;

    return $value;
  }
}
