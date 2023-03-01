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
   * Returns the compiled description based on the respondent and iteration
   * 
   * Descriptions can have $url$, $qnaire.name$, $iteration$, or any of the $participant.*$ values provided
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

  /**
   * Creates a qnaire_description from an object
   * @param object $qnaire_description
   * @param database\qnaire $db_qnaire The qnaire to associate the qnaire_description to
   * @return database\qnaire_description
   * @static
   */
  public static function create_from_object( $qnaire_description, $db_qnaire )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $db_language = $language_class_name::get_unique_record( 'code', $qnaire_description->language );

    $db_qnaire_description = new static();
    $db_qnaire_description->qnaire_id = $db_qnaire->id;
    $db_qnaire_description->language_id = $db_language->id;
    $db_qnaire_description->type = $qnaire_description->type;
    $db_qnaire_description->value = $qnaire_description->value;
    $db_qnaire_description->save();

    return $db_qnaire_description;
  }
}
