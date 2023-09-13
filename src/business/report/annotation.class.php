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

    $language_list = [];
    $language_sel = lib::create( 'database\select' );
    $language_sel->add_column( 'code' );
    $language_mod = lib::create( 'database\modifier' );
    $language_mod->order( 'code' );
    foreach( $db_qnaire->get_language_list( $language_sel, $language_mod ) as $language )
      $language_list[] = $language['code'];

    $variables_header = [
      'table', 'name', 'questionnaire', 'section', 'page', 'questionName', 'valueType', 'entityType',
      'repeatable', 'index', 'categoryName', 'condition', 'required', 'validation'
    ];

    $categories_header = ['table', 'variable', 'name', 'missing'];

    if( $db_qnaire->stages ) $variables_header[] = 'stage';

    foreach( $language_list as $language ) $variables_header[] = sprintf( 'instructions:%s', $language );
    foreach( $language_list as $language )
    {
      $variables_header[] = sprintf( 'label:%s', $language );
      $categories_header[] = sprintf( 'label:%s', $language );
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
    $qnaire_version_column['required'] = 0;
    $site_column = $meta_column_template;
    $site_column['name'] = 'site';
    $site_column['valueType'] = 'text';
    $site_column['required'] = 0;
    $language_column = $meta_column_template;
    $language_column['name'] = 'language';
    $language_column['valueType'] = 'text';
    $language_column['required'] = 1;
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
    $variables_data = [
      $rank_column,
      $qnaire_version_column,
      $submitted_column,
      $start_datetime_column,
      $last_datetime_column
    ];

    $categories_data = [];

    foreach( $db_qnaire->get_output_column_list( true, true ) as $variable_name => $question )
    {
      // Start by determining the validation (based on all preconditions)
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

      // now create the row that will be added for this question (the type is defined below)
      $row = [
        'table' => sprintf( '%s Raw', $db_qnaire->name ),
        'name' => $variable_name,
        'questionnaire' => $db_qnaire->name,
        'section' => $question['module_name'],
        'page' => $question['page_name'],
        'questionName' => $question['question_name'],
        'valueType' => 'text', // may be changed below
        'entityType' => 'Participant',
        'repeatable' => 0,
        'index' => 0,
        'categoryName' => '', // may be changed below
        'condition' => $precondition,
        'required' => 0,
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
      foreach( $row as $index => $value )
      {
        if( !is_null( $value ) )
        {
          $row[$index] = str_replace( "\n", '\n', $value );
        }
      }

      // if there is a unit list then add it to the category list
      // note that only questions or options with number-with-unit type/extra values will have a unit list
      if( array_key_exists( 'unit_list', $question ) )
      {
        // this is the unit question then add all possible units to the category body
        $first = true;
        $unit_category_list = [];
        foreach( $question['unit_list'] as $lang => $units )
        {
          foreach( $units as $unit_name => $unit_prompt )
          {
            if( $first )
            {
              // create the category body entry from the first language only
              $unit_category_list[$unit_name] = [
                'table' => $row['table'],
                'variable' => $row['name'],
                'name' => $unit_name,
                'missing' => 0
              ];
            }

            // now add the labels for this language
            $label = sprintf( 'label:%s', $lang );
            $unit_category_list[$unit_name][$label] = $unit_prompt;
          }
          $first = false;
        }

        if( 0 < count( $unit_category_list ) )
          $categories_data = array_merge( $categories_data, array_values( $unit_category_list ) );
      }

      // if there is a missing list then this is a column representing missing data
      if( array_key_exists( 'missing_list', $question ) )
      {
        // add all missing types to the category list
        foreach( $question['missing_list'] as $name => $prompt_list )
        {
          $category = [
            'table' => $row['table'],
            'variable' => $row['name'],
            'name' => $name,
            'missing' => 1
          ];
          foreach( $prompt_list as $lang => $prompt ) $category[sprintf( 'label:%s', $lang )] = $prompt;
          $categories_data[] = $category;
        }

        // and add the question row
        $variables_data[] = $row;
      }
      // boolean and list type questions are treated differently to other types
      else if( in_array( $question['type'], ['boolean', 'list'] ) )
      {
        // all list and boolean questions are represented by a single column
        if( 'list' == $question['type'] )
        {
          // single-selection will have an option-list, multi-selection will not
          if( array_key_exists( 'option_list', $question ) )
          {
            // single-selection list questions are represented by a single item
            foreach( $question['option_list'] as $option )
            {
              $category = [
                'table' => $row['table'],
                'variable' => $row['name'],
                'name' => $option['name'],
                'missing' => in_array( $option['name'], ['REFUSED','DK_NA'] ) ? 1 : 0
              ];
              foreach( $option['prompt'] as $lang => $prompt ) $category[sprintf('label:%s', $lang)] = $prompt;

              $categories_data[] = $category;
            }
          }
          else if( array_key_exists( 'extra', $question ) && !is_null( $question['extra'] ) )
          {
            // single-selection question options with an extra type are represented by their own column
            if( 'date' == $question['extra'] ) $row['valueType'] = 'date';
            else if( 'number' == $question['extra'] ) $row['valueType'] = 'decimal';
            else if( 'number with unit' == $question['extra'] )
            {
              // there will be two questions, one for the number and a second for the unit
              // the unit question will have a unit list, the number question will not
              if( !array_key_exists( 'unit_list', $question ) )
              {
                // this is the number question, so we just have to set the type
                $row['valueType'] = 'decimal';
              }
            }
          }
          else
          {
            // multi-selection questions are represented by one item for all possible options
            // make all types boolean (dkna and refuse options will be provided in the question list)
            $row['valueType'] = 'boolean';
          }
        }
        else if( 'boolean' == $question['type'] )
        {
          foreach( $question['boolean_list'] as $option )
          {
            $category = [
              'table' => $row['table'],
              'variable' => $row['name'],
              'name' => $option['name'],
              'missing' => in_array( $option['name'], ['REFUSED','DK_NA'] ) ? 1 : 0
            ];
            foreach( $option['prompt'] as $lang => $prompt ) $category[sprintf('label:%s', $lang)] = $prompt;

            $categories_data[] = $category;
          }
        }

        $variables_data[] = $row;
      }
      else // this is not a missing, boolean or list column
      {
        if( 'date' == $question['type'] )
        {
          $row['valueType'] = 'date';
        }
        else if( 'device' == $question['type'] )
        {
          // TODO: implement
        }
        else if( 'number' == $question['type'] )
        {
          $row['valueType'] = 'decimal';
        }
        else if( 'number with unit' == $question['type'] )
        {
          // there will be two questions, one for the number and a second for the unit
          // the unit question will have a unit list, the number question will not
          if( !array_key_exists( 'unit_list', $question ) )
          {
            // this is the number question, so we just have to set the type
            $row['valueType'] = 'decimal';
          }
        }
        // all remaining types (equipment, lookup, string, text) are treated as strings (nothing to change)

        // and add the question row
        $variables_data[] = $row;
      }
    }

    $this->add_table( 'Variables', $variables_header, $variables_data );
    $this->add_table( 'Categories', $categories_header, $categories_data );
  }
}
