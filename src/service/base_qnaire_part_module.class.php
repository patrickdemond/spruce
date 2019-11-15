<?php
/**
 * base_qnaire_part_module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Generic functionality used by all qnaire-part modules (module, page, question, question_option)
 */
abstract class base_qnaire_part_module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $subject = $this->get_subject();

    if( $select->has_column( 'has_precondition' ) ) $select->add_column( 'precondition IS NOT NULL', 'has_precondition' );

    if( $select->has_column( 'descriptions' ) )
    {
      $modifier->join(
        sprintf( '%s_description', $subject ),
        sprintf( '%s.id', $subject ),
        sprintf( '%s_description.%s_id', $subject, $subject )
      );
      $modifier->join(
        'language',
        sprintf( '%s_description.language_id', $subject ),
        sprintf( '%s_language.id', $subject ),
        '',
        sprintf( '%s_language', $subject )
      );
      $modifier->group( sprintf( '%s.id', $subject ) );
      $select->add_column(
        sprintf(
          'GROUP_CONCAT( DISTINCT CONCAT_WS( "`", %s_language.code, IFNULL( %s_description.value, "" ) ) SEPARATOR "`" )',
          $subject,
          $subject
        ),
        'descriptions',
        false
      );
    }

    $record = $this->get_resource();
    if( !is_null( $record ) )
    {
      $column = sprintf( 'previous_%s_id', $subject );
      if( $select->has_column( $column ) )
      {
        $function = sprintf( 'get_previous_%s', $subject );
        $db_previous_record = $record->$function();
        $select->add_constant( is_null( $db_previous_record ) ? NULL : $db_previous_record->id, $column, 'integer' );
      }

      $column = sprintf( 'next_%s_id', $subject );
      if( $select->has_column( $column ) )
      {
        $function = sprintf( 'get_next_%s', $subject );
        $db_next_record = $record->$function();
        $select->add_constant( is_null( $db_next_record ) ? NULL : $db_next_record->id, $column, 'integer' );
      }
    }
  }
}
