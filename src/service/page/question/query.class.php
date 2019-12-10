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
    $response_class_name = lib::get_class_name( 'database\response' );

    parent::setup();

    // if we got the question from a response then add the response answers to the record
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );

      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'question.id', '=', 'answer.question_id', false );
      $join_mod->where( 'answer.response_id', '=', $db_response->id );
      $this->modifier->join_modifier( 'answer', $join_mod, 'left' );
      $this->modifier->join( 'language', 'answer.language_id', 'language.id' );

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
    $response_class_name = lib::get_class_name( 'database\response' );
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );
    $answer_class_name = lib::get_class_name( 'database\answer' );

    $list = parent::get_record_list();

    // if we got the question from a response then compile any attribute or response variables in the description
    if( 1 == preg_match( '/^token=([^;\/]+)/', $this->get_resource_value( 0 ), $parts ) )
    {
      $db_response = $response_class_name::get_unique_record( 'token', $parts[1] );
      $db_qnaire = $db_response->get_qnaire();
      $expression_manager = lib::create( 'business\expression_manager' );

      foreach( $list as $index => $record )
      {
        // convert attributes
        preg_match_all( '/@[A-Za-z0-9_]+@/', $record['descriptions'], $matches );
        foreach( $matches as $match )
        {
          $attribute_name = substr( $match, 1, -1 );
          $db_attribute = $attribute_class_name::get_unique_record(
            array( 'qnaire_id', 'name' ),
            array( $db_qnaire->id, $attribute_name )
          );

          if( is_null( $db_attribute ) )
          {
            if( !$db_qnaire->debug )
            {
              log::warning( sprintf(
                'Invalid attribute found in question description for id %d (%s)',
                $record['id'],
                $record['name']
              ) );
              $list[$index]['descriptions'] = str_replace( $match, '', $record['descriptions'] );
            }
          }
          else
          {
            $db_response_attribute = $response_attribute_class_name::get_unique_record(
              array( 'response_id', 'attribute_id' ),
              array( $db_response->id, $db_attribute->id )
            );
            $list[$index]['descriptions'] = str_replace( $match, $db_response_attribute->value, $record['descriptions'] );
          }
        }

        // convert questions
        preg_match_all( '/\$[A-Za-z0-9_]+\$/', $record['descriptions'], $matches );
        foreach( $matches as $match )
        {
          $question_name = substr( $match, 1, -1 );
          $db_question = $db_qnaire->get_question( $question_name );
          if( is_null( $db_question ) || 'comment' == $db_question->type || 'list' == $db_question->type )
          {
            if( !$db_qnaire->debug )
            {
              log::warning( sprintf(
                'Invalid question found in question description for id %d (%s)',
                $record['id'],
                $record['name']
              ) );
              $list[$index]['descriptions'] = str_replace( $match, '', $record['descriptions'] );
            }
          }
          else
          {
            $db_answer = $answer_class_name::get_unique_record(
              array( 'response_id', 'question_id' ),
              array( $db_response->id, $db_question->id )
            );
            $value = is_null( $db_answer ) ? NULL : util::json_decode( $db_answer->value );

            if( is_object( $value ) && property_exists( $value, 'dkna' ) && $value->dkna ) $compiled = '(no answer)';
            else if( is_object( $value ) && property_exists( $value, 'refuse' ) && $value->refuse ) $compiled = '(no answer)';
            else if( is_null( $value ) ) $compiled = '';
            else if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
            else $compiled = $value;

            $list[$index]['descriptions'] = str_replace( $match, $compiled, $record['descriptions'] );
          }
        }
      }
    }

    return $list;
  }
}
