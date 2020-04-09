<?php
/**
 * get.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\downloadable
{
  /**
   * Replace parent method
   * 
   * Letters use octet-stream
   */
  protected function get_downloadable_mime_type_list()
  {
    return array( 'text/plain' );
  }

  /**
   * Replace parent method
   */
  protected function get_downloadable_public_name()
  {
    return sprintf( '%s.json', $this->get_leaf_record()->name );
  }

  /**
   * Replace parent method
   */
  protected function get_downloadable_file_path()
  {
    return sprintf( '%s/%s.json', QNAIRE_EXPORT_PATH, $this->get_leaf_record()->id );
  }

  /**
   * Extend parent method
   */
  public function prepare()
  {
    parent::prepare();

    $export = $this->get_argument( 'export', NULL );
    if( !is_null( $export ) ) $this->get_leaf_record()->generate_export( $export );
  }
}
