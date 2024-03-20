<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire_report;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /** 
   * Extend parent property
   */
  protected static $base64_column_list = ['data' => 'application/pdf'];
}
