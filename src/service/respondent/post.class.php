<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

class post extends \cenozo\service\post
{
  /**
   * Extends parent method
   */
  protected function prepare()
  {
    $service_class_name = lib::get_class_name( 'service\service' );
    $time_report = $this->get_argument( 'time_report', NULL );
    if( $this->get_argument( 'time_report', false ) ) $service_class_name::prepare();
    else parent::prepare();
  }

  /**
   * Extends parent method
   */
  protected function validate()
  {
    $service_class_name = lib::get_class_name( 'service\service' );
    if( $this->get_argument( 'time_report', false ) ) $service_class_name::validate();
    else parent::validate();
  }

  /**
   * Extends parent method
   */
  protected function setup()
  {
    $service_class_name = lib::get_class_name( 'service\service' );
    if( $this->get_argument( 'time_report', false ) )
    {
      $service_class_name::setup();

      // create the temporary table needed to respond with data from multiple qnaire/participant-lists
      $respondent_class_name = lib::get_class_name( 'database\respondent' );
      $respondent_class_name::db()->execute(
        'CREATE TEMPORARY TABLE time_report ( '.
          'id INT(10) UNSIGNED NOT NULL, '.
          'response_id INT(10) UNSIGNED NOT NULL, '.
          'KEY dk_id (id), '.
          'KEY fk_response_id (response_id) '.
        ')'
      );

      foreach( $this->get_file_as_object() as $index => $row )
      {
        $respondent_sel = lib::create( 'database\select' );
        $respondent_sel->from( 'respondent' );
        $respondent_sel->add_constant( $index, 'id' );
        $respondent_sel->add_table_column( 'response', 'id', 'response_id' );

        $respondent_mod = lib::create( 'database\modifier' );
        $respondent_mod->join( 'response', 'respondent.id', 'response.respondent_id' );
        $respondent_mod->where( 'response.submitted', '=', true );
        $respondent_mod->where( 'respondent.qnaire_id', '=', $row->qnaire_id );
        $respondent_mod->where( 'respondent.participant_id', 'IN', $row->participant_id_list );

        $respondent_class_name::db()->execute( sprintf(
          'INSERT INTO time_report %s %s',
          $respondent_sel->get_sql(),
          $respondent_mod->get_sql()
        ) );
      }
    }
    else parent::setup();
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    $study_class_name = lib::get_class_name( 'database\study' );
    $identifier_class_name = lib::get_class_name( 'database\identifier' );
    $collection_class_name = lib::get_class_name( 'database\collection' );
    $consent_type_class_name = lib::get_class_name( 'database\consent_type' );
    $event_type_class_name = lib::get_class_name( 'database\event_type' );
    $alternate_consent_type_class_name = lib::get_class_name( 'database\alternate_consent_type' );
    $proxy_type_class_name = lib::get_class_name( 'database\proxy_type' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $equipment_type_class_name = lib::get_class_name( 'database\equipment_type' );
    $qnaire_class_name = lib::get_class_name( 'database\qnaire' );
    $service_class_name = lib::get_class_name( 'service\service' );

    $action = $this->get_argument( 'action', NULL );
    if( $this->get_argument( 'time_report', false ) )
    {
      $service_class_name::execute();

      $response_class_name = lib::get_class_name( 'database\response' );

      // get the requested report data
      $select = lib::create( 'database\select' );
      $select->add_table_column( 'time_report', 'id', 'index' );
      $select->add_column( 'ROUND( SUM( IF( time > max_time, max_time, time ) ) )', 'time', false );

      $modifier = lib::create( 'database\modifier' );
      $modifier->join( 'time_report', 'response.id', 'time_report.response_id' );
      $modifier->join( 'page_time', 'response.id', 'page_time.response_id' );
      $modifier->join( 'page', 'page_time.page_id', 'page.id' );
      $modifier->where( 'response.submitted', '=', true );
      $modifier->group( 'time_report.id' );

      $this->set_data( $response_class_name::select( $select, $modifier ) );
    }
    else if( 'get_respondents' == $action )
    {
      $start_time = util::get_elapsed_time();

      // first update table data
      // Note: always sync study first (it will check that the parent Pine version matches)
      $study_class_name::sync_with_parent();
      $identifier_class_name::sync_with_parent();
      $collection_class_name::sync_with_parent();
      $consent_type_class_name::sync_with_parent();
      $event_type_class_name::sync_with_parent();
      $alternate_consent_type_class_name::sync_with_parent();
      $proxy_type_class_name::sync_with_parent();
      $lookup_class_name::sync_with_parent();
      $equipment_type_class_name::sync_with_parent();

      // now update all qnaires
      $data = [];
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'parent_beartooth_url', '!=', NULL );
      $modifier->where( 'parent_username', '!=', NULL );
      foreach( $qnaire_class_name::select_objects( $modifier ) as $db_qnaire )
      {
        $db_qnaire->sync_with_parent();
        $result = $db_qnaire->get_respondents_from_beartooth();
        $result['qnaire'] = $db_qnaire->name;
        $data[] = $result;
      }
      $this->set_data( $data );

      $total_time = util::get_elapsed_time() - $start_time;
      log::info( sprintf(
        'Total processing time: %s',
        86400 > $total_time ?
          // less than a day
          preg_replace( '/^00:/', '', gmdate("H:i:s", $total_time) ) : 
          // more than a day
          sprintf( '%sd %s', gmdate('j', $total_time), gmdate("H:i:s", $total_time) ),
      ) );
    }
    else if( 'export' == $action )
    {
      $uid_list = [];
      $modifier = lib::create( 'database\modifier' );
      $modifier->where( 'parent_beartooth_url', '!=', NULL );
      $modifier->where( 'parent_username', '!=', NULL );
      foreach( $qnaire_class_name::select_objects( $modifier ) as $db_qnaire )
      {
        $db_qnaire->sync_with_parent();
        $uid_list = array_merge( $uid_list, $db_qnaire->export_respondent_data() );
      }

      // set the list of exported UIDs as the returned data
      $this->set_data( $uid_list );
    }
    else parent::execute();
  }
}
