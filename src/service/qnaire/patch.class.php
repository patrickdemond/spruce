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

    if( $this->may_continue() )
    {
      if( !$this->get_argument( 'patch', false ) )
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

        // if the qnaire is repeated the offset must be >= 1
        if( array_key_exists( 'repeat_offset', $patch_array ) )
        {
          $db_qnaire = $this->get_leaf_record();
          if( ( array_key_exists( 'repeated', $patch_array ) && !is_null( $patch_array['repeated'] ) ) ||
              !is_null( $db_qnaire->repeated ) )
          {
            if( 1 > $patch_array['repeat_offset'] )
            {
              $this->status->set_code( 306 );
              $this->set_data( 'The repeat offset must be greater than or equal to 1.' );
            }
          }
        }

        // if the qnaire is repeated the offset must be >= 1
        if( array_key_exists( 'max_responses', $patch_array ) )
        {
          if( 0 > $patch_array['max_responses'] )
          {
            $this->status->set_code( 306 );
            $this->set_data( 'The maximum number of responses must be greater than or equal to 0.' );
          }
        }
      }
    }
  }

  /**
   * Extends parent method
   */
  protected function setup()
  {
    if( !$this->get_argument( 'patch', false ) ) parent::setup();
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    if( $patch = $this->get_argument( 'patch', false ) )
    {
      $db_qnaire = $this->get_leaf_record();
      $patch_object = util::json_decode( $this->get_file_as_raw() );
      if( is_null( $patch_object ) )
      {
        throw lib::create( 'exception\notice',
          'The patch file provided is not valid.',
          __METHOD__
        );
      }
      else $this->set_data( $db_qnaire->process_patch( $patch_object, 'apply' == $patch ) );
    }
    else
    {
      parent::execute();
    }
  }
}
