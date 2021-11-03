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

    $respondent = 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts );
    if( $respondent )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
    }
    $db_qnaire = $respondent ? $db_respondent->get_qnaire() : $this->get_parent_record()->get_qnaire();
    $expression_manager = lib::create( 'business\expression_manager', $respondent ? $db_response : $db_qnaire );

    foreach( $list as $index => $record )
    {
      $expression_manager->process_hidden_text( $record );

      $processing = '';
      try
      {
        if( $respondent )
        {
          // compile preconditions
          if( array_key_exists( 'precondition', $record ) )
          {
            $processing = 'precondition';
            $list[$index]['precondition'] = $expression_manager->compile(
              $record['precondition'],
              lib::create( 'database\question', $record['id'] )
            );
          }

          if( array_key_exists( 'minimum', $record ) && 'date' != $record['type'] && !is_null( $record['minimum'] ) )
          {
            $processing = 'minimum';
            $list[$index]['minimum'] = $expression_manager->compile(
              $record['minimum'],
              lib::create( 'database\question', $record['id'] )
            );
          }

          if( array_key_exists( 'maximum', $record ) && 'date' != $record['type'] && !is_null( $record['maximum'] ) )
          {
            $processing = 'maximum';
            $list[$index]['maximum'] = $expression_manager->compile(
              $record['maximum'],
              lib::create( 'database\question', $record['id'] )
            );
          }
        }

        if( array_key_exists( 'prompts', $record ) )
        {
          $processing = 'prompts';
          $list[$index]['prompts'] = $db_qnaire->compile_description( $record['prompts'] );
          if( $respondent ) $list[$index]['prompts'] = $db_response->compile_description( $list[$index]['prompts'] );
        }

        if( array_key_exists( 'popups', $record ) )
        {
          $processing = 'popups';
          if( $respondent ) $list[$index]['popups'] = $db_response->compile_description( $list[$index]['popups'] );
        }
      }
      catch( \cenozo\exception\runtime $e )
      {
        // when in debug mode display the compile error details
        if( $db_qnaire->debug )
        {
          $db_question = lib::create( 'database\question', $record['id'] );
          $db_page = $db_question->get_page();
          $db_module = $db_page->get_module();

          $messages = array();
          do { $messages[] = $e->get_raw_message(); } while( $e = $e->get_previous() );
          $e = lib::create( 'exception\notice',
            sprintf(
              "Unable to compile %s for question \"%s\" on page \"%s\" in module \"%s\".\n\n%s",
              $processing,
              $db_question->name,
              $db_page->name,
              $db_module->name,
              implode( "\n", $messages )
            ),
            __METHOD__,
            $e
          );
        }

        throw $e;
      }
    }

    return $list;
  }
}
