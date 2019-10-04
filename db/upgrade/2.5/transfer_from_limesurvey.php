#!/usr/bin/php
<?php
/**
 * This is a special script used to import the CLSA's Follow-up 2 scripts
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

ini_set( 'display_errors', '1' );
error_reporting( E_ALL | E_STRICT );
ini_set( 'date.timezone', 'US/Eastern' );

// utility functions
function out( $msg ) { printf( '%s: %s'."\n", date( 'Y-m-d H:i:s' ), $msg ); }
function error( $msg ) { out( sprintf( 'ERROR! %s', $msg ) ); }


class import
{
  /**
   * Reads the framework and application settings
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function read_settings()
  {
    // include the initialization settings
    global $SETTINGS;
    require_once '../../../settings.ini.php';
    require_once '../../../settings.local.ini.php';
    require_once $SETTINGS['path']['CENOZO'].'/src/initial.class.php';
    $initial = new \cenozo\initial();
    $this->settings = $initial->get_settings();

    define( 'BASEPATH', '' ); // needed to read the config file
    $config = require( LIMESURVEY_PATH.'/application/config/config.php' );
    $db = explode( ';', $config['components']['db']['connectionString'] );
    $parts = explode( ':', $db[0], 2 );
    $this->settings['survey_db']['driver'] = current( $parts );
    $parts = explode( '=', $db[0], 2 );
    $this->settings['survey_db']['server'] = next( $parts );
    $parts = explode( '=', $db[2], 2 );
    $this->settings['survey_db']['database'] = next( $parts );
  }

  public function connect_database()
  {
    $server = $this->settings['db']['server'];
    $username = $this->settings['db']['username'];
    $password = $this->settings['db']['password'];
    $name = $this->settings['db']['database_prefix'] . $this->settings['general']['instance_name'];
    $this->db = new \mysqli( $server, $username, $password, $name );
    if( $this->db->connect_error )
    {
      error( $this->db->connect_error );
      die();
    }
    $this->db->set_charset( 'utf8' );
  }

  /**
   * Executes the import
   * 
   * @author Patrick Emond <emondpd@mcmaster.ca>
   * @access public
   */
  public function execute()
  {
    out( 'Reading configuration parameters' );
    $this->read_settings();

    out( 'Connecting to database' );
    $this->connect_database();

    $pine_database = $this->settings['db']['database_prefix'] . $this->settings['general']['instance_name'];
    $limesurvey_database = $this->settings['survey_db']['database'];

    out( 'Creating the tracking F2 qnaire' );
    $sql = sprintf(
      'INSERT IGNORE INTO %s.qnaire( name ) VALUES ( "Tracking F2 Main" )',
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Adding all modules to the qnaire' );
    $sql = sprintf(
      'INSERT IGNORE INTO %s.module( qnaire_id, rank, name, description ) '.
      'SELECT qnaire.id, group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 1" ) ), '.
             'TRIM( groups.description ) '.
      'FROM %s.qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 357653 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'SELECT MAX( rank ) INTO @part2_offset '.
      'FROM module '.
      'JOIN qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main"'
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.module( qnaire_id, rank, name, description ) '.
      'SELECT qnaire.id, @part2_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 2" ) ), '.
             'TRIM( groups.description ) '.
      'FROM %s.qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 126673 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'SELECT MAX( rank ) INTO @part3_offset '.
      'FROM module '.
      'JOIN qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main"'
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.module( qnaire_id, rank, name, description ) '.
      'SELECT qnaire.id, @part3_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 3" ) ), '.
             'TRIM( groups.description ) '.
      'FROM %s.qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 155575 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Adding all pages to the modules' );
    $sql = sprintf(
      'INSERT IGNORE INTO %s.page( module_id, rank, name, precondition ) '.
      'SELECT module.id, question_order+1, TRIM( title ), relevance '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 357653 '.
      'AND parent_qid = 0 '.
      'AND questions.language = "en" '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.page( module_id, rank, name, precondition ) '.
      'SELECT module.id, question_order+1, TRIM( title ), relevance '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON @part2_offset+group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 126673 '.
      'AND parent_qid = 0 '.
      'AND questions.language = "en" '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.page( module_id, rank, name, precondition ) '.
      'SELECT module.id, question_order+1, TRIM( title ), relevance '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON @part3_offset+group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 155575 '.
      'AND parent_qid = 0 '.
      'AND questions.language = "en" '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Temporarily add a qid column to the question table' );
    $sql = sprintf(
      'ALTER TABLE %s.question '.
      'ADD COLUMN qid INT NULL DEFAULT NULL, '.
      'ADD INDEX dk_qid ( qid )',
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Adding all questions to the pages' );
    $sql = sprintf(
      'INSERT IGNORE INTO %s.question( qid, page_id, rank, name, description, type, multiple ) '.
      'SELECT qid, page.id, 1, TRIM( IF( title LIKE "INT\_0_", REPLACE( title, "INT", "INT1" ), title ) ), TRIM( question ), '.
      '  CASE type WHEN "L" THEN ( '.
          'IF( "DK_NA,NO,REFUSED,YES" = GROUP_CONCAT( answers.code ORDER BY answers.code ), "boolean", "list"  ) '.
        ') '.
      '            WHEN "M" THEN "list" '.
      '            WHEN "S" THEN "string" '.
      '            WHEN "Q" THEN "string" '. // TODO: multiple strings -- need to make them separate questions '.
      '            WHEN "N" THEN "number" '.
      '            WHEN "T" THEN "text" '.
      '            WHEN "F" THEN "list" '. // TODO: multiple lists -- need to make them separate questions '.
      '            WHEN "K" THEN "number" '. // TODO: multiple numbers -- used to enter value in more than one unit '.
      '            WHEN "X" THEN "comment" '.
      '  END AS type, '.
      '  "M" = type AS multiple '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'JOIN %s.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci '.
      'LEFT JOIN %s.answers USING( qid, language ) '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 357653 '.
      'AND parent_qid = 0  '.
      'AND questions.language = "en" '.
      'GROUP BY questions.qid '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.question( qid, page_id, rank, name, description, type, multiple ) '.
      'SELECT qid, page.id, 1, TRIM( IF( title LIKE "INT\_0_", REPLACE( title, "INT", "INT2" ), title ) ), TRIM( question ), '.
      '  CASE type WHEN "L" THEN ( '.
          'IF( "DK_NA,NO,REFUSED,YES" = GROUP_CONCAT( answers.code ORDER BY answers.code ), "boolean", "list"  ) '.
        ') '.
      '            WHEN "M" THEN "list" '.
      '            WHEN "S" THEN "string" '.
      '            WHEN "Q" THEN "string" '. // TODO: multiple strings -- need to make them separate questions '.
      '            WHEN "N" THEN "number" '.
      '            WHEN "T" THEN "text" '.
      '            WHEN "F" THEN "list" '. // TODO: multiple lists -- need to make them separate questions '.
      '            WHEN "K" THEN "number" '. // TODO: multiple numbers -- used to enter value in more than one unit '.
      '            WHEN "X" THEN "comment" '.
      '  END AS type, '.
      '  "M" = type AS multiple '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON @part2_offset+group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'JOIN %s.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci '.
      'LEFT JOIN %s.answers USING( qid, language ) '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 126673 '.
      'AND parent_qid = 0  '.
      'AND questions.language = "en" '.
      'GROUP BY questions.qid '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO %s.question( qid, page_id, rank, name, description, type, multiple ) '.
      'SELECT qid, page.id, 1, TRIM( IF( title LIKE "INT\_0_", REPLACE( title, "INT", "INT3" ), title ) ), TRIM( question ), '.
      '  CASE type WHEN "L" THEN ( '.
          'IF( "DK_NA,NO,REFUSED,YES" = GROUP_CONCAT( answers.code ORDER BY answers.code ), "boolean", "list"  ) '.
        ') '.
      '            WHEN "M" THEN "list" '.
      '            WHEN "S" THEN "string" '.
      '            WHEN "Q" THEN "string" '. // TODO: multiple strings -- need to make them separate questions '.
      '            WHEN "N" THEN "number" '.
      '            WHEN "T" THEN "text" '.
      '            WHEN "F" THEN "list" '. // TODO: multiple lists -- need to make them separate questions '.
      '            WHEN "K" THEN "number" '. // TODO: multiple numbers -- used to enter value in more than one unit '.
      '            WHEN "X" THEN "comment" '.
      '  END AS type, '.
      '  "M" = type AS multiple '.
      'FROM %s.questions '.
      'JOIN %s.groups USING ( gid, language ) '.
      'JOIN %s.module ON @part3_offset+group_order+1 = module.rank '.
      'JOIN %s.qnaire ON module.qnaire_id = qnaire.id '.
      'JOIN %s.page ON module.id = page.module_id AND page.name = title COLLATE utf8mb4_unicode_ci '.
      'LEFT JOIN %s.answers USING( qid, language ) '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND questions.sid = 155575 '.
      'AND parent_qid = 0  '.
      'AND questions.language = "en" '.
      'GROUP BY questions.qid '.
      'ORDER BY group_order, question_order',
      $pine_database,
      $limesurvey_database,
      $limesurvey_database,
      $pine_database,
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Adding all question_options to the questions' );
    $sql = sprintf(
      'INSERT IGNORE INTO question_option( question_id, rank, name, value, exclusive, extra ) '.
      'SELECT question.id, sortorder, TRIM( answer ), TRIM( code ), 1, IF( answer = "Other" OR code = "OTHER", "string", NULL )  '.
      'FROM %s.question '.
      'JOIN %s.answers USING( qid ) '.
      'WHERE answers.language = "en" '.
      'AND code NOT IN( "DK_NA", "REFUSED" ) '.
      'ORDER BY qid, sortorder',
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    $sql = sprintf(
      'INSERT IGNORE INTO question_option( question_id, rank, name, value, extra ) '.
      'SELECT question.id, question_order, TRIM( title ), TRIM( question ), '.
      '       IF( question = "Other" OR ( title LIKE "%%\_OT\_%%" AND title NOT LIKE "CCT_%%" ), "string", NULL ) '.
      'FROM %s.question '.
      'JOIN %s.questions subquestions ON question.qid = subquestions.parent_qid '.
      'WHERE subquestions.language = "en" '.
      'AND title NOT LIKE "%%DK_NA%%" '.
      'AND title NOT LIKE "%%REFUSED%%" '.
      'ORDER BY parent_qid, question_order',
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Making some question_options exclusive' );
    $sql = sprintf(
      'UPDATE %s.question_option '.
      'JOIN %s.question ON question_option.question_id = question.id '.
      'JOIN %s.question_attributes USING( qid ) '.
      'SET exclusive = 1 '.
      'WHERE attribute = "exclude_all_others" '.
      'AND question_attributes.value LIKE CONCAT( "%%", question_option.value COLLATE utf8mb4_unicode_ci, "%%" )',
      $pine_database,
      $pine_database,
      $limesurvey_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Reading token attributes' );
    $attribute_list = array( '358653' => [], '126673' => [], '155575' => [] );
    $sql = sprintf(
      'SELECT sid, attributedescriptions FROM %s.surveys WHERE sid IN( 357653, 126673, 155575 )',
      $limesurvey_database
    );
    $result = $this->db->query( $sql );
    if( false === $result )
    {
      error( $this->db->error );
      die();
    }
    foreach( $result as $row )
    {
      $sid = $row['sid'];
      foreach( get_object_vars( json_decode( $row['attributedescriptions' ] ) ) as $attribute => $obj )
        $attribute_list[$row['sid']][$attribute] = $obj->description;
    }

    $question_list = array();
    $sql = sprintf(
      'SELECT sid, name, precondition FROM %s.question',
      $pine_database
    );
    $result = $this->db->query( $sql );
    if( false === $result )
    {
      error( $this->db->error );
      die();
    }
    foreach( $result as $row )
    {
      // TODO: get all question list data here
    }

    // TODO: now go through and update all preconditions to replace 0X0X0 codes with $QUESTION$ and write to db

    out( 'Removing the temporary qid column now that we no longer need it' );
    $sql = sprintf(
      'ALTER TABLE %s.question DROP INDEX dk_qid, DROP COLUMN qid',
      $pine_database
    );
    if( false === $this->db->query( $sql ) )
    {
      error( $this->db->error );
      die();
    }

    out( 'Done' );
  }

  /**
   * Contains all initialization parameters.
   * @var array
   * @access private
   */
  private $settings = array();
}

$import = new import();
$import->execute();






