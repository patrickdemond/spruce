<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\stage;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    $db_qnaire = $this->get_leaf_record()->get_qnaire();

    if( $db_qnaire->readonly ) throw lib::create(
      'exception\notice',
      'The operation cannot be completed because the questionnaire is in read-only mode.',
      __METHOD__
    );

    $data = $this->get_file_as_array();
    if( array_key_exists( 'precondition', $data ) )
    {
      // validate the precondition
      $expression_manager = lib::create( 'business\expression_manager', $db_qnaire );
      $error = $expression_manager->validate( $data['precondition'] );
      if( !is_null( $error ) )
      {
        $this->set_data( $error );
        $this->status->set_code( 306 );
      }
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    // if the first/last module chagned then adjust the adjacent stage
    $db_stage = $this->get_leaf_record();
    $data = $this->get_file_as_array();
    if( array_key_exists( 'first_module_id', $data ) )
    {
      $db_prev_stage = $db_stage->get_previous();
      if( !is_null( $db_prev_stage ) )
      {
        $db_prev_stage->last_module_id = $db_stage->get_first_module()->get_previous()->id;
        $db_prev_stage->save();
      }
    }

    if( array_key_exists( 'last_module_id', $data ) )
    {
      $db_next_stage = $db_stage->get_next();
      if( !is_null( $db_next_stage ) )
      {
        $db_next_stage->first_module_id = $db_stage->get_last_module()->get_next()->id;
        $db_next_stage->save();
      }
    }

    if( array_key_exists( 'rank', $data ) )
    {
      // all modules need to have their rank updated based on the change to the stage's rank
      $rank = 1;

      $stage_mod = lib::create( 'database\modifier' );
      $stage_mod->order( 'stage.rank' );
      foreach( $db_stage->get_qnaire()->get_stage_object_list( $stage_mod ) as $db_temp_stage )
      {
        $module_mod = lib::create( 'database\modifier' );
        $module_mod->order( 'module.rank' );
        foreach( $db_temp_stage->get_module_object_list( $module_mod ) as $db_module )
        {
          $db_module->rank = $rank;
          $db_module->save();
          $rank++;
        }
      }
    }
  }
}
