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
  }
}
