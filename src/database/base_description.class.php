<?php
/**
 * base_description.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * base_description: abstract class for module, page, question and question_option description
 */
abstract class base_description extends \cenozo\database\record
{
  /**
   * Overrides the parent class
   */
  public function save()
  {
    if( $this->get_qnaire()->readonly ) throw lib::create( 'exception\notice',
      'You cannot make changes to this questionnaire because it is in read-only mode.',
      __METHOD__
    );

    parent::save();
  }

  /**
   * Returns the parent qnaire
   */
  public function get_qnaire()
  {
    $table_name = static::get_table_name();
    if( 'qnaire_description' == $table_name ) return parent::get_qnaire();
    else if( 'reminder_description' == $table_name ) return $this->get_reminder()->get_qnaire();
    else if( 'module_description' == $table_name ) return $this->get_module()->get_qnaire();
    else if( 'page_description' == $table_name ) return $this->get_page()->get_qnaire();
    else if( 'question_description' == $table_name ) return $this->get_question()->get_qnaire();
    else if( 'question_option_description' == $table_name ) return $this->get_question_option()->get_qnaire();
    else throw lib::create( 'exception\runtime', sprintf( 'Class %s should not extend base_description', $table_name ), __METHOD__ );
  }
}
