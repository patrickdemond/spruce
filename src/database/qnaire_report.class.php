<?php
/**
 * qnaire_report.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_report: record
 */
class qnaire_report extends \cenozo\database\has_data
{
  /**
   * 
  /**
   * Creates a qnaire_report from an object
   * @param object $qnaire_report
   * @param database\qnaire $db_qnaire The qnaire to associate the qnaire_report to
   * @return database\qnaire_report
   * @static
   */
  public static function create_from_object( $qnaire_report, $db_qnaire )
  {
    $language_class_name = lib::get_class_name( 'database\language' );
    $db_language = $language_class_name::get_unique_record( 'code', $qnaire_report->language );

    $db_qnaire_report = new static();
    $db_qnaire_report->qnaire_id = $db_qnaire->id;
    $db_qnaire_report->language_id = $db_language->id;
    $db_qnaire_report->data = $qnaire_report->data;
    $db_qnaire_report->save();

    // add all qnaire_report data
    foreach( $qnaire_report->qnaire_report_data_list as $qnaire_report_data )
    {
      $db_qnaire_report_data = lib::create( 'database\qnaire_report_data' );
      $db_qnaire_report_data->qnaire_report_id = $db_qnaire_report->id;
      $db_qnaire_report_data->name = $qnaire_report_data->name;
      $db_qnaire_report_data->code = $qnaire_report_data->code;
      $db_qnaire_report_data->save();
    }

    return $db_qnaire_report;
  }


  /**
   * Writes the filled report file to disk, returning any errors.
   * 
   * @param array $data An array of key=>value pairs to fill into the PDF form
   * @param string $filename Where to write the filled-out PDF file
   * @return string Any errors encountered while writing the PDF (NULL if there are none)
   */
  public function fill_and_write_form( $data, $filename )
  {
    // temporarily write the pdf report to disk
    $this->create_data_file();

    $pdf_writer = lib::create( 'business\pdf_writer' );
    $pdf_writer->set_template( $this->get_data_filename() );
    $pdf_writer->fill_form( $data );
    $success = $pdf_writer->save( $filename );

    // delete the temporary pdf template file
    $this->delete_data_file();

    return $success ? NULL : $pdf_writer->get_error();
  }
}
