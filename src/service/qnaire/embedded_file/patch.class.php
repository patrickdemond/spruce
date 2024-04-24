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

    // add the true mime type and file size to the record
    $file = $this->get_argument( 'file', NULL );
    if( !is_null( $file ) )
    {
      $content_type = util::get_header( 'Content-Type' );
      $base64_column_list = $this->get_leaf_module()->get_base64_column_list();
      $mime_type = $base64_column_list[$file];
      if( in_array( $content_type, [$mime_type, 'application/octet-stream'] ) )
      {
        $filename = sprintf( '%s/%s', TEMP_PATH, bin2hex( openssl_random_pseudo_bytes( 8 ) ) );
        $file = $this->get_file_as_raw();
        file_put_contents( $filename, $file );

        $db_embedded_file = $this->get_leaf_record();
        $db_embedded_file->size = filesize( $filename );
        $db_embedded_file->mime_type = mime_content_type( $filename );
        $db_embedded_file->save();

        unlink( $filename );
      }
    }
  }
}
