<?php
/**
 * question.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * question: record
 */
class question extends base_qnaire_part
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'page';

  /**
   * Overview parent method
   */
  public function save()
  {
    $changing_name = !is_null( $this->id ) && $this->has_column_changed( 'name' );
    $old_name = $this->get_passive_column_value( 'name' );
    $old_data_directory = $this->get_old_data_directory();

    parent::save();

    // remove all question options if the question's type isn't list
    if( 'list' != $this->type && 0 < $this->get_question_option_count() ) $this->remove_question_option( NULL );

    if( $changing_name )
    {
      // update all preconditions if the question's name is changing
      $this->get_qnaire()->update_name_in_preconditions( $this, $old_name );

      // rename response data directories, if necessary
      if( file_exists( $old_data_directory ) ) rename( $old_data_directory, $this->get_data_directory() );
    }
  }

  /**
   * Overview parent method
   */
  public function get_qnaire()
  {
    return $this->get_page()->get_qnaire();
  }

  /**
   * Returns the previous question (even if it is on the previous page)
   * @return database\question
   */
  public function get_previous()
  {
    $db_previous_question = parent::get_previous();

    if( is_null( $db_previous_question ) )
    {
      $db_previous_page = $this->get_page()->get_previous();
      if( !is_null( $db_previous_page ) ) $db_previous_question = $db_previous_page->get_last_question();
    }

    return $db_previous_question;
  }

  /**
   * Returns the next question (event if it is on the next page)
   */
  public function get_next()
  {
    $db_next_question = parent::get_next();

    if( is_null( $db_next_question ) )
    {
      $db_next_page = $this->get_page()->get_next();
      if( !is_null( $db_next_page ) ) $db_next_question = $db_next_page->get_first_question();
    }

    return $db_next_question;
  }

  /**
   * Clones another question
   * @param database\question $db_source_question
   */
  public function clone_from( $db_source_question )
  {
    $device_class_name = lib::get_class_name( 'database\device' );

    parent::clone_from( $db_source_question );

    // If there is a device then find the equivalent from the parent qnaire
    // Note that the parent clone_from() method will copy the source question's device_id so if this question is part
    // of a different qnaire then we have to find the device by the same name belonging to this question's qnaire.
    $db_qnaire = $this->get_qnaire();
    if( !is_null( $this->device_id ) && $db_source_question->get_qnaire()->id != $db_qnaire->id )
    {
      $db_device = $device_class_name::get_unique_record(
        array( 'qnaire_id', 'name' ),
        array( $db_qnaire->id, $db_source_question->get_device()->name )
      );
      $this->device_id = $db_device->id;
      $this->save();
    }

    // replace all existing question options with those from the clone source
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'question_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM question_option %s', $modifier->get_sql() ) );

    foreach( $db_source_question->get_question_option_object_list() as $db_source_question_option )
    {
      $db_question_option = lib::create( 'database\question_option' );
      $db_question_option->question_id = $this->id;
      $db_question_option->rank = $db_source_question_option->rank;
      $db_question_option->name = $db_source_question_option->name;
      $db_question_option->clone_from( $db_source_question_option );
    }
  }

  /**
   * Returns an array of details about this question (used by qnaire::get_output_column_list())
   * 
   * @param boolean $descriptions If true then include module, page and question descriptions
   * @return array
   */
  public function get_output_column_list( $descriptions = false )
  {
    $variable_suffix = $this->get_qnaire()->variable_suffix;
    $db_page = $this->get_page();
    $db_module = $db_page->get_module();
    $db_qnaire = $db_module->get_qnaire();

    $column_list = [];

    // determine which languages the qnaire uses
    $language_list = array();
    if( $descriptions )
    {
      $language_sel = lib::create( 'database\select' );
      $language_sel->add_column( 'code' );
      foreach( $db_qnaire->get_language_list( $language_sel ) as $language ) $language_list[] = $language['code'];
    }

    $option_mod = lib::create( 'database\modifier' );
    $option_mod->order( 'question_option.rank' );
    $option_list = $this->get_question_option_object_list( $option_mod );

    // create the base column array to be used throughout
    $db_device = $this->get_device();
    $base_column = array(
      'module_name' => $db_module->name,
      'page_name' => $db_page->name,
      'question_name' => $this->name,
      'question_id' => $this->id,
      'type' => $this->type,
      'device' => is_null( $db_device ) ? NULL : $db_device->name,
      'minimum' => $this->minimum,
      'maximum' => $this->maximum,
      'dkna_allowed' => $this->dkna_allowed,
      'refuse_allowed' => $this->refuse_allowed,
      'module_precondition' => $db_module->precondition,
      'page_precondition' => $db_page->precondition,
      'question_precondition' => $this->precondition
    );
    if( $db_qnaire->stages ) $base_column['stage_name'] = $db_module->get_stage()->name;

    if( $descriptions )
    {
      $description_list = array(
        'module_prompt' => array(),
        'module_popup' => array(),
        'page_prompt' => array(),
        'page_popup' => array(),
        'question_prompt' => array(),
        'question_popup' => array()
      );

      // add the module's descriptions
      $desc_sel = lib::create( 'database\select' );
      $desc_sel->add_table_column( 'language', 'code', 'language' );
      $desc_sel->add_column( 'type' );
      $desc_sel->add_column( 'value' );
      $desc_mod = lib::create( 'database\modifier' );
      $desc_mod->join( 'language', 'module_description.language_id', 'language.id' );
      foreach( $db_module->get_module_description_list( $desc_sel, $desc_mod ) as $item )
        $description_list[sprintf( 'module_%s', $item['type'] )][$item['language']] = $item['value'];

      // add the page's descriptions
      $desc_sel = lib::create( 'database\select' );
      $desc_sel->add_table_column( 'language', 'code', 'language' );
      $desc_sel->add_column( 'type' );
      $desc_sel->add_column( 'value' );
      $desc_mod = lib::create( 'database\modifier' );
      $desc_mod->join( 'language', 'page_description.language_id', 'language.id' );
      foreach( $db_page->get_page_description_list( $desc_sel, $desc_mod ) as $item )
        $description_list[sprintf( 'page_%s', $item['type'] )][$item['language']] = $item['value'];

      // add the question's descriptions
      $desc_sel = lib::create( 'database\select' );
      $desc_sel->add_table_column( 'language', 'code', 'language' );
      $desc_sel->add_column( 'type' );
      $desc_sel->add_column( 'value' );
      $desc_mod = lib::create( 'database\modifier' );
      $desc_mod->join( 'language', 'question_description.language_id', 'language.id' );
      foreach( $this->get_question_description_list( $desc_sel, $desc_mod ) as $item )
        $description_list[sprintf( 'question_%s', $item['type'] )][$item['language']] = $item['value'];

      $base_column = array_merge( $base_column, $description_list );
    }

    // only create a variable for all options if at least one is not exclusive
    $all_exclusive = true;
    if( 'list' == $this->type )
    {
      foreach( $option_list as $db_option )
      {
        if( !$db_option->exclusive ) $all_exclusive = false;
        $base_column['all_exclusive'] = $all_exclusive;
      }
    }

    // only create a single column for this question if there are no options or they are all exclusive
    if( $all_exclusive )
    {
      // Get the base column name from the question's name
      // Note that the "number with unit" question type needs two columns, one for the number and
      // another for the unit.  We'll start by creating the number, and below the unit column.
      $column_name = sprintf(
        'number with unit' == $this->type ? '%s_NB' : '%s',
        $this->name
      );

      // if it exists then add the qnaire's variable suffix to the question name
      if( !is_null( $variable_suffix ) )
        $column_name = sprintf( '%s_%s', $column_name, $variable_suffix );

      $column_list[$column_name] = $base_column;

      // if there is a possibility for dkna or refused then add it as a "missing" column
      if( in_array( $this->type, ['boolean', 'list'] ) )
      {
        if( 'boolean' == $this->type )
        {
          $column_list[$column_name]['boolean_list'] = array();
          $option = [ 'id' => NULL, 'name' => 'YES' ];
          if( $descriptions )
          {
            $option['prompt'] = [];
            $option['popup'] = [];

            foreach( $language_list as $lang )
              $option['prompt'][$lang] = 'fr' == $lang ? 'Oui' : 'Yes';
          }
          $column_list[$column_name]['boolean_list'][] = $option;

          $option = [ 'id' => NULL, 'name' => 'NO' ];
          if( $descriptions )
          {
            $option['prompt'] = [];
            $option['popup'] = [];

            foreach( $language_list as $lang )
              $option['prompt'][$lang] = 'fr' == $lang ? 'Non' : 'No';
          }
          $column_list[$column_name]['boolean_list'][] = $option;
        }
        else if( 0 < count( $option_list ) )
        {
          $column_list[$column_name]['option_list'] = array();
          foreach( $option_list as $db_option )
          {
            $option = array( 'id' => $db_option->id, 'name' => $db_option->name );

            if( $descriptions )
            {
              $option['prompt'] = [];
              $option['popup'] = [];

              $desc_sel = lib::create( 'database\select' );
              $desc_sel->add_table_column( 'language', 'code', 'language' );
              $desc_sel->add_column( 'type' );
              $desc_sel->add_column( 'value' );
              $desc_mod = lib::create( 'database\modifier' );
              $desc_mod->join( 'language', 'question_option_description.language_id', 'language.id' );
              foreach( $db_option->get_question_option_description_list( $desc_sel, $desc_mod ) as $item )
                $option[$item['type']][$item['language']] = $item['value'];
            }

            $column_list[$column_name]['option_list'][] = $option;
          }
        }

        // add missing-answer options
        if( $this->dkna_allowed )
        {
          $option = [ 'id' => NULL, 'name' => 'DK_NA' ];
          if( $descriptions )
          {
            $option['prompt'] = [];
            $option['popup'] = [];

            foreach( $language_list as $lang )
            {
              $option['prompt'][$lang] =
                'fr' == $lang ? 'Ne sais pas / pas de réponse' : 'Don\'t Know / No Answer';
            }
          }

          $list_name = 'boolean' == $this->type ? 'boolean_list' : 'option_list';
          $column_list[$column_name][$list_name][] = $option;
        }

        if( $this->refuse_allowed )
        {
          $option = [ 'id' => NULL, 'name' => 'REFUSED' ];
          if( $descriptions )
          {
            $option['prompt'] = [];
            $option['popup'] = [];

            foreach( $language_list as $lang )
            {
              $option['prompt'][$lang] =
                'fr' == $lang ? 'Préfère ne pas répondre' : 'Prefer not to answer';
            }
          }

          $list_name = 'boolean' == $this->type ? 'boolean_list' : 'option_list';
          $column_list[$column_name][$list_name][] = $option;
        }
      }
      else if( $this->dkna_allowed || $this->refuse_allowed )
      {
        $missing_column_name = sprintf( '%s_MISSING', $this->name );

        // if it exists then add the qnaire's variable suffix to the question name
        if( !is_null( $variable_suffix ) )
          $missing_column_name = sprintf( '%s_%s', $missing_column_name, $variable_suffix );

        $column_list[$missing_column_name] = $base_column;

        // set the type to string since values will be DK_NA or MISSING
        $column_list[$missing_column_name]['type'] = 'string';

        // add the possible values in a missing_list
        $column_list[$missing_column_name]['missing_list'] = [];

        if( $this->dkna_allowed )
        {
          $column_list[$missing_column_name]['missing_list']['DK_NA'] = [];
          foreach( $language_list as $lang )
          {
            $column_list[$missing_column_name]['missing_list']['DK_NA'][$lang] =
              'fr' == $lang ? 'Ne sais pas / pas de réponse' : 'Don\'t Know / No Answer';
          }
        }

        if( $this->refuse_allowed )
        {
          $column_list[$missing_column_name]['missing_list']['REFUSED'] = [];
          foreach( $language_list as $lang )
          {
            $column_list[$missing_column_name]['missing_list']['REFUSED'][$lang] =
              'fr' == $lang ? 'Préfère ne pas répondre' : 'Prefer not to answer';
          }
        }
      }

      // now create the unit column if this is a "number with unit" question
      if( 'number with unit' == $this->type )
      {
        $unit_column_name = sprintf( '%s_UNIT', $this->name );

        // if it exists then add the qnaire's variable suffix to the question name
        if( !is_null( $variable_suffix ) )
          $unit_column_name = sprintf( '%s_%s', $unit_column_name, $variable_suffix );

        $column_list[$unit_column_name] = $base_column;
        $column_list[$unit_column_name]['minimum'] = NULL;
        $column_list[$unit_column_name]['maximum'] = NULL;
        $column_list[$unit_column_name]['unit_list'] = $descriptions
                                                     ? $db_qnaire->get_unit_list_enum( $this->unit_list )
                                                     : $this->unit_list;
      }
    }

    foreach( $option_list as $db_option )
    {
      // add an additional column for all options if any are not exclusive, or for all which have extra data
      if( !$all_exclusive || $db_option->extra )
      {
        // get the base column name from the question's name and add the option's name as a suffix
        $column_name = sprintf( '%s_%s', $this->name, $db_option->name );
        // Get the base column name from the question's name and add the option's name as a suffix
        // Note that the "number with unit" extra type needs two columns, one for the number and
        // another for the unit.  We'll start by creating the number, and below the unit column.
        $column_name = sprintf(
          'number with unit' == $db_option->extra ? '%s_%s_NB' : '%s_%s',
          $this->name,
          $db_option->name
        );

        // if it exists then add the qnaire's variable suffix to the question name
        if( !is_null( $variable_suffix ) )
          $column_name = sprintf( '%s_%s', $column_name, $variable_suffix );

        $precondition = NULL;
        $precondition = $this->precondition;
        if( !is_null( $db_option->precondition ) )
        {
          if( is_null( $precondition ) ) $precondition = $db_option->precondition;
          else $precondition = sprintf( '(%s) && (%s)', $precondition, $db_option->precondition );
        }

        $column_list[$column_name] = $base_column;
        $column_list[$column_name]['question_option_name'] = $db_option->name;
        $column_list[$column_name]['option_id'] = $db_option->id;
        $column_list[$column_name]['extra'] = $db_option->extra;
        $column_list[$column_name]['question_option_precondition'] = $db_option->precondition;

        if( $descriptions )
        {
          $column_list[$column_name]['question_option_prompt'] = array();
          $column_list[$column_name]['question_option_popup'] = array();

          // add the question option's descriptions
          $desc_sel = lib::create( 'database\select' );
          $desc_sel->add_table_column( 'language', 'code', 'language' );
          $desc_sel->add_column( 'type' );
          $desc_sel->add_column( 'value' );
          $desc_mod = lib::create( 'database\modifier' );
          $desc_mod->join( 'language', 'question_option_description.language_id', 'language.id' );
          foreach( $db_option->get_question_option_description_list( $desc_sel, $desc_mod ) as $item )
          {
            $column_list[$column_name][sprintf( 'question_option_%s', $item['type'] )][$item['language']] =
              $item['value'];
          }
        }

        // now create the unit column if this is a "number with unit" extra option
        if( 'number with unit' == $db_option->extra )
        {
          $unit_column_name = sprintf( '%s_%s_UNIT', $this->name, $db_option->name );

          // if it exists then add the qnaire's variable suffix to the question name
          if( !is_null( $variable_suffix ) )
            $unit_column_name = sprintf( '%s_%s', $unit_column_name, $variable_suffix );

          $column_list[$unit_column_name] = $column_list[$column_name];
          $column_list[$unit_column_name]['minimum'] = NULL;
          $column_list[$unit_column_name]['maximum'] = NULL;
          $column_list[$unit_column_name]['unit_list'] = $descriptions
                                                       ? $db_qnaire->get_unit_list_enum( $db_option->unit_list )
                                                       : $db_option->unit_list;
        }
      }
    }

    // finally, if not all exclusive then create these options as columns as well
    if( !$all_exclusive )
    {
      // get the base column name from the question's name and add DK_NA as a suffix
      $dkna_column_name = sprintf( '%s_DK_NA', $this->name );

      // if it exists then add the qnaire's variable suffix to the question name
      if( !is_null( $variable_suffix ) )
        $dkna_column_name = sprintf( '%s_%s', $dkna_column_name, $variable_suffix );

      $column_list[$dkna_column_name] = $base_column;
      $column_list[$dkna_column_name]['question_option_name'] = 'DK_NA';
      $column_list[$dkna_column_name]['option_id'] = 'dkna';
      $column_list[$dkna_column_name]['all_exclusive'] = $all_exclusive;

      if( $descriptions )
      {
        $popup = array();
        if( in_array( 'en', $language_list ) ) $popup['en'] = NULL;
        if( in_array( 'fr', $language_list ) ) $popup['fr'] = NULL;
        $column_list[$dkna_column_name]['question_option_popup'] = $popup;

        $prompt = array();
        if( in_array( 'en', $language_list ) ) $prompt['en'] = 'Don\'t Know / No Answer';
        if( in_array( 'fr', $language_list ) ) $prompt['fr'] = 'Ne sais pas / pas de réponse';
        $column_list[$dkna_column_name]['question_option_prompt'] = $prompt;
      }

      // get the base column name from the question's name and add REFUSED as a suffix
      $refused_column_name = sprintf( '%s_REFUSED', $this->name );

      // if it exists then add the qnaire's variable suffix to the question name
      if( !is_null( $variable_suffix ) )
        $refused_column_name = sprintf( '%s_%s', $refused_column_name, $variable_suffix );

      $column_list[$refused_column_name] = $base_column;
      $column_list[$refused_column_name]['question_option_name'] = 'REFUSED';
      $column_list[$refused_column_name]['option_id'] = 'refuse';
      $column_list[$refused_column_name]['all_exclusive'] = $all_exclusive;

      if( $descriptions )
      {
        $popup = array();
        if( in_array( 'en', $language_list ) ) $popup['en'] = NULL;
        if( in_array( 'fr', $language_list ) ) $popup['fr'] = NULL;
        $column_list[$refused_column_name]['question_option_popup'] = $popup;

        $prompt = array();
        if( in_array( 'en', $language_list ) ) $prompt['en'] = 'Prefer not to answer';
        if( in_array( 'fr', $language_list ) ) $prompt['fr'] = 'Préfère ne pas répondre';
        $column_list[$refused_column_name]['question_option_prompt'] = $prompt;
      }
    }

    return $column_list;
  }

  /**
   * Returns the directory that uploaded response data to this question is written to
   */
  public function get_data_directory()
  {
    $db_qnaire = $this->get_qnaire();
    return sprintf( '%s/%s', $db_qnaire->get_data_directory(), $this->name );
  }

  /**
   * Returns the old data directory (used by save() before updating the record)
   */
  protected function get_old_data_directory()
  {
    $db_qnaire = $this->get_qnaire();
    return sprintf(
      '%s/%s',
      $this->get_qnaire()->get_data_directory(),
      $this->get_passive_column_value( 'name' )
    );
  }
}
