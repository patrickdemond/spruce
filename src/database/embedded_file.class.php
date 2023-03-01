<?php
/**
 * embedded_file.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * embedded_file: record
 */
class embedded_file extends \cenozo\database\record
{
  /**
   * Returns the HTML-ready <img> tag
   * @param string $width Optional width (100) in pixels or percent (50%)
   * @return string
   */
  public function get_tag( $width = NULL )
  {
    if( preg_match( '#^image/#', $this->mime_type ) )
    {
      return sprintf(
        '<img %ssrc="data:%s;base64,%s" />',
        !is_null( $width ) ? sprintf( 'style="width: %s;" ', $width ) : '',
        $this->mime_type,
        $this->data
      );
    }
    else if( preg_match( '#^audio/#', $this->mime_type ) )
    {
      return sprintf(
        '<audio src="data:%s;base64,%s" class="full-width" controls />',
        $this->mime_type,
        $this->data
      );
    }
  }

  /**
   * Creates a embedded_file from an object
   * @param object $embedded_file
   * @param database\qnaire $db_qnaire The qnaire to associate the embedded_file to
   * @return database\embedded_file
   * @static
   */
  public static function create_from_object( $embedded_file, $db_qnaire )
  {
    $db_embedded_file = new static();
    $db_embedded_file->qnaire_id = $db_qnaire->id;
    $db_embedded_file->name = $embedded_file->name;
    $db_embedded_file->mime_type = $embedded_file->mime_type;
    $db_embedded_file->size = $embedded_file->size;
    $db_embedded_file->data = $embedded_file->data;
    $db_embedded_file->save();

    return $db_embedded_file;
  }
}
