<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\respondent;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function setup()
  {
    parent::setup();

    if( $this->get_argument( 'no_mail', false ) )
    {
      $db_respondent = $this->get_leaf_record();
      $db_respondent->do_not_send_mail();
    }
  }
}
