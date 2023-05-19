<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\downloadable
{
  /**
   * Replace parent method
   */
  protected function get_downloadable_mime_type_list()
  {
    return array( 'application/pdf' );
  }

  /**
   * Replace parent method
   */
  protected function get_downloadable_public_name()
  {
    return sprintf( 'Participant Report %s.pdf', $this->get_leaf_record()->get_respondent()->token );
  }

  /**
   * Replace parent method
   */
  protected function get_downloadable_file_path()
  {
    return $this->report_filename;
  }

  /**
   * Extend parent method
   */
  public function prepare()
  {
    parent::prepare();

    if( 'application/pdf' == $this->get_mime_type() )
    {
      $db_response = $this->get_leaf_record();
      $this->report_filename = $db_response->generate_report();
    }
  }

  /**
   * Extend parent method
   */
  public function finish()
  {
    parent::finish();

    // clean up by deleting temporary files
    if( !is_null( $this->report_filename ) && file_exists( $this->report_filename ) )
      unlink( $this->report_filename );
  }

  /**
   * @var string $report_filename
   */
  private $report_filename = NULL;
}
