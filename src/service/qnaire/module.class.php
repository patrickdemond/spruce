<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

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
    parent::prepare_read( $select, $modifier );

    // add the total number of modules
    $this->add_count_column( 'module_count', 'module', $select, $modifier );

    $db_qnaire = $this->get_resource();
    if( !is_null( $db_qnaire ) )
    {
      if( $select->has_column( 'first_page_id' ) )
      {
        $first_page_id = NULL;
        $db_first_module = $db_qnaire->get_first_module();
        if( !is_null( $db_first_module ) )
        {
          $db_first_page = $db_first_module->get_first_page();
          if( !is_null( $db_first_page) ) $first_page_id = $db_first_page->id;
        }
        $select->add_constant( $first_page_id, 'first_page_id', 'integer' );
      }
    }
  }
}
