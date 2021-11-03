<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\image;
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
    $headers = apache_request_headers();
    if( false !== strpos( $headers['Content-Type'], 'application/octet-stream' ) && !is_null( $file ) )
    {
      if( 'data' != $file ) throw lib::create( 'exception\argument', 'file', $file, __METHOD__ );

      $filename = sprintf( '%s/%s', TEMPORARY_FILES_PATH, bin2hex( openssl_random_pseudo_bytes( 8 ) ) );
      $file = $this->get_file_as_raw();
      file_put_contents( $filename, $file );
      $details = getimagesize( $filename );
      $db_image = $this->get_leaf_record();
      $db_image->mime_type = $details['mime'];
      $db_image->size = filesize( $filename );
      $db_image->width = $details[0];
      $db_image->height = $details[1];
      $db_image->data = base64_encode( $file );
      $db_image->save();
      unlink( $filename );
    }
  }
}
