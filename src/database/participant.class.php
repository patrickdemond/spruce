<?php
/**
 * participant.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

class participant extends \cenozo\database\participant
{
  /**
   * Override parent method if identifier uses a respondent's token
   */
  public static function get_record_from_identifier( $identifier )
  {
    $util_class_name = lib::get_class_name( 'util' );
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    // convert respondent token to participant id
    if( !$util_class_name::string_matches_int( $identifier ) &&
        false === strpos( 'token=', $identifier ) )
    {
      // convert respondent_token to respondent_id
      $regex = '/token=([^;]+)/';
      $matches = array();
      if( preg_match( $regex, $identifier, $matches ) )
      {
        $db_respondent = $respondent_class_name::get_unique_record( 'token', $matches[1] );
        if( !is_null( $db_respondent ) ) $identifier = $db_respondent->participant_id;
      }
    }

    return parent::get_record_from_identifier( $identifier );
  }
}
