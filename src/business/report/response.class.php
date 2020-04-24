<?php
/**
 * response.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\report;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Contact report
 */
class response extends \cenozo\business\report\base_report
{
  /**
   * Build the report
   * @access protected
   */
  protected function build()
  {
    $response_class_name = lib::get_class_name( 'database\response' );

    $column_list = array();

    // parse the restriction details
    $db_qnaire = NULL;
    $submitted = NULL;
    foreach( $this->get_restriction_list( false ) as $restriction )
    {
      if( 'qnaire' == $restriction['name'] ) $db_qnaire = lib::create( 'database\qnaire', $restriction['value'] );
      else if( 'submitted' == $restriction['name'] ) $submitted = $restriction['value'];
    }

    $module_mod = lib::create( 'database\modifier' );
    $module_mod->order( 'module.rank' );
    foreach( $db_qnaire->get_module_object_list( $module_mod ) as $db_module )
    {
      $page_mod = lib::create( 'database\modifier' );
      $page_mod->order( 'page.rank' );
      foreach( $db_module->get_page_object_list( $page_mod ) as $db_page )
      {
        $question_mod = lib::create( 'database\modifier' );
        $question_mod->where( 'type', '!=', 'comment' );
        $question_mod->order( 'question.rank' );
        foreach( $db_page->get_question_object_list( $question_mod ) as $db_question )
        {
          // figure out the full variable name in pieces
          $variable_pieces = array( $db_question->name );
          if( !is_null( $db_qnaire->variable_suffix ) ) $variable_pieces[] = $db_qnaire->variable_suffix;

          $option_sel = lib::create( 'database\select' );
          $option_sel->add_column( 'id' );
          $option_sel->add_column( 'name' );
          $option_sel->add_column( 'exclusive' );
          $option_sel->add_column( 'extra' );
          $option_sel->add_column( 'multiple_answers' );

          $option_mod = lib::create( 'database\modifier' );
          $option_mod->order( 'question_option.rank' );
          $option_list = $db_question->get_question_option_list( $option_sel, $option_mod );

          // only create a variable for all options if at least one is not exclusive or has extra data
          $all_exclusive = true;
          $no_extra = true;
          if( 'list' == $db_question->type )
          {
            foreach( $option_list as $option )
            {
              if( !$option['exclusive'] ) $all_exclusive = false;
              if( !is_null( $option['extra'] ) ) $no_extra = false;
            }
          }

          if( !$all_exclusive || !$no_extra )
          {
            foreach( $option_list as $option )
            {
              // add the option name in the middle of the variable name pieces
              $pieces = $variable_pieces;
              array_splice( $pieces, 1, 0, $option['name'] );
              $column_name = implode( '_', $pieces );
              $column_list[$column_name] = array(
                'question_id' => $db_question->id,
                'option_id' => $option['id'],
                'extra' => $option['extra'],
                'multiple_answers' => $option['multiple_answers'],
                'all_exclusive' => $all_exclusive,
                'no_extra' => $no_extra
              );
            }
          }
          else
          {
            $column_name = implode( '_', $variable_pieces );
            $column_list[$column_name] = array(
              'question_id' => $db_question->id,
              'type' => $db_question->type
            );

            if( 0 < count( $option_list ) ) $column_list[$column_name]['option_list'] = $option_list;
          }
        }
      }
    }

    // now loop through all responses and fill in the data array
    $data = array();
    $response_mod = lib::create( 'database\modifier' );
    $response_mod->join( 'respondent', 'response.respondent_id', 'respondent.id' );
    $response_mod->where( 'respondent.qnaire_id', '=', $db_qnaire->id );
    if( !is_null( $submitted ) ) $response_mod->where( 'response.submitted', '=', $submitted );
    $response_mod->order( 'respondent.end_datetime' );
    foreach( $response_class_name::select_objects( $response_mod ) as $db_response )
    {
      $answer_list = array();
      $answer_sel = lib::create( 'database\select' );
      $answer_sel->add_column( 'question_id' );
      $answer_sel->add_column( 'value' );
      $answer_mod = lib::create( 'database\modifier' );
      $answer_mod->order( 'question_id' );
      foreach( $db_response->get_answer_list( $answer_sel, $answer_mod ) as $answer )
        $answer_list[$answer['question_id']] = $answer['value'];

      $data_row = array(
        $db_response->get_respondent()->get_participant()->uid,
        $db_response->rank,
        $db_response->submitted ? 1 : 0,
        is_null( $db_response->start_datetime ) ? NULL : $db_response->start_datetime->format( 'c' ),
        is_null( $db_response->last_datetime ) ? NULL : $db_response->last_datetime->format( 'c' )
      );
      foreach( $column_list as $column )
      {
        $row_value = NULL;

        if( array_key_exists( $column['question_id'], $answer_list ) )
        {
          $answer = util::json_decode( $answer_list[$column['question_id']] );
          if( is_object( $answer ) && property_exists( $answer, 'dkna' ) && $answer->dkna )
          {
            $row_value = 'DKNA';
          }
          else if( is_object( $answer ) && property_exists( $answer, 'refuse' ) && $answer->refuse )
          {
            $row_value = 'REFUSED';
          }
          else
          {
            if( array_key_exists( 'option_id', $column ) )
            { // this is a multiple-answer question, so every answer is its own variable
              $row_value = !$column['all_exclusive'] ? 'NO' : NULL;
              if( is_array( $answer ) ) foreach( $answer as $a )
              {
                if( ( is_object( $a ) && $column['option_id'] == $a->id ) || ( !is_object( $a ) && $column['option_id'] == $a ) )
                {
                  // use the value if the option asks for extra data
                  $row_value = is_null( $column['extra'] ) ? 'YES' : ( property_exists( $a, 'value' ) ? $a->value : NULL );
                  break;
                }
              }
            }
            else // the question can only have one answer
            {
              if( 'boolean' == $column['type'] )
              {
                $row_value = $answer ? 'YES' : 'NO';
              }
              else if( 'list' == $column['type'] )
              { // this is a "select one option" so set the answer to the option's name
                if( is_array( $answer ) )
                {
                  $option_id = current( $answer );
                  foreach( $column['option_list'] as $option )
                  {
                    if( $option_id == $option['id'] )
                    {
                      $row_value = $option['name'];
                      break;
                    }
                  }
                }
              }
              else // date, number, string and text are all just direct answers
              {
                $row_value = $answer;
              }
            }
          }
        }

        $data_row[] = $row_value;
      }

      $data[] = $data_row;
    }

    $header = array_keys( $column_list );
    array_unshift( $header, 'uid', 'rank', 'submitted', 'start_datetime', 'last_datetime' );
    $this->add_table( NULL, $header, $data );
  }
}
