<?php
/**
 * image.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 * @filesource
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * image: record
 */
class image extends \cenozo\database\record
{
  /**
   * Returns the HTML-ready <img> tag
   * @param string $width Optional width (100) in pixels or percent (50%)
   * @return string
   */
  public function get_tag( $width = NULL )
  {
    return sprintf(
      '<img %ssrc="data:%s;base64,%s" />',
      !is_null( $width ) ? sprintf( 'style="width: %s;" ', $width ) : '',
      $this->mime_type,
      $this->data
    );
  }
}
