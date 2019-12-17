<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\module;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \pine\service\base_qnaire_part_module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // add the total number of pages
    $this->add_count_column( 'page_count', 'page', $select, $modifier );

    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    $db_module = $this->get_resource();
    if( !is_null( $db_module ) )
    {
      if( $select->has_column( 'first_page_id' ) )
      {
        $first_page_id = NULL;
        $db_first_page = $db_module->get_first_page();
        if( !is_null( $db_first_page) ) $first_page_id = $db_first_page->id;
        $select->add_constant( $first_page_id, 'first_page_id', 'integer' );
      }
    }
  }
}
