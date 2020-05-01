<?php
/**
 * qnaire_description.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_description: record
 */
class qnaire_description extends base_description
{
  /**
   * TODO: document
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
      else if( 'qnaire.name' == $value )
      {
        $replace = $db_qnaire->name;
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
