<?php
/**
 * get.class.php
 */

namespace pine\service\qnaire_document;
use cenozo\lib, cenozo\log, pine\util;

class get extends \cenozo\service\get
{
  /**
   * Extends parent method
   */
  protected function execute()
  {
    $leaf_record = $this->get_leaf_record();
    $data = is_null( $leaf_record ) ? NULL : $leaf_record->get_column_values( $this->select, $this->modifier );

    // convert base64 data to include mime type
    if( !is_null( $data ) ) $data['data'] = array( 'mime_type' => 'application/pdf', 'data' => $data['data'] );
    $this->set_data( $data );
  }
}
