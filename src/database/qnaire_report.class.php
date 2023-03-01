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
class qnaire_report extends \cenozo\database\record
{
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
}
