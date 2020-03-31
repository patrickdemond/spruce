<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( 300 > $this->get_status()->get_code() )
    {
      // do not allow a qnaire to be set to not repeated if it already has repeated qnaires
      $patch_array = $this->get_file_as_array();
      if( array_key_exists( 'repeated', $patch_array ) && is_null( $patch_array['repeated'] ) )
      {
        $db_qnaire = $this->get_leaf_record();
        if( $db_qnaire->has_duplicates() )
        {
          $this->status->set_code( 306 );
          $this->set_data(
            'You cannot set this questionnaire to not be repeated because it already has multiple responses '.
            'provided by the same participant.'
          );
        }
      }
    }
  }
}
