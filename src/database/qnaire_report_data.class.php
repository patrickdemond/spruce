<?php
/**
 * qnaire_report_data.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_report_data: record
 */ 
class qnaire_report_data extends \cenozo\database\record
{
  /**
   * Allow backticks in the code column since they are used in expressions
   */
  protected static $allow_backtick_column_list = ['code'];
}
