<?php
/**
 * answer_device.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * answer_device: record
 */ 
class answer_device extends \cenozo\database\record
{
  /**
   * Extend parent method
   */
  public function save()
  {
    $status_changed = $this->has_column_changed( 'status' );

    parent::save();

    if( $status_changed && 'cancelled' == $this->status )
    {
      // delete any answer associated with this answer device
      $db_answer = $this->get_answer();
      $db_answer->value = 'null';
      $db_answer->save();
      foreach( $db_answer->get_data_files() as $filename ) unlink( $filename );

      // abort the device
      $db_device = $this->get_device();
      if( !$db_device->emulate )
      {
        $cypress_manager = lib::create( 'business\cypress_manager', $db_device );
        $cypress_manager->abort( $uuid = $this->uuid );
      }
    }
  }

  /**
   * Launches the device by communicating with the cypress service
   */
  public function launch()
  {
    $db_device = $this->get_device();
    $cypress_manager = lib::create( 'business\cypress_manager', $db_device );
    $db_answer = $this->get_answer();
    $db_response = $db_answer->get_response();

    // if already completed then delete answer data and restart
    if( 'completed' == $this->status )
    {
      $db_answer->value = 'null';
      $db_answer->save();
      foreach( $db_answer->get_data_files() as $filename ) unlink( $filename );

      $this->uuid = NULL;
      $this->status = NULL;
      $this->start_datetime = NULL;
      $this->end_datetime = NULL;
    }

    // always include the token and language
    $data = array(
      'answer_id' => $db_answer->id,
      'barcode' => $db_response->get_respondent()->token,
      'language' => $db_response->get_language()->code,
      'interviewer' => $db_answer->get_user()->name
    );

    // then include any other data
    foreach( $db_device->get_device_data_object_list() as $db_device_data )
      $data[$db_device_data->name] = $db_device_data->get_compiled_value( $db_answer );

    $this->uuid = $db_device->emulate
                ? str_replace( '.', '-', uniqid( 'emulate.', true ) ) // emulate a response from cypress
                : $cypress_manager->launch( $data );
    $this->start_datetime = util::get_datetime_object();
    $this->status = 'in progress';
    $this->save();
  }

  /**
   * Convenience method
   * @return database\device
   */
  public function get_device()
  {
    return $this->get_answer()->get_question()->get_device();
  }
}
