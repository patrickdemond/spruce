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

    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $modifier->join( 'language', 'qnaire.base_language_id', 'base_language.id', '', 'base_language' );

    if( $select->has_column( 'module_descriptions' ) )
    {
      $modifier->left_join( 'module_description', 'module.id', 'module_description.module_id' );
      $modifier->left_join( 'language', 'module_description.language_id', 'module_language.id', 'module_language' );
      $select->add_column(
        'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", module_language.code, module_description.value ) SEPARATOR "`" )',
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
