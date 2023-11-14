<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire_collection_trigger;
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

    $modifier->join( 'qnaire', 'qnaire_collection_trigger.qnaire_id', 'qnaire.id' );
    $modifier->join( 'collection', 'qnaire_collection_trigger.collection_id', 'collection.id' );
    $modifier->join( 'question', 'qnaire_collection_trigger.question_id', 'question.id' );

    $db_qnaire_collection_trigger = $this->get_resource();
    if( !is_null( $db_qnaire_collection_trigger ) )
    {
      // include the question name and type as supplemental data
      $select->add_column(
        'CONCAT( question.name, " (", question.type, ")" )',
        'formatted_question_id',
        false
      );
    }
  }
}
