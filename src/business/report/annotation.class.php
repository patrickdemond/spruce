<?php
/**
 * annotation.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business\report;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Contact report
 */
class annotation extends \cenozo\business\report\base_report
{
  /**
   * Build the report
   * @access protected
   */
  protected function build()
  {
    $question_class_name = lib::get_class_name( 'database\question' );
    $select = lib::create( 'database\select' );
    $modifier = lib::create( 'database\modifier' );

    // parse the restriction details
    $qnaire_id = NULL;
    foreach( $this->get_restriction_list( false ) as $restriction )
      if( 'qnaire' == $restriction['name'] ) $qnaire_id = $restriction['value'];

    $select->add_column( 'module.name', 'moduleName', false );
    $select->add_column( 'page.name', 'pageName', false );
    $select->add_column( 'CONCAT_WS( "_", question.name, qnaire.variable_suffix )', 'questionName', false );
    $select->add_column( 'question.precondition', 'precondition', false );
    $select->add_column( 'GROUP_CONCAT( IF( language.code = "en", question_description.value, "" ) SEPARATOR "" )', 'prompt_en', false );
    $select->add_column( 'GROUP_CONCAT( IF( language.code = "fr", question_description.value, "" ) SEPARATOR "" )', 'prompt_fr', false );

    $modifier->join( 'page', 'question.page_id', 'page.id' );
    $modifier->join( 'module', 'page.module_id', 'module.id' );
    $modifier->join( 'qnaire', 'module.qnaire_id', 'qnaire.id' );
    $modifier->join( 'question_description', 'question.id', 'question_description.question_id' );
    $modifier->join( 'language', 'question_description.language_id', 'language.id' );
    $modifier->where( 'qnaire.id', '=', $qnaire_id );
    $modifier->order( 'module.rank' );
    $modifier->order( 'page.rank' );
    $modifier->order( 'question.rank' );
    $modifier->group( 'question.id' );

    $this->add_table_from_select( NULL, $question_class_name::select( $select, $modifier ) );
  }
}
