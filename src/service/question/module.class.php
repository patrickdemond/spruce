<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\question;
use cenozo\lib, cenozo\log, linden\util;

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
    $modifier->join( 'page', 'question.page_id', 'page.id' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
  }
}
