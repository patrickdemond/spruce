<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\study;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\study\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // stratum_data is used by child instances when synchronizing with parents
    if( $select->has_column( 'stratum_data' ) )
    {
      $this->add_list_column(
        'stratum_list',
        'stratum',
        'CONCAT_WS( "$$", stratum.name, IFNULL( stratum.description, "" ) )',
        $select,
        $modifier,
        NULL,
        NULL,
        NULL,
        '&&',
        false
      );
    }
  }
}
