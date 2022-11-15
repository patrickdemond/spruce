<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\lookup;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extends parent method
   */
  protected function setup()
  {
    if( !$this->get_argument( 'action', false ) ) parent::setup();
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    if( $lookup_data = $this->get_argument( 'action', false ) )
    {
      $db_lookup = $this->get_leaf_record();

      $csv_data = str_getcsv( $this->get_file_as_raw(), "\n" );
      foreach( $csv_data as &$row ) $row = str_getcsv( $row );

      $this->set_data( $db_lookup->process_data( $csv_data, 'apply' == $lookup_data ) );
    }
    else
    {
      parent::execute();
    }
  }
}
