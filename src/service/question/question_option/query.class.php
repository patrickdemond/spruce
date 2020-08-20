<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question\question_option;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    $list = parent::get_record_list();

    // if we got the question_option from a respondent then compile any attribute or response variables in the description
    $token = $this->get_argument( 'token', false );
    if( $token )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $token );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
      $expression_manager = lib::create( 'business\expression_manager' );

      foreach( $list as $index => $record )
      {
        // compile preconditions
        if( array_key_exists( 'precondition', $record ) )
        {
          $list[$index]['precondition'] = $expression_manager->compile(
            $db_response,
            $record['precondition'],
            lib::create( 'database\question_option', $record['id'] )
          );
        }

        // convert minimum answers that aren't in a date format
        if( array_key_exists( 'minimum', $record ) &&
            !is_null( $record['minimum'] ) &&
            !preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $record['minimum'] ) )
        {
          $list[$index]['minimum'] = $expression_manager->compile(
            $db_response,
            $record['minimum'],
            lib::create( 'database\question_option', $record['id'] )
          );
        }

        // convert maximum answers that aren't in a date format
        if( array_key_exists( 'maximum', $record ) &&
            !is_null( $record['maximum'] ) &&
            !preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $record['maximum'] ) )
        {
          $list[$index]['maximum'] = $expression_manager->compile(
            $db_response,
            $record['maximum'],
            lib::create( 'database\question_option', $record['id'] )
          );
        }

        if( array_key_exists( 'prompts', $record ) ) $list[$index]['prompts'] =
          $db_response->compile_description( $record['prompts'] );
        if( array_key_exists( 'popups', $record ) ) $list[$index]['popups'] =
          $db_response->compile_description( $record['popups'] );
      }
    }

    return $list;
  }
}
