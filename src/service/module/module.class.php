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

    // add the average time it takes to complete the module
    if( $select->has_column( 'average_time' ) )
    {
      $join_sel = lib::create( 'database\select' );
      $join_sel->from( 'module' );
      $join_sel->add_column( 'id', 'module_id' );
      $join_sel->add_column( 'ROUND( AVG( time ) )', 'time', false );

      $join_mod = lib::create( 'database\modifier' );
      $join_mod->left_join( 'page', 'module.id', 'page.module_id' );
      $join_mod->left_join( 'page_time', 'page.id', 'page_time.page_id' );
      $join_mod->where( 'IFNULL( page_time.time, 0 )', '<=', 'IFNULL( page.max_time, 0 )', false );
      $join_mod->group( 'module.id' );

      $modifier->join(
        sprintf( '( %s %s ) AS module_average_time', $join_sel->get_sql(), $join_mod->get_sql() ),
        'module.id',
        'module_average_time.module_id'
      );
      $select->add_table_column( 'module_average_time', 'time', 'average_time' );
    }

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
