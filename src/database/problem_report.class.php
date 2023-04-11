<?php
/**
 * problem_report.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * problem_report: record
 */
class problem_report extends \cenozo\database\record
{
  /**
   * Override the parent method
   */
  public function save()
  {
    // automatically set additional data when first creating a problem_report record
    if( is_null( $this->id ) )
    {
      $db_response = lib::create( 'database\response', $this->response_id );

      $this->show_hidden = $db_response->show_hidden;

      // determine the page
      if( !is_null( $db_response->get_respondent()->end_datetime ) ) $this->page_name = 'CONCLUSION';
      else if( !is_null( $db_response->page_id ) ) $this->page_name = $db_response->get_page()->name;
      else $this->page_name = 'INTRODUCTION';

      if( array_key_exists( 'REMOTE_ADDR', $_SERVER ) )
        $this->remote_address = $_SERVER['REMOTE_ADDR'];
      if( array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) )
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
      if( array_key_exists( 'HTTP_SEC_CH_UA', $_SERVER ) )
        $this->brand = $_SERVER['HTTP_SEC_CH_UA'];
      if( array_key_exists( 'HTTP_SEC_CH_UA_MOBILE', $_SERVER ) )
        $this->platform = $_SERVER['HTTP_SEC_CH_UA_MOBILE'];
      if( array_key_exists( 'HTTP_SEC_CH_UA_PLATFORM', $_SERVER ) )
        $this->mobile = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'];
      $this->datetime = util::get_datetime_object();
    }

    parent::save();
  }
}
