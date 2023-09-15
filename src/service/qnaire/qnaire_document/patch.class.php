<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\qnaire_document;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $file = $this->get_argument( 'file', NULL );
    if( false !== strpos( util::get_header( 'Content-Type' ), 'application/octet-stream' ) && !is_null( $file ) )
    {
      if( 'data' != $file ) throw lib::create( 'exception\argument', 'file', $file, __METHOD__ );

      $filename = sprintf( '%s/%s', TEMP_PATH, bin2hex( openssl_random_pseudo_bytes( 8 ) ) );
      $file = $this->get_file_as_raw();
      file_put_contents( $filename, $file );

      $db_qnaire_document = $this->get_leaf_record();
      $db_qnaire_document->data = base64_encode( $file );
      $db_qnaire_document->save();

      unlink( $filename );
    }
  }
}
