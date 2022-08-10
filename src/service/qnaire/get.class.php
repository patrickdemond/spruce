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
    $output = $this->get_argument( 'output', NULL );
    return sprintf( '%s.%s', $this->get_leaf_record()->name, 'export' == $output ? 'json' : 'txt' );
  }

  /**
   * Replace parent method
   */
  protected function get_downloadable_file_path()
  {
    $output = $this->get_argument( 'output', NULL );
    return sprintf(
      '%s/%s.%s',
      'export' == $output ? QNAIRE_EXPORT_PATH : QNAIRE_PRINT_PATH,
      $this->get_leaf_record()->id,
      'export' == $output ? 'json' : 'txt'
    );
  }

  /**
   * Extend parent method
   */
  public function prepare()
  {
    parent::prepare();

    if( $this->may_continue() )
    {
      $output = $this->get_argument( 'output', NULL );
      if( !is_null( $output ) ) $this->get_leaf_record()->generate( $output );
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    if( $this->get_argument( 'test_connection', false ) )
    {
      // instead of returning the qnaire's details test the connection and return the result
      $db_qnaire = $this->get_leaf_record();
      $this->set_data( $db_qnaire->test_connection() );
    }
    else
    {
      parent::execute();
    }
  }
}
