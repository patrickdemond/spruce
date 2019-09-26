<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace linden\service\response;
use cenozo\lib, cenozo\log, linden\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      if( 'proceed' == $action )
      {
      }
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      if( 'proceed' == $action )
      {
        $db_response = $this->get_leaf_record();
        $db_response->move_to_next_page();
      }
    }
  }
}
