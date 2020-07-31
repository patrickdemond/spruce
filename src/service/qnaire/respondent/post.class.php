<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\respondent;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function validate()
  {
    parent::validate();

    if( 300 > $this->get_status()->get_code() )
    {
      // make sure the participant is enrolled and not in a final hold
      $post_array = $this->get_file_as_array();
      $db_participant = lib::create( 'database\participant', $post_array['participant_id'] );
      $db_last_hold = $db_participant->get_last_hold();
      $final_hold = !is_null( $db_last_hold ) && 'final' == $db_last_hold->get_hold_type()->type;
      if( !is_null( $db_participant->exclusion_id ) || $final_hold )
      {
        $this->status->set_code( 306 );
        $this->set_data( 'Only enrolled participants who are not in a final hold may be added to the questionnaire.' );
      }
    }
  }

  /**
   * Extends parent method
   */
  protected function setup()
  {
    parent::setup();

    if( $this->get_argument( 'no_mail', false ) )
    {
      $db_respondent = $this->get_leaf_record();
      $db_respondent->do_not_send_mail();
    }
  }
}
