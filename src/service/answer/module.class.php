<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\answer;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'response', 'answer.response_id', 'response.id' );
    $modifier->join( 'question', 'answer.question_id', 'question.id' );
    $modifier->join( 'language', 'answer.language_id', 'language.id' );
    $modifier->left_join( 'user', 'answer.user_id', 'user.id' );
    $modifier->left_join( 'device', 'question.device_id', 'device.id' );
    
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'response.id', '=', 'response_device.response_id', false );
    $join_mod->where( 'question.device_id', '=', 'response_device.device_id', false );
    $modifier->join_modifier( 'response_device', $join_mod, 'left' );

    $db_answer = $this->get_resource();
    if( !is_null( $db_answer ) )
    {
      if( $select->has_column( 'files_received' ) )
      {
        $select->add_constant(
          count( $db_answer->get_data_files() ),
          'files_received',
          'integer'
        );
      }
    }
  }
}
