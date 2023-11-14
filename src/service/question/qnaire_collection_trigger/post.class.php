<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question\qnaire_collection_trigger;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function prepare()
  {
    parent::prepare();

    // add the qnaire id from the question
    $record = $this->get_leaf_record();
    $db_question = $this->get_parent_record();
    $record->qnaire_id = $db_question->get_qnaire()->id;
  }
}
