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
      $this->modifier->left_join( 'answer_device', 'answer.id', 'answer_device.answer_id' );

      $this->select->add_table_column( 'language', 'code', 'language' );
      $this->select->add_table_column( 'answer', 'id', 'answer_id' );
      $this->select->add_table_column( 'answer', 'value' );
      $this->select->add_table_column( 'answer_device', 'uuid', 'device_uuid' );
      $this->select->add_table_column( 'answer_device', 'status', 'device_status' );
    }
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    $list = parent::get_record_list();

    $db_qnaire = NULL;
    $db_response = NULL;
    $db_respondent = NULL;
    $expression_manager = NULL;

    $respondent = 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts );
    if( $respondent )
    {
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_qnaire = $db_respondent->get_qnaire();
      $db_response = $db_respondent->get_current_response();
      $expression_manager = lib::create( 'business\expression_manager', $db_response );
    }
    else
    {
      $db_qnaire = $this->get_parent_record()->get_qnaire();
      $expression_manager = lib::create( 'business\expression_manager', $db_qnaire );
    }

    foreach( $list as $index => $record )
    {
      $expression_manager->process_hidden_text( $record );

      // alter data if respondent data is included in the request
      if( $respondent )
      {
        // default answers enclosed in single or double quotes must be compiled as strings (descriptions)
        if( !is_null( $record['default_answer'] ) )
        {
          $record['default_answer'] = $db_response->compile_default_answer( $record['default_answer'] );
        }

        if( 'device' == $record['type'] && $record['device_id'] )
        {
          // count the number of files on disk for this record
          $record['files_received'] = 0;

          $db_answer = lib::create( 'database\answer', $record['answer_id'] );
          $record['files_received'] = count( $db_answer->get_data_files() );
        }
        else if( 'audio' == $record['type'] )
        {
          // audio files are stored on disk, not in the database
          $db_answer = lib::create( 'database\answer', $record['answer_id'] );
          $record['file'] = NULL;
          $filename = sprintf( '%s/audio.wav', $db_answer->get_data_directory() );
          if( file_exists( $filename ) )
          {
            $file = file_get_contents( sprintf( '%s/audio.wav', $db_answer->get_data_directory() ) );
            if( false !== $file ) $record['file'] = base64_encode( $file );
          }
        }

        $list[$index] = $record;
      }

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
