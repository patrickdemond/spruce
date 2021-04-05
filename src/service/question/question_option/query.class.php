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
      $expression_manager = lib::create( 'business\expression_manager', $db_response );

      foreach( $list as $index => $record )
      {
        $processing = '';

        try
        {
          // compile preconditions
          if( array_key_exists( 'precondition', $record ) )
          {
            $processing = 'precondition';
            $list[$index]['precondition'] = $expression_manager->compile(
              $record['precondition'],
              lib::create( 'database\question_option', $record['id'] )
            );
          }

          // convert minimum answers that aren't in a date format
          if( array_key_exists( 'minimum', $record ) &&
              !is_null( $record['minimum'] ) &&
              !preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $record['minimum'] ) )
          {
            $processing = 'minimum';
            $list[$index]['minimum'] = $expression_manager->compile(
              $record['minimum'],
              lib::create( 'database\question_option', $record['id'] )
            );
          }

          // convert maximum answers that aren't in a date format
          if( array_key_exists( 'maximum', $record ) &&
              !is_null( $record['maximum'] ) &&
              !preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $record['maximum'] ) )
          {
            $processing = 'maximum';
            $list[$index]['maximum'] = $expression_manager->compile(
              $record['maximum'],
              lib::create( 'database\question_option', $record['id'] )
            );
          }

          if( array_key_exists( 'prompts', $record ) )
          {
            $processing = 'prompts';
            $list[$index]['prompts'] = $db_response->compile_description( $record['prompts'] );
          }

          if( array_key_exists( 'popups', $record ) )
          {
            $processing = 'popups';
            $list[$index]['popups'] = $db_response->compile_description( $record['popups'] );
          }
        }
        catch( \cenozo\exception\runtime $e )
        {
          // when in debug mode display the compile error details
          if( $db_respondent->get_qnaire()->debug )
          {
            $db_question_option = lib::create( 'database\question_option', $record['id'] );
            $db_question = $db_question_option->get_question();
            $db_page = $db_question->get_page();
            $db_module = $db_page->get_module();

            $messages = array();
            do { $messages[] = $e->get_raw_message(); } while( $e = $e->get_previous() );
            $e = lib::create( 'exception\notice',
              sprintf(
                "Unable to compile %s for option \"%s\" in question \"%s\" on page \"%s\" in module \"%s\".\n\n%s",
                $processing,
                $db_question_option->name,
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
    }

    return $list;
  }
}
