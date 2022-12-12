<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\question;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \pine\service\base_qnaire_part_patch
{
  /**
   * Extends parent method
   */
  protected function execute()
  {
    try 
    {
      parent::execute();
    }
    catch( \cenozo\exception\database $e )
    {
      if( $e->is_constrained() )
      {
        $this->set_data( 'The value provided for "Unit List" is not in valid JSON syntax.' );
        $this->status->set_code( 306 );
      }
    }
  }
}
