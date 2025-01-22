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
    $answer_class_name = lib::get_class_name( 'database\answer' );

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

  /**
   * Applies a patch file to the qnaire_report and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $apply = false )
  {
    $qnaire_report_data_class_name = lib::get_class_name( 'database\qnaire_report_data' );

    $difference_list = [];

    foreach( $patch_object as $property => $value )
    {
      if( 'qnaire_report_data_list' == $property )
      {
        // check every item in the patch object for additions and changes
        $add_list = [];
        $change_list = [];
        foreach( $patch_object->qnaire_report_data_list as $qnaire_report )
        {
          $db_qnaire_report_data = $qnaire_report_data_class_name::get_unique_record(
            [ 'qnaire_report_id', 'name' ],
            [ $this->id, $qnaire_report->name ]
          );

          if( is_null( $db_qnaire_report_data ) )
          {
            if( $apply )
            {
              $db_qnaire_report_data = lib::create( 'database\qnaire_report_data' );
              $db_qnaire_report_data->qnaire_report_id = $this->id;
              $db_qnaire_report_data->name = $qnaire_report->name;
              $db_qnaire_report_data->code = $qnaire_report->code;
              $db_qnaire_report_data->save();
            }
            else $add_list[] = $qnaire_report;
          }
          else
          {
            // find and add all differences
            $diff = [];
            foreach( $qnaire_report as $property => $value )
              if( $db_qnaire_report_data->$property != $qnaire_report->$property )
                $diff[$property] = $qnaire_report->$property;

            if( 0 < count( $diff ) )
            {
              if( $apply )
              {
                $db_qnaire_report_data->code = $qnaire_report->code;
                $db_qnaire_report_data->save();
              }
              else
              {
                $change_list[$qnaire_report->name] = $diff;
              }
            }
          }
        }

        // check every item in this object for removals
        $remove_list = [];
        foreach( $this->get_qnaire_report_data_object_list() as $db_qnaire_report_data )
        {
          $found = false;
          foreach( $patch_object->qnaire_report_data_list as $qnaire_report )
          {
            if( $db_qnaire_report_data->name == $qnaire_report->name )
            {
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            if( $apply ) $db_qnaire_report_data->delete();
            else $remove_list[] = $db_qnaire_report_data->name;
          }
        }

        $diff_list = [];
        if( 0 < count( $add_list ) ) $diff_list['add'] = $add_list;
        if( 0 < count( $change_list ) ) $diff_list['change'] = $change_list;
        if( 0 < count( $remove_list ) ) $diff_list['remove'] = $remove_list;
        if( 0 < count( $diff_list ) ) $difference_list['qnaire_report_data_list'] = $diff_list;
      }
      else
      {
        if( 'language' != $property && $patch_object->$property != $this->$property )
        {
          if( $apply ) $this->$property = $patch_object->$property;
          else $difference_list[$property] = $patch_object->$property;
        }
      }
    }

    if( $apply )
    {
      $this->save();
      return null;
    }
    else return 0 == count( $difference_list ) ? NULL : (object)$difference_list;
  }
}
