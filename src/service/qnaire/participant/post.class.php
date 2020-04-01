<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\participant;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\write
{
  /**
   * Extends parent constructor
   */
  public function __construct( $path, $args, $file )
  {
    parent::__construct( 'POST', $path, $args, $file );
  }

  /**
   * Extends parent method
   */
  protected function validate()
  {
    parent::validate();

    if( 300 > $this->status->get_code() )
    {
      $file = $this->get_file_as_object();
      if( !property_exists( $file, 'mode' ) ||
          !in_array( $file->mode, ['confirm', 'release'] ) ||
          !property_exists( $file, 'uid_list' ) ) {
        $this->status->set_code( 400 );
      }
    }
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $file = $this->get_file_as_object();

    // This is a special service since participants cannot be added to the system through the web interface.
    // Instead, this service provides participant-based utility functions.
    $modifier = lib::create( 'database\modifier' );
    $modifier->left_join( 'respondent', 'participant.id', 'respondent.participant_id' );
    $modifier->where( 'respondent.id', '=', NULL );
    $uid_list = $participant_class_name::get_valid_uid_list( $file->uid_list, $modifier );

    if( 'release' == $file->mode )
    { // release the participants
      if( 0 < count( $uid_list ) ) $this->get_parent_record()->mass_respondent( $uid_list );
    }
    else // 'confirm' == $file->mode
    { // return a list of all valid uids
      $this->set_data( $uid_list );
    }
  }

  /**
   * Overrides the parent method (this service not meant for creating resources)
   */
  protected function create_resource( $index )
  {
    return 0 == $index ? parent::create_resource( $index ) : NULL;
  }
}
