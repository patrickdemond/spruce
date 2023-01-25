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

    // determine if we're generating an opal-style data dictionary
    $dictionary = false;
    foreach( $this->get_restriction_list( true ) as $restriction )
      if( 'dictionary' == $restriction['name'] ) $dictionary = $restriction['value'];

    $language_list = [];
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    $language_mod = lib::create( 'database\modifier' );
    $language_mod->order( 'code' );
    foreach( $db_qnaire->get_language_list( $language_sel, $language_mod ) as $language )
      $language_list[] = $language['code'];

    // the output is different depending on what type of annotation we're generating
    if( $dictionary )
    {
      $header = [
        'table', 'name', 'questionnaire', 'section', 'page', 'questionName', 'valueType', 'entityType',
        'repeatable', 'index', 'categoryName', 'condition', 'required', 'validation'
      ];

      $category_header = ['table', 'variable', 'name', 'missing'];

      if( $db_qnaire->stages ) $header[] = 'stage';

      foreach( $language_list as $language ) $header[] = sprintf( 'instructions:%s', $language );
      foreach( $language_list as $language )
      {
        $header[] = sprintf( 'label:%s', $language );
        $category_header[] = sprintf( 'label:%s', $language );
      }

      $meta_column_template = [
        'table' => sprintf( '%s Raw', $db_qnaire->name ),
        'name' => '',
        'questionnaire' => $db_qnaire->name,
        'section' => '',
        'page' => '',
        'questionName' => '',
        'valueType' => '',
        'entityType' => 'Participant',
        'repeatable' => 0,
        'index' => 0,
        'categoryName' => '',
        'condition' => '',
        'required' => 1,
        'validation' => ''
      ];

      $rank_column = $meta_column_template;
      $rank_column['name'] = 'rank';
      $rank_column['valueType'] = 'integer';
      $qnaire_version_column = $meta_column_template;
      $qnaire_version_column['name'] = 'qnaire_version';
      $qnaire_version_column['valueType'] = 'text';
      $submitted_column = $meta_column_template;
      $submitted_column['name'] = 'submitted';
      $submitted_column['valueType'] = 'boolean';
      $start_datetime_column = $meta_column_template;
      $start_datetime_column['name'] = 'start_datetime';
      $start_datetime_column['valueType'] = 'datetime';
      $last_datetime_column = $meta_column_template;
      $last_datetime_column['name'] = 'last_datetime';
      $last_datetime_column['valueType'] = 'datetime';

      // Get all questions from the qnaire (including descriptions, exported questions only) and build the
      // table data from there.  Start with the qnaire metadata.
      $body = [
        $rank_column,
        $qnaire_version_column,
        $submitted_column,
        $start_datetime_column,
        $last_datetime_column
      ];

      $category_body = [];
      foreach( $db_qnaire->get_all_questions( true, true ) as $variable_name => $question )
      {
        // the value must be determined
        $type = $question['type'];
        if( 'list' == $type )
        {
          $type = array_key_exists( 'extra', $question ) && !is_null( $question['extra'] )
                ? $question['extra']
                : 'boolean';
        }

        if( 'string' == $type ) $type = 'text';

        // if there is more than one precondition then append them using (a) && (b) format
        $precondition_list = [];
        if( !is_null( $question['module_precondition'] ) )
          $precondition_list[] = $question['module_precondition'];
        if( !is_null( $question['page_precondition'] ) )
          $precondition_list[] = $question['page_precondition'];
        if( !is_null( $question['question_precondition'] ) )
          $precondition_list[] = $question['question_precondition'];
        if( array_key_exists( 'question_option_precondition', $question ) &&
            !is_null( $question['question_option_precondition'] ) )
          $precondition_list[] = $question['question_option_precondition'];

        $precondition = implode( ' ) && ( ', $precondition_list );
        if( 1 < count( $precondition_list ) ) $precondition = sprintf( '( %s )', $precondition );

        $validation_list = [];
        if( !is_null( $question['minimum'] ) ) $validation_list[] = $question['minimum'];
        if( !is_null( $question['maximum'] ) ) $validation_list[] = $question['maximum'];
        $validation = 0 < count( $validation_list )
                    ? sprintf( '[%s]', implode( ', ', $validation_list ) )
                    : '';

        $row = [
          'table' => sprintf( '%s Raw', $db_qnaire->name ),
          'name' => $variable_name,
          'questionnaire' => $db_qnaire->name,
          'section' => $question['module_name'],
          'page' => $question['page_name'],
          'questionName' => $question['question_name'],
          'valueType' => $type,
          'entityType' => 'Participant',
          'repeatable' => 0,
          'index' => 0,
          'categoryName' => array_key_exists( 'question_option_name', $question ) ?
            $question['question_option_name'] : '',
          'condition' => $precondition,
          'required' => array_key_exists( 'option_list', $question ) ? 0 : 1,
          'validation' => $validation
        ];

        if( $db_qnaire->stages ) $row['stage'] = $question['stage_name'];

        $popup_column = sprintf(
          '%s_popup',
          array_key_exists( 'option_id', $question ) ? 'question_option' : 'question'
        );
        foreach( $question[$popup_column] as $language => $popup )
        {
          $row[sprintf( 'instructions:%s', $language )] = $popup;
        }

        $prompt_column = sprintf(
          '%s_prompt',
          array_key_exists( 'option_id', $question ) ? 'question_option' : 'question'
        );
        foreach( $question[$prompt_column] as $language => $prompt )
        {
          $row[sprintf( 'label:%s', $language )] = $prompt;
        }

        // convert all newlines to \n (as text)
        foreach( $row as $index => $value ) $row[$index] = str_replace( "\n", '\n', $value );

        $body[] = $row;

        // now build the category entry (multiple for list questions)
        if( array_key_exists( 'option_list', $question ) )
        {
          foreach( $question['option_list'] as $option )
          {
            $category_body[] = [
              'table' => $row['table'],
              'variable' => $row['name'],
              'name' => $option['name'],
              'missing' => 0
            ];
          }

          // add the dkna/refuse options
          if( $question['dkna_allowed'] )
          {
            $category_body[] = [
              'table' => $row['table'],
              'variable' => $row['name'],
              'name' => 'DK_NA',
              'missing' => 1
            ];
          }

          // add the dkna/refuse options
          if( $question['refuse_allowed'] )
          {
            $category_body[] = [
              'table' => $row['table'],
              'variable' => $row['name'],
              'name' => 'REFUSED',
              'missing' => 1
            ];
          }
        }
      }

      $this->add_table( 'Variables', $header, $body );
      $this->add_table( 'Categories', $category_header, $category_body );
    }
    else
    {
      $header = ['variable_name', 'type', 'minimum', 'maximum'];

      $header = array_merge( $header, ['module_name', 'module_precondition'] );
      foreach( $language_list as $language ) $header[] = sprintf( 'module_prompt_%s', $language );

      $header = array_merge( $header, ['page_name', 'page_precondition'] );
      foreach( $language_list as $language ) $header[] = sprintf( 'page_prompt_%s', $language );

      $header = array_merge( $header, ['question_name', 'question_precondition'] );
      foreach( $language_list as $language ) $header[] = sprintf( 'question_prompt_%s', $language );

      $header = array_merge( $header, ['question_option_name', 'question_option_precondition'] );
      foreach( $language_list as $language ) $header[] = sprintf( 'question_option_prompt_%s', $language );

      // get all questions from the qnaire (including descriptions) and build the table data from there
      $body = [];
      foreach( $db_qnaire->get_all_questions( true ) as $variable_name => $question )
      {
        $row = [
          'variable_name' => $variable_name,
          'type' => $question['type'],
          'minimum' => $question['minimum'],
          'maximum' => $question['maximum']
        ];

        $row = array_merge(
          $row,
          [
            'module_name' => $question['module_name'],
            'module_precondition' => $question['module_precondition']
          ]
        );
        foreach( $question['module_prompt'] as $language => $prompt )
          $row[ sprintf( 'module_prompt_%s', $language ) ] = $prompt;

        $row = array_merge(
          $row,
          [
            'page_name' => $question['page_name'],
            'page_precondition' => $question['page_precondition']
          ]
        );
        foreach( $question['page_prompt'] as $language => $prompt )
          $row[ sprintf( 'page_prompt_%s', $language ) ] = $prompt;

        $row = array_merge(
          $row,
          [
            'question_name' => $question['question_name'],
            'question_precondition' => $question['question_precondition']
          ]
        );
        foreach( $question['question_prompt'] as $language => $prompt )
          $row[ sprintf( 'question_prompt_%s', $language ) ] = $prompt;

        $row = array_merge(
          $row,
          [
            'question_option_name' => array_key_exists( 'question_option_name', $question ) ?
              $question['question_option_name'] : NULL,
            'question_option_precondition' => array_key_exists( 'question_option_precondition', $question ) ?
              $question['question_option_precondition'] : NULL
          ]
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
}
