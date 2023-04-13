<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\embedded_file;
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

      $filename = sprintf( '%s/%s', TEMPORARY_FILES_PATH, bin2hex( openssl_random_pseudo_bytes( 8 ) ) );
      $file = $this->get_file_as_raw();
      file_put_contents( $filename, $file );

      $db_embedded_file = $this->get_leaf_record();
      $db_embedded_file->size = filesize( $filename );
      $db_embedded_file->data = base64_encode( $file );
      $db_embedded_file->mime_type = mime_content_type( $filename );
      $db_embedded_file->save();

      unlink( $filename );
    }
  }
}
