<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\page;
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

    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );

    if( $select->has_column( 'has_precondition' ) ) $select->add_column( 'precondition IS NOT NULL', 'has_precondition' );

    $db_page = $this->get_resource();
    if( !is_null( $db_page ) )
    {
      if( $select->has_column( 'previous_page_id' ) )
      {
        $db_previous_page = $db_page->get_previous_page();
        $select->add_constant( is_null( $db_previous_page ) ? NULL : $db_previous_page->id, 'previous_page_id', 'integer' );
      }

      if( $select->has_column( 'next_page_id' ) )
      {
        $db_next_page = $db_page->get_next_page();
        $select->add_constant( is_null( $db_next_page ) ? NULL : $db_next_page->id, 'next_page_id', 'integer' );
      }
    }
  }
}
