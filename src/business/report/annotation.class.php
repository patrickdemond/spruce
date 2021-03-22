<?php
/**
 * annotation.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\report;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Contact report
 */
class annotation extends \cenozo\business\report\base_report
{
  /**
   * Build the report
   * @access protected
   */
  protected function build()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    $select = lib::create( 'database\select' );
    $modifier = lib::create( 'database\modifier' );

    // parse the restriction details
    $db_qnaire = NULL;
    foreach( $this->get_restriction_list( false ) as $restriction )
      if( 'qnaire' == $restriction['name'] ) $db_qnaire = lib::create( 'database\qnaire', $restriction['value'] );

    $language_list = array();
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    $language_mod = lib::create( 'database\modifier' );
    $language_mod->order( 'code' );
    foreach( $db_qnaire->get_language_list( $language_sel, $language_mod ) as $language ) $language_list[] = $language['code'];

    $header = array( 'variable_name', 'type', 'minimum', 'maximum' );

    $header = array_merge( $header, array( 'module_name', 'module_precondition' ) );
    foreach( $language_list as $language ) $header[] = sprintf( 'module_prompt_%s', $language );

    $header = array_merge( $header, array( 'page_name', 'page_precondition' ) );
    foreach( $language_list as $language ) $header[] = sprintf( 'page_prompt_%s', $language );

    $header = array_merge( $header, array( 'question_name', 'question_precondition' ) );
    foreach( $language_list as $language ) $header[] = sprintf( 'question_prompt_%s', $language );

    $header = array_merge( $header, array( 'question_option_name', 'question_option_precondition' ) );
    foreach( $language_list as $language ) $header[] = sprintf( 'question_option_prompt_%s', $language );

    // get all questions from the qnaire (including descriptions) and build the table data from there
    $body = array();
    foreach( $db_qnaire->get_all_questions( true ) as $variable_name => $question )
    {
      $row = array(
        'variable_name' => $variable_name,
        'type' => $question['type'],
        'minimum' => array_key_exists( 'minimum', $question ) ? $question['minimum'] : NULL,
        'maximum' => array_key_exists( 'maximum', $question ) ? $question['maximum'] : NULL
      );

      $row = array_merge(
        $row,
        array( 'module_name' => $question['module_name'], 'module_precondition' => $question['module_precondition'] )
      );
      foreach( $question['module_prompt'] as $language => $prompt ) $row[ sprintf( 'module_prompt_%s', $language ) ] = $prompt;

      $row = array_merge(
        $row,
        array( 'page_name' => $question['page_name'], 'page_precondition' => $question['page_precondition'] )
      );
      foreach( $question['page_prompt'] as $language => $prompt ) $row[ sprintf( 'page_prompt_%s', $language ) ] = $prompt;

      $row = array_merge(
        $row,
        array( 'question_name' => $question['question_name'], 'question_precondition' => $question['question_precondition'] )
      );
      foreach( $question['question_prompt'] as $language => $prompt ) $row[ sprintf( 'question_prompt_%s', $language ) ] = $prompt;

      $row = array_merge(
        $row,
        array(
          'question_option_name' => array_key_exists( 'question_option_name', $question ) ?
            $question['question_option_name'] : NULL,
          'question_option_precondition' => array_key_exists( 'question_option_precondition', $question ) ?
            $question['question_option_precondition'] : NULL
        )
      );
      if( array_key_exists( 'question_option_prompt', $question ) )
        foreach( $question['question_option_prompt'] as $language => $prompt )
          $row[ sprintf( 'question_option_prompt_%s', $language ) ] = $prompt;

      // convert all newlines to \n (as text)
      foreach( $row as $index => $value ) $row[$index] = str_replace( "\n", '\n', $value );

      $body[] = $row;
    }

    $this->add_table( NULL, $header, $body );
  }
}
