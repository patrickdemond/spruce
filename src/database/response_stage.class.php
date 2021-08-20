<?php
/**
 * response_stage.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * response_stage: record
 */
class response_stage extends \cenozo\database\record
{
  /**
   * Update the response stage's status
   */
  public function update_status()
  {
    if( in_array( $this->status, ['not ready', 'ready'] ) )
    {
      $expression_manager = lib::create( 'business\expression_manager', $this->get_response() );
      $this->status = $expression_manager->evaluate( $this->get_stage()->precondition ) ? 'ready' : 'not ready';
      $this->save();
    }
  }

  /**
   * Launches the stage
   */
  public function launch()
  {
    if( is_null( $this->page_id ) )
    {
      $db_module = $this->get_stage()->get_first_module();
      $this->page_id = $db_module->get_first_page()->id;
      $this->save();
    }
    
    $db_response = $this->get_response();
    $db_response->stage_selection = false;
    $db_response->page_id = $this->page_id;
    $db_response->save();

    $this->user_id = lib::create( 'business\session' )->get_user()->id;
    $this->status = 'active';
    if( !is_null( $this->deviation_type_id ) )
    {
      // if we're launching then we're no longer skipping
      if( 'skip' == $this->get_deviation_type()->name ) $this->deviation_type_id = NULL;
    }
    $this->save();
  }

  /**
   * Pauses the stage
   */
  public function pause()
  {
    $db_response = $this->get_response();
    $db_response->stage_selection = true;
    $db_response->page_id = NULL;
    $db_response->save();

    $this->status = 'paused';
    $this->save();
  }

  /**
   * Skips the stage (all answers will be deleted)
   */
  public function skip()
  {
    $this->delete_answers();
    $this->user_id = lib::create( 'business\session' )->get_user()->id;
    $this->status = 'skipped';
    $this->page_id = NULL;
    $this->save();
  }

  /**
   * Reset the stage (delete all answers)
   */
  public function reset()
  {
    // update the status (which will only work if it is already "not ready" or "ready"
    $this->delete_answers();
    $this->user_id = NULL;
    $this->status = 'not ready';
    $this->deviation_type_id = NULL;
    $this->page_id = NULL;
    $this->update_status();
  }

  /**
   * Delete all answers belonging to the stage
   */
  public function delete_answers()
  {
    // delete all questions belonging to each module belonging to the stage
    $module_sel = lib::create( 'database\select' );
    $module_sel->add_column( 'id' );
    foreach( $this->get_stage()->get_module_list( $module_sel ) as $module )
    {
      // remove answers
      $question_sel = lib::create( 'database\select' );
      $question_sel->from( 'question' );
      $question_sel->add_column( 'id' );
      $question_mod = lib::create( 'database\modifier' );
      $question_mod->join( 'page', 'question.page_id', 'page.id' );
      $question_mod->where( 'page.module_id', '=', $module['id'] );
      $sub_select_sql = sprintf( '( %s %s )', $question_sel->get_sql(), $question_mod->get_sql() );

      $answer_mod = lib::create( 'database\modifier' );
      $answer_mod->where( 'response_id', '=', $this->response_id );
      $answer_mod->where( 'question_id', 'IN', $sub_select_sql, false );
      $sql = sprintf( 'DELETE FROM answer %s', $answer_mod->get_sql() );
      static::db()->execute( $sql );

      // remove page time
      $page_sel = lib::create( 'database\select' );
      $page_sel->from( 'page' );
      $page_sel->add_column( 'id' );
      $page_mod = lib::create( 'database\modifier' );
      $page_mod->where( 'module_id', '=', $module['id'] );
      $sub_select_sql = sprintf( '( %s %s )', $page_sel->get_sql(), $page_mod->get_sql() );

      $page_time_mod = lib::create( 'database\modifier' );
      $page_time_mod->where( 'response_id', '=', $this->response_id );
      $page_time_mod->where( 'page_id', 'IN', $sub_select_sql, false );
      $sql = sprintf( 'DELETE FROM page_time %s', $page_time_mod->get_sql() );
      static::db()->execute( $sql );
    }
  }
}
