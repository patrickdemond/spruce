<?php
/**
 * get.class.php
 */

namespace pine\service\embedded_file;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extend parent property
   */
  protected static $base64_column_list = ['data' => 'application/octet-stream']; // allow any file type
}
