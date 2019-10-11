<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\response;
use cenozo\lib, cenozo\log, pine\util;

class post extends \cenozo\service\post
{
  /**
   * Extend parent method
   */
  public function finish()
  {
    parent::finish();

    // create the attributes for this response
    $this->get_leaf_record()->create_attributes();
  }
}
