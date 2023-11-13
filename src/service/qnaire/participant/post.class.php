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

    if( $this->may_continue() )
    {
      $file = $this->get_file_as_object();
      if( !property_exists( $file, 'mode' ) ||
          !in_array( $file->mode, ['confirm', 'add_mail', 'remove_mail', 'create'] ) ||
          !property_exists( $file, 'identifier_list' ) ) $this->status->set_code( 400 );
    }
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    $participant_class_name = lib::get_class_name( 'database\participant' );
    $participant_identifier_class_name = lib::get_class_name( 'database\participant_identifier' );
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    $db_qnaire = $this->get_parent_record();
    $file = $this->get_file_as_object();

    // This is a special service since participants cannot be added to the system through the web interface.
    // Instead, this service provides participant-based utility functions.
    $modifier = lib::create( 'database\modifier' );
    if( !$db_qnaire->allow_in_hold )
    {
      $modifier->join( 'participant_last_hold', 'participant.id', 'participant_last_hold.participant_id' );
      $modifier->left_join( 'hold', 'participant_last_hold.hold_id', 'hold.id' );
      $modifier->left_join( 'hold_type', 'hold.hold_type_id', 'hold_type.id' );
      $modifier->where( 'IFNULL( hold_type.type, "" )', '!=', 'final' ); // no final holds
    }
    $modifier->where( 'exclusion_id', '=', NULL ); // no exclusions

    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'participant.id', '=', 'respondent.participant_id', false );
    $join_mod->where( 'respondent.qnaire_id', '=', $db_qnaire->id );
    if( in_array( $file->mode, array( 'add_mail', 'remove_mail' ) ) )
    {
      // only affect existing respondents
      $modifier->join_modifier( 'respondent', $join_mod );
    }
    else
    {
      // only affect missing respondents
      $modifier->join_modifier( 'respondent', $join_mod, 'left' );
      $modifier->where( 'respondent.id', '=', NULL );
    }

    $identifier_id = property_exists( $file, 'identifier_id' ) ? $file->identifier_id : NULL;
    $db_identifier = is_null( $identifier_id ) ? NULL : lib::create( 'database\identifier', $identifier_id );
    $identifier_list = $participant_class_name::get_valid_identifier_list( $db_identifier, $file->identifier_list, $modifier );

    if( 'confirm' == $file->mode )
    { // return a list of all valid identifiers
      $this->set_data( $identifier_list );
    }
    else if( 0 < count( $identifier_list ) )
    {
      if( 'add_mail' == $file->mode ) $db_qnaire->mass_send_all_mail( $db_identifier, $identifier_list );
      else if( 'remove_mail' == $file->mode ) $db_qnaire->mass_remove_unsent_mail( $db_identifier, $identifier_list );
      else if( 'create' == $file->mode )
      {
        $this->set_data( $db_qnaire->mass_respondent( $db_identifier, $identifier_list ) );
      }
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
