<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response\question;
use cenozo\lib, cenozo\log, pine\util;

/**
 * NOTE: This service is used by the response "display" feature where all of a response's questions are displayed in one page
 */
class query extends \cenozo\service\query
{
  /**
   * Replace parent method
   */
  protected function prepare()
  {
    // do not run the parent method
    $read_class_name = lib::get_class_name( 'service\read' );
    $read_class_name::prepare();
  }

  /**
   * Replace parent method
   */
  protected function setup()
  {
    $module_class_name = lib::get_class_name( 'database\module' );
    $page_class_name = lib::get_class_name( 'database\page' );
    $question_class_name = lib::get_class_name( 'database\question' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );

    parent::setup();

    $db_response = $this->get_parent_record();
    $db_language = $db_response->get_language();
    $db_qnaire = $db_response->get_respondent()->get_qnaire();
    $expression_manager = lib::create( 'business\expression_manager', $db_response );

    // generate a list of all visible questions and any answers
    $this->data_list = array();

    $module_sel = lib::create( 'database\select' );
    $module_sel->from( 'module' );
    $module_sel->add_column( 'id' );
    $module_sel->add_column( 'precondition' );
    $module_sel->add_table_column( 'module_description', 'value', 'description' );

    $module_mod = lib::create( 'database\modifier' );
    $module_mod->where( 'qnaire_id', '=', $db_qnaire->id );
    $module_mod->order( 'module.rank' );

    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'module.id', '=', 'module_description.module_id', false );
    $join_mod->where( 'module_description.language_id', '=', $db_language->id );
    $join_mod->where( 'module_description.type', '=', 'prompt' );
    $module_mod->join_modifier( 'module_description', $join_mod );

    foreach( $module_class_name::select( $module_sel, $module_mod ) as $module )
    {
      if( $expression_manager->evaluate( $module['precondition'] ) )
      {
        $module_data = array(
          'description' => $db_response->compile_description( $module['description'], true ),
          'page_list' => array()
        );

        $page_sel = lib::create( 'database\select' );
        $page_sel->from( 'page' );
        $page_sel->add_column( 'id' );
        $page_sel->add_column( 'precondition' );
        $page_sel->add_table_column( 'page_description', 'value', 'description' );

        $page_mod = lib::create( 'database\modifier' );
        $page_mod->where( 'module_id', '=', $module['id'] );
        $page_mod->order( 'page.rank' );

        $join_mod = lib::create( 'database\modifier' );
        $join_mod->where( 'page.id', '=', 'page_description.page_id', false );
        $join_mod->where( 'page_description.language_id', '=', $db_language->id );
        $join_mod->where( 'page_description.type', '=', 'prompt' );
        $page_mod->join_modifier( 'page_description', $join_mod );

        foreach( $page_class_name::select( $page_sel, $page_mod ) as $page )
        {
          if( $expression_manager->evaluate( $page['precondition'] ) )
          {
            $page_data = array(
              'description' => $db_response->compile_description( $page['description'], true ),
              'question_list' => array()
            );

            $question_sel = lib::create( 'database\select' );
            $question_sel->from( 'question' );
            $question_sel->add_column( 'id' );
            $question_sel->add_column( 'type' );
            $question_sel->add_column( 'precondition' );
            $question_sel->add_column( 'unit_list' );
            $question_sel->add_table_column( 'question_description', 'value', 'description' );
            $question_sel->add_table_column( 'answer', 'id', 'answer_id' );
            $question_sel->add_table_column( 'answer', 'value', 'answer' );
            $question_sel->add_column(
              sprintf( 'IFNULL( answer.language_id, %d )', $db_language->id ),
              'language_id',
              false
            );
            $question_sel->add_column(
              sprintf( 'IFNULL( language.code, %d )', $db_language->code ),
              'language_code',
              false
            );

            $question_mod = lib::create( 'database\modifier' );
            $question_mod->where( 'page_id', '=', $page['id'] );
            $question_mod->order( 'question.rank' );

            $join_mod = lib::create( 'database\modifier' );
            $join_mod->where( 'question.id', '=', 'answer.question_id', false );
            $join_mod->where( 'answer.response_id', '=', $db_response->id );
            $question_mod->join_modifier( 'answer', $join_mod, 'left' );

            $question_mod->left_join( 'language', 'answer.language_id', 'language.id' );

            $join_mod = lib::create( 'database\modifier' );
            $join_mod->where( 'question.id', '=', 'question_description.question_id', false );
            $join_mod->where(
              sprintf( 'IFNULL( answer.language_id, %d )', $db_language->id ),
              '=',
              'question_description.language_id',
              false
            );
            $join_mod->where( 'question_description.type', '=', 'prompt' );
            $question_mod->join_modifier( 'question_description', $join_mod );

            foreach( $question_class_name::select( $question_sel, $question_mod ) as $question )
            {
              if( $expression_manager->evaluate( $question['precondition'] ) )
              {
                // get the answer for this question, if one exists
                $decoded_value = is_null( $question['answer'] ) ? NULL : util::json_decode( $question['answer'] );
                $print_answer = NULL;
                if( !is_null( $question['answer'] ) )
                {
                  // Print in english if the base is french and the question language disagrees, or
                  // the base is english and the question language does not disagree (XOR)
                  $english = ( 'fr' == $db_language->code xor $question['language_id'] == $db_language->id );
                  if( is_object( $decoded_value ) )
                  {
                    if( property_exists( $decoded_value, 'dkna' ) && $decoded_value->dkna )
                    {
                      $print_answer = $english ? 'Don\'t Know / No Answer' : 'Ne sais pas / pas de réponse';
                    }
                    else if( property_exists( $decoded_value, 'refuse' ) && $decoded_value->refuse )
                    {
                      $print_answer = $english ? 'Refused' : 'Refus';
                    }
                    else if(
                      property_exists( $decoded_value, 'value' ) &&
                      property_exists( $decoded_value, 'unit' )
                    ) {
                      $unit_list_enum = $db_qnaire->get_unit_list_enum( $question['unit_list'] );
                      $lang = $question['language_code'];
                      $unit = $decoded_value->unit;
                      $print_answer = sprintf(
                        '%s %s',
                        $decoded_value->value,
                        !is_null( $unit_list_enum ) &&
                        array_key_exists( $lang, $unit_list_enum ) &&
                        array_key_exists( $unit, $unit_list_enum[$lang] ) ?
                          $unit_list_enum[$lang][$unit] : $unit
                      );
                    }
                  }
                  else if( 'boolean' == $question['type'] )
                  {
                    $print_answer = $decoded_value
                                  ? ( $english ? 'Yes' : 'Oui' )
                                  : ( $english ? 'No' : 'Non' );
                  }
                  else
                  {
                    $print_answer = $decoded_value;
                  }
                }

                // the answer's language takes precedence over the response's
                $question_data = array(
                  'type' => $question['type'],
                  'description' => $db_response->compile_description( $question['description'], true ),
                  'answer' => is_null( $print_answer ) ? NULL : $print_answer
                );

                if( 'audio' == $question['type'] )
                {
                  // audio files are stored on disk, not in the database
                  $db_answer = lib::create( 'database\answer', $question['answer_id'] );
                  $question_data['file'] = NULL;
                  $filename = sprintf( '%s/audio.wav', $db_answer->get_data_directory() );
                  if( file_exists( $filename ) )
                  {
                    $file = file_get_contents( sprintf( '%s/audio.wav', $db_answer->get_data_directory() ) );
                    if( false !== $file )
                    {
                      // send as a base64 encoded audio string for the <audio> tag's src attribute
                      $question_data['file'] = sprintf(
                        'data:audio/wav;base64,%s',
                        base64_encode( $file )
                      );
                    }
                  }
                }
                else if( 'list' == $question['type'] )
                {
                  $question_data['option_list'] = array();

                  $option_sel = lib::create( 'database\select' );
                  $option_sel->from( 'question_option' );
                  $option_sel->add_column( 'id' );
                  $option_sel->add_column( 'precondition' );
                  $option_sel->add_column( 'unit_list' );
                  $option_sel->add_table_column( 'question_option_description', 'value', 'description' );

                  $option_mod = lib::create( 'database\modifier' );
                  $option_mod->where( 'question_id', '=', $question['id'] );
                  $option_mod->order( 'question_option.rank' );

                  $join_mod = lib::create( 'database\modifier' );
                  $join_mod->where(
                    'question_option.id',
                    '=',
                    'question_option_description.question_option_id',
                    false
                  );
                  $join_mod->where( 'question_option_description.language_id', '=', $question['language_id'] );
                  $join_mod->where( 'question_option_description.type', '=', 'prompt' );
                  $option_mod->join_modifier( 'question_option_description', $join_mod );

                  foreach( $question_option_class_name::select( $option_sel, $option_mod ) as $option )
                  {
                    if( $expression_manager->evaluate( $option['precondition'] ) )
                    {
                      $option_data = array(
                        'description' => $db_response->compile_description( $option['description'], true )
                      );

                      if( is_array( $decoded_value ) )
                      {
                        // Create a list of selected options from the decoded value and see if the current option
                        // has been selected or not
                        $selected_option_id_list = [];
                        foreach( $decoded_value as $item )
                        {
                          $selected_option_id_list[] = is_object( $item ) ?
                            $selected_option_id_list[] = $item->id : $item;
                        }

                        $option_data['selected'] = false;
                        if( in_array( $option['id'], $selected_option_id_list ) )
                        {
                          $option_data['selected'] = true;

                          if( is_object( $item ) )
                          {
                            // add the option data's value
                            $option_data['value'] = $item->value;
                            if(
                              is_object( $item->value ) &&
                              property_exists( $item->value, 'value' ) &&
                              property_exists( $item->value, 'unit' )
                            ) {
                              $unit_list_enum = $db_qnaire->get_unit_list_enum( $option['unit_list'] );
                              $lang = $question['language_code'];
                              $unit = $item->value->unit;
                              $option_data['value'] = sprintf(
                                '%s %s',
                                $item->value->value,
                                !is_null( $unit_list_enum ) &&
                                array_key_exists( $lang, $unit_list_enum ) &&
                                array_key_exists( $unit, $unit_list_enum[$lang] ) ?
                                  $unit_list_enum[$lang][$unit] : $unit
                              );
                            }
                          }
                        }
                      }

                      $question_data['option_list'][] = $option_data;
                    }
                  }
                }

                $page_data['question_list'][] = $question_data;
              }
            }

            $module_data['page_list'][] = $page_data;
          }
        }

        $this->data_list[] = $module_data;
      }
    }
  }

  /**
   * Replace parent method
   */
  protected function get_record_count()
  {
    // return the number of questions in the data_list
    $count = 0;
    foreach( $this->data_list as $module )
      foreach( $module['page_list'] as $page )
        $count += count( $page['question_list'] );

    return $count;
  }

  /**
   * Replace parent method
   */
  protected function get_record_list()
  {
    return $this->data_list;
  }

  /**
   * @var nested associative array
   * @access private
   */
  private $data_list = array();
}
