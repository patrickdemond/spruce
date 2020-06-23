<?php
/**
 * page.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * page: record
 */
class page extends base_qnaire_part
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'module';

  /**
   * Override the parent method
   */
  public function save()
  {
    if( is_null( $this->max_time ) )
    {
      $setting_manager = lib::create( 'business\setting_manager' );
      $this->max_time = $setting_manager->get_setting( 'general', 'default_page_max_time' );
    }

    parent::save();
  }

  /**
   * Overview parent method
   */
  public function get_qnaire()
  {
    return $this->get_module()->get_qnaire();
  }

  /**
   * Override parent method
   */
  public static function get_record_from_identifier( $identifier )
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );

    if( 1 == preg_match( '/^token=([^;\/]+)/', $identifier, $parts ) )
    {
      // return the current page for the provided respondent's current response
      $db_respondent = $respondent_class_name::get_unique_record( 'token', $parts[1] );
      $db_response = is_null( $db_respondent ) ? NULL : $db_respondent->get_current_response();
      return is_null( $db_response ) || is_null( $db_response->page_id ) ? NULL : lib::create( 'database\page', $db_response->page_id );
    }
    else return parent::get_record_from_identifier( $identifier );
  }

  /**
   * TODO: document
   */
  public function get_previous()
  {
    $db_previous_page = parent::get_previous();

    if( is_null( $db_previous_page ) )
    {
      $db_previous_module = $this->get_module()->get_previous();
      if( !is_null( $db_previous_module ) ) $db_previous_page = $db_previous_module->get_last_page();
    }

    return $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next()
  {
    $db_next_page = parent::get_next();

    if( is_null( $db_next_page ) )
    {
      $db_next_module = $this->get_module()->get_next();
      if( !is_null( $db_next_module ) ) $db_next_page = $db_next_module->get_first_page();
    }

    return $db_next_page;
  }

  /**
   * TODO: document
   */
  public function get_previous_for_response( $db_response )
  {
    $expression_manager = lib::create( 'business\expression_manager' );

    // start by getting the page one rank lower than the current
    $db_previous_page = $this->get_previous();

    if( !is_null( $db_previous_page ) )
    {
      try
      {
        // make sure the page's module is valid
        $db_module = $db_previous_page->get_module();
        if( !$expression_manager->evaluate( $db_response, $db_module->precondition ) )
        {
          do { $db_module = $db_module->get_previous(); }
          while( !is_null( $db_module ) && !$expression_manager->evaluate( $db_response, $db_module->precondition ) );
          $db_previous_page = is_null( $db_module ) ? NULL : $db_module->get_last_page();
        }

        // if there is a previous page then make sure to test its precondition if a response is included in the request
        if( !is_null( $db_previous_page ) && !$expression_manager->evaluate( $db_response, $db_previous_page->precondition ) )
          $db_previous_page = $db_previous_page->get_previous_for_response( $db_response );
      }
      catch( \cenozo\exception\runtime $e )
      {
        if( is_null( $db_response ) || $db_response->get_qnaire()->debug )
          throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__ );

        // if we're not in debug mode then log it but otherwise proceed
        log::error( $e->get_raw_message() );
      }
    }

    return $db_previous_page;
  }

  /**
   * TODO: document
   */
  public function get_next_for_response( $db_response )
  {
    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );
    $expression_manager = lib::create( 'business\expression_manager' );

    // delete any answer associated with a skipped question on the current page
    $question_sel = lib::create( 'database\select' );
    $question_sel->add_column( 'id' );
    $question_sel->add_column( 'precondition' );
    $question_mod = lib::create( 'database\modifier' );
    $question_mod->where( 'question.precondition', '!=', NULL );
    foreach( $this->get_question_list( $question_sel, $question_mod ) as $question )
    {
      if( !$expression_manager->evaluate( $db_response, $question['precondition'] ) )
      {
        $db_answer = $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $db_response->id, $question['id'] )
        );
        if( !is_null( $db_answer ) ) $db_answer->delete();
      }
    }

    // delete any answer associated with a skipped question_option on the current page
    $question_option_sel = lib::create( 'database\select' );
    $question_option_sel->add_column( 'id' );
    $question_option_sel->add_column( 'precondition' );
    $question_option_sel->add_table_column( 'question', 'id', 'question_id' );
    $question_option_mod = lib::create( 'database\modifier' );
    $question_option_mod->join( 'question', 'question_option.question_id', 'question.id' );
    $question_option_mod->where( 'question.type', '=', 'list' );
    $question_option_mod->where( 'question.page_id', '=', $this->id );
    $question_option_mod->where( 'question_option.precondition', '!=', NULL );
    foreach( $question_option_class_name::select( $question_option_sel, $question_option_mod ) as $question_option )
    {
      if( !$expression_manager->evaluate( $db_response, $question_option['precondition'] ) )
      {
        $db_answer = $answer_class_name::get_unique_record(
          array( 'response_id', 'question_id' ),
          array( $db_response->id, $question_option['question_id'] )
        );

        if( !is_null( $db_answer ) ) $db_answer->remove_answer_value_by_option_id( $question_option['id'] );
      }
    }

    // get the page one rank lower than the current
    $db_next_page = $this->get_next();

    if( !is_null( $db_next_page ) )
    {
      try
      {
        // make sure the page's module is valid
        $db_module = $db_next_page->get_module();
        if( !$expression_manager->evaluate( $db_response, $db_module->precondition ) )
        {
          do
          {
            // delete any answer associated with the skipped module
            $db_response->delete_answers_in_module( $db_module );
            $db_module = $db_module->get_next();
          }
          while( !is_null( $db_module ) && !$expression_manager->evaluate( $db_response, $db_module->precondition ) );
          $db_next_page = is_null( $db_module ) ? NULL : $db_module->get_first_page();
        }

        // if there is a next page then make sure to test its precondition if a response is included in the request
        if( !is_null( $db_next_page ) && !$expression_manager->evaluate( $db_response, $db_next_page->precondition ) )
        {
          $db_response->delete_answers_in_page( $db_next_page );
          $db_next_page = $db_next_page->get_next_for_response( $db_response );
        }
      }
      catch( \cenozo\exception\runtime $e )
      {
        if( is_null( $db_response ) || $db_response->get_qnaire()->debug )
          throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__ );

        // if we're not in debug mode then log it but otherwise proceed
        log::error( $e->get_raw_message() );
      }
    }

    return $db_next_page;
  }

  /**
   * TODO: document
   */
  public function get_overall_rank()
  {
    $db_module = $this->get_module();

    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_constant( 'COUNT(*)', 'total', 'integer', false );
    $modifier = lib::create( 'database\modifier' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->where( 'module.qnaire_id', '=', $db_module->qnaire_id );
    $modifier->where( 'module.rank', '<', $db_module->rank );
    return static::db()->get_one( sprintf( '%s %s', $select->get_sql(), $modifier->get_sql() ) ) + $this->rank;
  }

  /**
   * TODO: document
   */
  public function get_first_question()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    return $question_class_name::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->id, 1 )
    );
  }

  /**
   * TODO: document
   */
  public function get_last_question()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    return $question_class_name::get_unique_record(
      array( 'page_id', 'rank' ),
      array( $this->id, $this->get_question_count() )
    );
  }

  /**
   * TODO: document
   */
  public function clone_from( $db_source_page )
  {
    parent::clone_from( $db_source_page );

    // replace all existing page options with those from the clone source
    $modifier = lib::create( 'database\modifier' );
    $modifier->where( 'page_id', '=', $this->id );
    static::db()->execute( sprintf( 'DELETE FROM question %s', $modifier->get_sql() ) );

    foreach( $db_source_page->get_question_object_list() as $db_source_question )
    {
      $db_question = lib::create( 'database\question' );
      $db_question->page_id = $this->id;
      $db_question->rank = $db_source_question->rank;
      // question names must be unique throughout a questionnaire
      $db_question->name = $this->get_qnaire()->id == $db_source_page->get_qnaire()->id
                         ? sprintf( '%s_COPY', $db_source_question->name )
                         : $db_source_question->name;
      $db_question->clone_from( $db_source_question );
    }
  }

  /**
   * TODO: document
   */
  public static function recalculate_average_time()
  {
    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_column( 'id' );
    $select->add_column( 'AVG( time )', 'average_time', false );
    $modifier = lib::create( 'database\modifier' );
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'page.id', '=', 'page_time.page_id', false );
    $join_mod->where( 'IFNULL( page_time.time, 0 )', '<=', 'IFNULL( page.max_time, 0 )', false );
    $modifier->join_modifier( 'page_time', $join_mod, 'left' );
    $modifier->group( 'page.id' );

    static::db()->execute( sprintf(
      "REPLACE INTO page_average_time( page_id, time )\n%s %s",
      $select->get_sql(),
      $modifier->get_sql()
    ) );
  }

  public static function recalculate_max_time()
  {
    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
    $page_time_class_name = lib::get_class_name( 'database\page_time' );

    $select = lib::create( 'database\select' );
    $select->from( 'page' );
    $select->add_column( 'id' );
    $select->add_column( 'COUNT(*)', 'total', false );
    $modifier = lib::create( 'database\modifier' );
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'page.id', '=', 'page_time.page_id', false );
    $join_mod->where( 'IFNULL( page_time.time, 0 )', '>', 0 );
    $modifier->join_modifier( 'page_time', $join_mod, 'left' );
    $modifier->group( 'page.id' );

    $page_list = array();
    foreach( static::select( $select, $modifier ) as $row )
    {
      $row['q2_index'] = ( $row['total']+1 )/2;
      $row['q1_index'] = ( $row['total']-floor( $row['q2_index'] )+1 )/2;
      $row['q3_index'] = ( $row['total']-floor( $row['q2_index'] )+1 )/2 + $row['q2_index'];

      // get Q1, Q2 and Q3 for all pages (lower quartile, median, upper quartile)
      $q1_sel = lib::create( 'database\select' );
      $q1_sel->add_column( 'time' );
      $q1_mod = lib::create( 'database\modifier' );
      $q1_mod->where( 'page_id', '=', $row['id'] );
      $q1_mod->where( 'time', '>', '0', false );
      $q1_mod->order( 'time' );
      $q1_mod->offset( floor( $row['q1_index'] )-1 );
      $q1_mod->limit( floor( $row['q1_index'] ) == $row['q1_index'] ? 1 : 2 );
      $q1_sum = 0;
      $points = $page_time_class_name::select( $q1_sel, $q1_mod );
      foreach( $points as $r ) $q1_sum += $r['time'];
      $row['q1'] = $q1_sum / count( $points );

      $q2_sel = lib::create( 'database\select' );
      $q2_sel->add_column( 'time' );
      $q2_mod = lib::create( 'database\modifier' );
      $q2_mod->where( 'page_id', '=', $row['id'] );
      $q2_mod->where( 'time', '>', '0', false );
      $q2_mod->order( 'time' );
      $q2_mod->offset( floor( $row['q2_index'] )-1 );
      $q2_mod->limit( floor( $row['q2_index'] ) == $row['q2_index'] ? 1 : 2 );
      $q2_sum = 0;
      $points = $page_time_class_name::select( $q2_sel, $q2_mod );
      foreach( $points as $r ) $q2_sum += $r['time'];
      $row['q2'] = $q2_sum / count( $points );

      $q3_sel = lib::create( 'database\select' );
      $q3_sel->add_column( 'time' );
      $q3_mod = lib::create( 'database\modifier' );
      $q3_mod->where( 'page_id', '=', $row['id'] );
      $q3_mod->where( 'page_time.time', '>', '0', false );
      $q3_mod->order( 'time' );
      $q3_mod->offset( floor( $row['q3_index'] )-1 );
      $q3_mod->limit( floor( $row['q3_index'] ) == $row['q3_index'] ? 1 : 2 );
      $q3_sum = 0;
      $points = $page_time_class_name::select( $q3_sel, $q3_mod );
      foreach( $points as $r ) $q3_sum += $r['time'];
      $row['q3'] = $q3_sum / count( $points );

      $row['range'] = $row['q3'] - $row['q1'];
      $row['fence'] = $row['q3'] + 3.0*$row['range'];

      $page_list[] = $row;
    }

    // turn read-only off for all questionnaires
    $qnaire_mod = lib::create( 'database\modifier' );
    $qnaire_mod->where( 'readonly', '=', true );
    $qnaire_list = $qnaire_class_name::select_objects( $qnaire_mod );
    foreach( $qnaire_list as $db_qnaire )
    {
      $db_qnaire->readonly = false;
      $db_qnaire->save();
    }

    // now update all page max times
    foreach( $page_list as $page )
    {
      $db_page = lib::create( 'database\page', $page['id'] );
      $db_page->max_time = $page['fence'];
      $db_page->save();
    }

    // now put back the original read-only status
    foreach( $qnaire_list as $db_qnaire )
    {
      $db_qnaire->readonly = true;
      $db_qnaire->save();
    }
  }
}
