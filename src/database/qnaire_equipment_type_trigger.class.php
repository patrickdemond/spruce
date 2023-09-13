<?php
/**
 * qnaire_equipment_type_trigger.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_equipment_type_trigger: record
 */
class qnaire_equipment_type_trigger extends qnaire_trigger
{
  /**
   * Executes this trigger for a given response
   * @param database\response $db_response
   */
  public function execute( $db_response )
  {
    // Note: this method does not use the parent class' ::check_trigger method since
    //       equipment_type triggers are always executed

    $answer_class_name = lib::get_class_name( 'database\answer' );
    $equipment_class_name = lib::get_class_name( 'database\equipment' );

    $db_participant = $db_response->get_respondent()->get_participant();
    $db_qnaire = $this->get_qnaire();
    $db_question = $this->get_question();
    $db_answer = $answer_class_name::get_unique_record(
      array( 'response_id', 'question_id' ),
      array( $db_response->id, $db_question->id )
    );

    // do not proceed if there is no answer or it is not a string (serial number)
    if( is_null( $db_answer ) ) return;
    $serial_number = util::json_decode( $db_answer->value );
    if( !is_string( $serial_number ) ) return;

    if( $db_qnaire->debug )
    {
      log::info( sprintf(
        'Creating %s "%s" equipment %s due to question "%s" (questionnaire "%s")',
        $this->get_equipment_type()->name,
        $serial_number,
        $this->loaned ? 'loaned' : 'returned',
        $db_question->name,
        $db_qnaire->name
      ) );
    }

    // see if the equipment already exists
    $db_equipment = $equipment_class_name::get_unique_record( 'serial_number', $serial_number );
    if( is_null( $db_equipment ) )
    {
      $session = lib::create( 'business\session' );
      $db_effective_site = $session->get_effective_site();
      $db_effective_user = $session->get_effective_user();

      $db_equipment = lib::create( 'database\equipment' );
      $db_equipment->equipment_type_id = $this->equipment_type_id;
      $db_equipment->site_id = $db_effective_site->id;
      $db_equipment->serial_number = $serial_number;
      $db_equipment->note = sprintf(
        'Created by Pine after questionnaire "%s" '.
        'was completed by user "%s" '.
        'from site "%s" '.
        'with question "%s"',
        $db_qnaire->name,
        $db_effective_user->name,
        $db_effective_site->name,
        $db_question->name,
      );
      $db_equipment->save();
    }

    $datetime_obj = util::get_datetime_object( $db_response->last_datetime );
    if( $this->loaned )
    {
      // if the equipment is marked as loaned then mark it as returned
      $equipment_loan_mod = lib::create( 'database\modifier' );
      $equipment_loan_mod->where( 'end_datetime', '=', NULL );
      foreach( $db_equipment->get_equipment_loan_object_list( $equipment_loan_mod ) as $db_equipment_loan )
      {
        $db_equipment_loan->end_datetime = $datetime_obj;
        $db_equipment_loan->note = 'Automatically setting end date because of new loan.';
        $db_equipment_loan->save();
      }

      // and now create a new loan record
      $db_equipment_loan = lib::create( 'database\equipment_loan' );
      $db_equipment_loan->participant_id = $db_participant->id;
      $db_equipment_loan->equipment_id = $db_equipment->id;
      $db_equipment_loan->start_datetime = $datetime_obj;
      $db_equipment_loan->save();
    }
    else // returned
    {
      // try and find the participant's loan record
      $equipment_loan_mod = lib::create( 'database\modifier' );
      $equipment_loan_mod->where( 'participant_id', '=', $db_participant->id );
      $equipment_loan_mod->order( '-end_datetime' ); // put null values first
      $equipment_loan_list = $db_equipment->get_equipment_loan_object_list( $equipment_loan_mod );
      if( 0 < count( $equipment_loan_list ) )
      {
        // if the loan record is found set the end date (if it isn't already set)
        $db_equipment_loan = current( $equipment_loan_list );
        if( is_null( $db_equipment_loan->end_datetime ) )
        {
          $db_equipment_loan->end_datetime = $datetime_obj;
          $db_equipment_loan->save();
        }
      }
      else
      {
        // close any existing loan records
        $db_current_equipment_loan = $db_equipment->get_current_equipment_loan();
        if( !is_null( $db_current_equipment_loan ) )
        {
          $db_current_equipment_loan->end_datetime = $datetime_obj;
          $db_current_equipment_loan->save();
        }

        // create the loan record if it doesn't already exist
        $db_equipment_loan = lib::create( 'database\equipment_loan' );
        $db_equipment_loan->participant_id = $db_participant->id;
        $db_equipment_loan->equipment_id = $db_equipment->id;
        $db_equipment_loan->start_datetime = $datetime_obj;
        $db_equipment_loan->end_datetime = $datetime_obj;
        $db_equipment_loan->note = 'Automatically setting start date because loan was never created.';
        $db_equipment_loan->save();
      }
    }
  }
}
