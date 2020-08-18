<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page\question;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function setup()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    parent::setup();

    // if we got the question from a respondent then add the respondent's current response answers to the record
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();

      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'question.id', '=', 'answer.question_id', false );
      $join_mod->where( 'answer.response_id', '=', $db_response->id );
      $this->modifier->join_modifier( 'answer', $join_mod, 'left' );
      $this->modifier->left_join( 'language', 'answer.language_id', 'language.id' );

      $this->select->add_table_column( 'language', 'code', 'language' );
      $this->select->add_table_column( 'answer', 'id', 'answer_id' );
      $this->select->add_table_column( 'answer', 'value' );
    }
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    $list = parent::get_record_list();

    // if we got the question from a respondent then compile any attribute or response variables in the description
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
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
            lib::create( 'database\question', $record['id'] )
          );
        }

        if( array_key_exists( 'minimum', $record ) && 'date' != $record['type'] && !is_null( $record['minimum'] ) )
        {
          $list[$index]['minimum'] = $expression_manager->compile(
            $db_response,
            $record['minimum'],
            lib::create( 'database\question', $record['id'] )
          );
        }

        if( array_key_exists( 'maximum', $record ) && 'date' != $record['type'] && !is_null( $record['maximum'] ) )
        {
          $list[$index]['maximum'] = $expression_manager->compile(
            $db_response,
            $record['maximum'],
            lib::create( 'database\question', $record['id'] )
          );
        }

        if( array_key_exists( 'prompts', $record ) ) $list[$index]['prompts'] =
          $db_response->compile_description( $record['prompts'], $this->get_argument( 'show_hidden', false ) );
        if( array_key_exists( 'popups', $record ) ) $list[$index]['popups'] =
          $db_response->compile_description( $record['popups'], $this->get_argument( 'show_hidden', false ) );
      }
    }

    return $list;
  }
}
