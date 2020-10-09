<?php
/**
 * reminder_description.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * reminder_description: record
 */
class reminder_description extends base_description
{
  /**
   * Returns the compiled description based on the respondent and iteration
   * 
   * Descriptions can have $url$, $reminder.name$, $iteration$, or any of the $participant.*$ values provided
   * by the business\data_manager class.
   * @param database\respondent $db_respondent
   * @param integer $iteration
   * @return string
   */
  public function get_compiled_value( $db_respondent, $iteration )
  {
    $data_manager = lib::create( 'business\data_manager' );
    $db_participant = $db_respondent->get_participant();

    $text = $this->value;
    $matches = array();
    preg_match_all( '/\$[^$\s]+\$/', $text, $matches ); // get anything enclosed by $ with no whitespace
    foreach( $matches[0] as $match )
    {
      $value = substr( $match, 1, -1 );
      $replace = '';
      if( 'url' == $value )
      {
        $replace = $db_respondent->get_url();
      }
      else if( 'reminder.name' == $value )
      {
        $replace = $db_reminder->name;
      }
      else if( 'iteration' == $value )
      {
        $replace = $iteration;
      }
      else
      {
        $replace = 0 === strpos( $value, 'participant.' )
                 ? $data_manager->get_participant_value( $db_participant, $value )
                 : $data_manager->get_value( $value );
      }

      $text = str_replace( $match, $replace, $text );
    }

    return $text;
  }
}
