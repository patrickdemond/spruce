<?php
/**
 * requisite.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\database;
use cenozo\lib, cenozo\log, linden\util;

/**
 * requisite: record
 */
class requisite extends \cenozo\database\has_rank
{
  /**
   * The type of record which the record has a rank for.
   * @var string
   * @access protected
   * @static
   */
  protected static $rank_parent = 'requisite_group';
}
