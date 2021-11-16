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
    $stage_class_name = lib::get_class_name( 'database\stage' );

    if( in_array( $this->status, ['not ready', 'parent skipped', 'ready'] ) )
    {
      $db_response = $this->get_response();
      $db_stage = $this->get_stage();

      if( $db_stage->is_last_stage() )
      {
        // the last stage can only proceed once all other stages are completed or skipped
        $response_stage_mod = lib::create( 'database\modifier' );
        $response_stage_mod->where( 'stage_id', '!=', $db_stage->id );
        $response_stage_mod->where( 'status', 'NOT IN', array( 'completed', 'skipped', 'parent skipped' ) );
        if( 0 < $db_response->get_response_stage_count( $response_stage_mod ) )
        {
          $this->status = 'not ready';
        }
        else // we still evaluate the precondition, if there is one
        {
          $expression_manager = lib::create( 'business\expression_manager', $db_response );
          $this->status = $expression_manager->evaluate( $db_stage->precondition ) ? 'ready' : 'not ready';
        }
      }
      else
      {
        $expression_manager = lib::create( 'business\expression_manager', $db_response );
        $this->status = $expression_manager->evaluate( $db_stage->precondition ) ? 'ready' : 'not ready';

        // skip stages which are dependent on a parent stage which has been skipped
        if( 'not ready' == $this->status )
        {
          $matches = array();
          if( preg_match_all( '/#[^#]+#/', $db_stage->precondition, $matches ) )
          {
            foreach( $matches[0] as $match )
            {
              $parent_stage_name = preg_replace( '/\.status\(\)$/', '', trim( $match, '#' ) );
              $db_parent_stage = $stage_class_name::get_unique_record(
                array( 'qnaire_id', 'name' ),
                array( $db_stage->qnaire_id, $parent_stage_name )
              );
              if( !is_null( $db_parent_stage ) )
              {
                $db_parent_response_stage = static::get_unique_record(
                  array( 'response_id', 'stage_id' ),
                  array( $db_response->id, $db_parent_stage->id )
                );

                if( !is_null( $db_parent_response_stage ) )
                {
                  if( 'skipped' == $db_parent_response_stage->status )
                  {
                    $this->status = 'parent skipped';
                    break;
                  }
                }
              }
            }
          }
        }
      }

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

      // if we're re-launching the stage then remove the end datetime
      if( 'completed' == $this->status ) $this->end_datetime = NULL;
      // otherwise we're launching for the first time so set the start datetime
      else $this->start_datetime = util::get_datetime_object();

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
      if( 'skip' == $this->get_deviation_type()->name )
      {
        $this->deviation_type_id = NULL;
        $this->deviation_comments = NULL;
      }
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
    $this->start_datetime = NULL;
    $this->end_datetime = NULL;
    $this->save();

    // now update the response's status in case this results in ending the interview
    $this->get_response()->update_status();
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
    $this->deviation_comments = NULL;
    $this->page_id = NULL;
    $this->start_datetime = NULL;
    $this->end_datetime = NULL;
    $this->comments = NULL;
    $this->update_status();
  }

  /**
   * Marks the stage as complete (should only be called when all questions have been answered)
   */
  public function complete()
  {
    // go back to page selection
    $db_response = $this->get_response();
    $db_response->page_id = NULL;
    $db_response->stage_selection = true;
    $db_response->save();

    // we've moved past the last page in the stage, so mark it as complete
    $this->status = 'completed';
    $this->page_id = NULL;
    $this->end_datetime = util::get_datetime_object();
    $this->save();
  }

  /**
   * Delete all answers belonging to the stage
   */
  private function delete_answers()
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
