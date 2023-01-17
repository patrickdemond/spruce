<?php
/**
 * overview.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\overview;
use cenozo\lib, cenozo\log, pine\util;

/**
 * overview: progress
 */
class progress extends \cenozo\business\overview\base_overview
{
  /**
   * Implements abstract method
   */
  protected function build( $modifier = NULL )
  {
    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );

    $qnaire_mod = lib::create( 'database\modifier' );
    $qnaire_mod->order( 'qnaire.name' );
    foreach( $qnaire_class_name::select_objects( $qnaire_mod ) as $db_qnaire )
    {
      $respondent_count = $db_qnaire->get_respondent_count();
      if( 0 < $respondent_count )
      {
        $root_node = $this->add_root_item( $db_qnaire->name );

        $this->add_item( $root_node, 'Respondents', $respondent_count );

        // put in the number of responses
        if( !is_null( $db_qnaire->max_responses ) && 0 < $db_qnaire->max_responses )
        { // broken up by iteration
          $parent_node = $this->add_item( $root_node, 'Submitted' );
          for( $iteration = 1; $iteration <= $db_qnaire->max_responses; $iteration++ )
          {
            $respondent_mod = lib::create( 'database\modifier' );
            $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
            $respondent_mod->where( 'response.submitted', '=', true );
            $respondent_mod->where( 'rank', '=', $iteration );
            $this->add_item( $parent_node, 'Iteration #'.$iteration, $db_qnaire->get_respondent_count( $respondent_mod ) );
          }
        }
        else // no iterations or infinite iterations, so just count them all
        {
          $respondent_mod = lib::create( 'database\modifier' );
          $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
          $respondent_mod->where( 'response.submitted', '=', true );
          $this->add_item( $root_node, 'Submitted', $db_qnaire->get_respondent_count( $respondent_mod ) );
        }

        // now put in where incomplete responses are stopped
        $parent_node = $this->add_item( $root_node, 'Incomplete' );

        // now put in where incomplete responses are stopped
        $respondent_mod = lib::create( 'database\modifier' );
        $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
        $respondent_mod->where( 'response.submitted', '=', false );
        $this->add_item( $parent_node, 'Total', $db_qnaire->get_respondent_count( $respondent_mod ) );

        $respondent_mod = lib::create( 'database\modifier' );
        $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
        $respondent_mod->where( 'response.submitted', '=', false );
        $respondent_mod->where( 'response.page_id', '=', NULL );
        $this->add_item( $parent_node, 'Introduction', $db_qnaire->get_respondent_count( $respondent_mod ) );

        // now break it up by page
        $respondent_sel = lib::create( 'database\select' );
        $respondent_sel->add_table_column( 'page', 'name' );
        $respondent_sel->add_column( 'COUNT(*)', 'total', false );
        $respondent_mod = lib::create( 'database\modifier' );
        $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
        $respondent_mod->join( 'page', 'response.page_id', 'page.id' );
        $respondent_mod->join( 'module', 'page.module_id', 'module.id' );
        $respondent_mod->where( 'response.submitted', '=', false );
        $respondent_mod->group( 'page.id' );
        $respondent_mod->order( 'module.rank' );
        $respondent_mod->order( 'page.rank' );
        foreach( $db_qnaire->get_respondent_list( $respondent_sel, $respondent_mod ) as $respondent )
          $this->add_item( $parent_node, $respondent['name'], $respondent['total'] );
      }
    }
  }
}
