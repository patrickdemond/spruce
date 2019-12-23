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
class module extends \pine\service\base_qnaire_part_module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    // add the total number of questions
    $this->add_count_column( 'question_count', 'question', $select, $modifier );

    // add the average time it takes to complete the module
    if( $select->has_column( 'average_time' ) )
    {
      $join_sel = lib::create( 'database\select' );
      $join_sel->from( 'page' );
      $join_sel->add_column( 'id', 'page_id' );
      $join_sel->add_column( 'ROUND( AVG( time ) )', 'time', false );

      $join_mod = lib::create( 'database\modifier' );
      $sub_join_mod = lib::create( 'database\modifier' );
      $sub_join_mod->where( 'page.id', '=', 'page_time.page_id', false );
      $sub_join_mod->where( 'IFNULL( page_time.time, 0 )', '<=', 'page.max_time', false );
      $join_mod->left_join( 'page_time', 'page.id', 'page_time.page_id' );
      $join_mod->join_modifier( 'page_time', $sub_join_mod, 'left' );
      $join_mod->group( 'page.id' );

      $modifier->join(
        sprintf( '( %s %s ) AS page_average_time', $join_sel->get_sql(), $join_mod->get_sql() ),
        'page.id',
        'page_average_time.page_id'
      );
      $select->add_table_column( 'page_average_time', 'time', 'average_time' );
    }

    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $modifier->join( 'language', 'qnaire.base_language_id', 'base_language.id', '', 'base_language' );

    if( $select->has_column( 'module_descriptions' ) )
    {
      $modifier->left_join( 'module_description', 'module.id', 'module_description.module_id' );
      $modifier->left_join( 'language', 'module_description.language_id', 'module_language.id', 'module_language' );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", module_language.code, IFNULL( module_description.value, "" ) ) SEPARATOR "`" )',
        'module_descriptions',
        false
      );
    }

    $db_page = $this->get_resource();
    if( !is_null( $db_page ) )
    {
      $select->add_constant( $db_page->get_module()->get_qnaire()->get_number_of_pages(), 'qnaire_pages', 'integer' );
      $select->add_constant( $db_page->get_overall_rank(), 'qnaire_page', 'integer' );
    }
  }
}
