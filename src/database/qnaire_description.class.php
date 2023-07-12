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
    $embedded_file_class_name = lib::get_class_name( 'database\embedded_file' );
    $data_manager = lib::create( 'business\data_manager' );
    $db_participant = $db_respondent->get_participant();

    $text = $this->value;
    $matches = array();
    if( !is_null( $text ) )
    {
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
          if( 0 === strpos( $value, 'participant.' ) )
          {
            $replace = is_null( $db_participant ) ?
              NULL : $data_manager->get_participant_value( $db_participant, $value );
          }
          else
          {
            $replace = $data_manager->get_value( $value );
          }
        }

        $text = str_replace( $match, $replace, $text );
      }

      preg_match_all( '/@([A-Za-z0-9_]+)(\.width\( *([0-9]+%?) *\))?@/', $text, $matches );
      foreach( $matches[1] as $index => $match )
      {
        $name = $match;

        // images may have a width argument, for example: @name.width(123)@
        $width = array_key_exists( 3, $matches ) ? $matches[3][$index] : NULL;
        $db_embedded_file = $embedded_file_class_name::get_unique_record(
          array( 'qnaire_id', 'name' ),
          array( $this->qnaire_id, $name )
        );
        if( !is_null( $db_embedded_file ) )
        {
          $text = str_replace( $matches[0][$index], $db_embedded_file->get_tag( $width ), $text );
        }
      }
    }

    return $text;
  }
}
