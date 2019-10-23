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
function error( $msg )
{
  foreach( func_get_args() as $index => $arg )
  {
    if( 0 == $index ) $arg = sprintf( 'Error! %s', $arg );
    out( $arg );
  }
  die();
}

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
    $this->pinedb = $this->settings['db']['database_prefix'] . $this->settings['general']['instance_name'];
    $this->cenozodb = $this->settings['db']['database_prefix'] . $this->settings['general']['framework_name'];
    $this->lsdb = $this->settings['survey_db']['database'];
  }

  public function connect_database()
  {
    $server = $this->settings['db']['server'];
    $username = $this->settings['db']['username'];
    $password = $this->settings['db']['password'];
    $this->db = new \mysqli( $server, $username, $password, $this->pinedb );
    if( $this->db->connect_error ) error( $this->db->connect_error );
    $this->db->set_charset( 'utf8mb4' );
    $this->db->query( 'SET SESSION group_concat_max_len = 1000000' ); // used to make sure group_concat results goes beyond 1024 chars
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

    out( 'Determining English and French database IDs' );
    $sql = sprintf(
      'SELECT code, id FROM %s.language WHERE code IN( "en", "fr" )',
      $this->cenozodb
    );
    $result = $this->db->query( $sql );
    if( false === $result ) error( $this->db->error );

    foreach( $result as $row )
    {
      if( 'en' == $row['code'] ) $english_id = $row['id'];
      else if( 'fr' == $row['code'] ) $french_id = $row['id'];
    }

    out( 'Creating the tracking F2 qnaire' );
    $sql = sprintf(
      'INSERT INTO qnaire( name, base_language_id ) '.
      'SELECT "Tracking F2 Main", id '.
      'FROM %s.language '.
      'WHERE code = "en"',
      $this->cenozodb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'INSERT IGNORE INTO qnaire_has_language( qnaire_id, language_id ) '.
      'SELECT DISTINCT qnaire.id, language.id '.
      'FROM qnaire, %s.questions '.
      'JOIN %s.language ON questions.language = language.code '.
      'WHERE questions.sid IN ( 357653, 126673, 155575 )',
      $this->lsdb,
      $this->cenozodb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Adding temp columns to the module, page, and question tables' );
    $sql = sprintf(
      'ALTER TABLE module '.
      'ADD COLUMN gid INT NULL DEFAULT NULL, '.
      'ADD UNIQUE INDEX uq_gid ( gid )'
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'ALTER TABLE page '.
      'ADD COLUMN sid INT NULL DEFAULT NULL, '.
      'ADD COLUMN qid INT NULL DEFAULT NULL, '.
      'ADD INDEX uq_sid ( sid ), '.
      'ADD UNIQUE INDEX uq_qid ( qid )'
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'ALTER TABLE question '.
      'ADD COLUMN qid INT NULL DEFAULT NULL, '.
      'ADD UNIQUE INDEX uq_qid ( qid )'
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Adding all modules to the qnaire' );
    $sql = sprintf(
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name ) '.
      'SELECT gid, qnaire.id, group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 1" ) ) '.
      'FROM qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 357653 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $this->lsdb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'SELECT MAX( rank ) INTO @part2_offset '.
      'FROM module '.
      'JOIN qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main"'
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name ) '.
      'SELECT gid, qnaire.id, @part2_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 2" ) ) '.
      'FROM qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 126673 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $this->lsdb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'SELECT MAX( rank ) INTO @part3_offset '.
      'FROM module '.
      'JOIN qnaire ON module.qnaire_id = qnaire.id '.
      'WHERE qnaire.name = "Tracking F2 Main"'
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name ) '.
      'SELECT gid, qnaire.id, @part3_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 3" ) ) '.
      'FROM qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 155575 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $this->lsdb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    $sql = sprintf(
      'REPLACE INTO module_description( module_id, language_id, value ) '.
      'SELECT module.id, language.id, TRIM( groups.description ) '.
      'FROM %s.groups '.
      'JOIN %s.language ON groups.language = language.code '.
      'JOIN module USING( gid ) '.
      'WHERE sid IN( 357653, 126673, 155575 )',
      $this->lsdb,
      $this->cenozodb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Reading all questions in parts 1, 2 and 3' );

    $page_list = [];
    foreach( [ 357653, 126673, 155575 ] as $sid )
    {
      $sql = sprintf(
        'SELECT module.id AS module_id, questions.qid, title, type, relevance, '.
               'GROUP_CONCAT( question ORDER BY language.code SEPARATOR "`" ) AS question '.
        'FROM %s.questions '.
        'JOIN %s.language ON questions.language = language.code '.
        'JOIN %s.groups USING( gid, language ) '.
        'JOIN module ON groups.gid = module.gid '.
        'LEFT JOIN %s.question_attributes '.
          'ON questions.qid = question_attributes.qid AND '.
          'question_attributes.attribute = "hidden" AND '.
          'question_attributes.value = 1 '.
        'WHERE questions.sid = %d '.
        'AND parent_qid = 0 '.
        'AND title NOT LIKE "%%\\_OTSP\\_%%" '. // ignore specify-other questions
        'AND qaid IS NULL '. // don't include hidden questions
        'GROUP BY questions.qid '.
        'ORDER BY group_order, question_order',
        $this->lsdb,
        $this->cenozodb,
        $this->lsdb,
        $this->lsdb,
        $sid
      );
      $result = $this->db->query( $sql );
      if( false === $result ) error( $this->db->error );

      $last_module_id = NULL;
      foreach( $result as $row )
      {
        if( $last_module_id != $row['module_id'] )
        {
          $page_rank = 1;
          $last_module_id = $row['module_id'];
        }

        $page = [
          'sid' => $sid,
          'qid' => $row['qid'],
          'module_id' => $row['module_id'],
          'rank' => $page_rank++,
          'precondition' => 1 == $row['relevance'] ? NULL : preg_replace( '/(\.code)? == "Y"/', '', $row['relevance'] ),
          'name' => $row['title'],
          'description' => [ 'en' => NULL, 'fr' => NULL ],
          'question_list' => []
        ];

        if( ';' == $row['type'] ) // 2D array
        {
          // we only want the question title to show once
          $parts = explode( '`', $row['question'] );
          $page['description'] = [
            'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
            'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
          ];

          // get the question rows and cols
          $question_rows = $this->get_sub_question_list( $row['qid'], 0 );
          $question_cols = $this->get_sub_question_list( $row['qid'], 1 );

          // now create a new question for every row/col combination
          $question_rank = 1;
          foreach( $question_rows as $question_row )
          {
            foreach( $question_cols as $question_col )
            {
              $row_parts = explode( '`', $question_row['question'] );
              $col_parts = explode( '`', $question_col['question'] );
              $page['question_list'][] = [
                'qid' => NULL,
                'rank' => $question_rank++,
                'name' => sprintf( '%s_%s', str_replace( '_TRF2', '', $question_row['title'] ), $question_col['title'] ),
                'type' => 'string',
                'mandatory' => 0,
                'description' => [
                  'en' => sprintf( '%s %s', $row_parts[0], $col_parts[0] ),
                  'fr' => sprintf( '%s %s', $row_parts[1], $col_parts[1] )
                ]
              ];
            }
          }
        }
        else if( 'L' == $row['type'] ) // exclusive list
        {
          $option_list = $this->get_exclusive_option_list( $row['qid'] );

          // the intermission questions have duplicate names, so prefix them with the module name
          $name = $row['title'];
          if( 1 == preg_match( '/^INT_[0-9]+/', $name ) )
          {
            $sql = sprintf(
              'SELECT name FROM module WHERE id = %d',
              $page['module_id']
            );
            $subresult = $this->db->query( $sql );
            if( false === $subresult ) error( $this->db->error );
            $name = sprintf(
              '%s_%s',
              str_replace( ' ', '_', current( $subresult->fetch_row() ) ),
              $name
            );
          }

          $parts = explode( '`', $row['question'] );
          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $name,
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ]
          ];
          $question['type'] = 0 == count( $option_list ) ? 'boolean' : 'list';
          if( 0 < count( $option_list ) ) $question['option_list'] = $option_list;

          $page['question_list'][] = $question;
        }
        else if( 'F' == $row['type'] ) // multiple exclusive list questions
        {
          // we only want the question title to show once
          $parts = explode( '`', $row['question'] );
          $page['description'] = [
            'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
            'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
          ];
          $page['qid'] = $row['qid']; // set the page's qid since it represents all questions

          $option_list = $this->get_exclusive_option_list( $row['qid'] );

          // create a new question for each subquestion
          $question_rank = 1;
          foreach( $this->get_sub_question_list( $row['qid'] ) as $sub_question )
          {
            $parts = explode( '`', $sub_question['question'] );

            $question = [
              'qid' => $sub_question['qid'],
              'rank' => $question_rank++,
              'name' => sprintf( '%s_%s', str_replace( '_TRF2', '', $row['title'] ), $sub_question['title'] ),
              'type' => 'list',
              'description' => [
                'en' => $parts[0],
                'fr' => $parts[1]
              ]
            ];
            $question['type'] = 0 == count( $option_list ) ? 'boolean' : 'list';
            if( 0 < count( $option_list ) ) $question['option_list'] = $option_list;

            $page['question_list'][] = $question;
          }
        }
        else if( 'N' == $row['type'] ) // number
        {
          $parts = explode( '`', $row['question'] );
          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'number'
          ];

          // look at the question to see if there is a min/max value contained in old javascript
          if( preg_match( '/\bmin: ([0-9]+)/', $parts[0], $matches ) )
            $question['minimum'] = $matches[1];
          if( preg_match( '/\bmax: ([0-9]+)/', $parts[0], $matches ) )
            $question['maximum'] = $matches[1];

          $page['question_list'][] = $question;
        }
        else if( 'K' == $row['type'] ) // multiple number questions
        {
          // all multiple number questions are used to provide multiple different units for a single value
          $option_list = [];
          foreach( $this->get_sub_question_list( $row['qid'] ) as $sub_question )
          {
            $parts = explode( '`', $sub_question['question'] );
            $desc = NULL;
            if( preg_match( '/minutes/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'minutes', 'fr' => 'minutes' ];
            else if( preg_match( '/hours/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'hours', 'fr' => 'heures' ];
            else if( preg_match( '/weeks/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'weeks', 'fr' => 'semaines' ];
            else if( preg_match( '/months/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'months', 'fr' => 'mois' ];
            else if( preg_match( '/years/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'years', 'fr' => 'années' ];
            else if( preg_match( '/age/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'age', 'fr' => 'âge' ];
            else if( preg_match( '/year/', strtolower( $parts[0] ) ) ) $desc = [ 'en' => 'year', 'fr' => 'année' ];

            $option_list[] = [
              'rank' => $option_rank++,
              'name' => strtoupper( $desc['en'] ),
              'description' => $desc,
              'exclusive' => 1
            ];
          }

          $parts = explode( '`', $row['question'] );
          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'number'
          ];

          // look at the question to see if there is a min/max value contained in old javascript
          if( preg_match( '/\bmin: ([0-9]+)/', $parts[0], $matches ) )
            $question['minimum'] = $matches[1];
          if( preg_match( '/\bmax: ([0-9]+)/', $parts[0], $matches ) )
            $question['maximum'] = $matches[1];

          $page['question_list'][] = $question;

          // now add the unit question as an exclusive list
          $question = [
            'qid' => NULL,
            'rank' => 2,
            'type' => 'list',
            'name' => str_replace( '_TRF2', '_UNIT_TRF2', $row['title'] ),
            'description' => NULL,
            'option_list' => $option_list
          ];

          $page['question_list'][] = $question;
        }
        else if( 'M' == $row['type'] ) // non-exclusive list
        {
          // get the list of options for all questions which make up this page
          $option_rank = 1;
          $option_list = [];
          foreach( $this->get_sub_question_list( $row['qid'] ) as $sub_question )
          {
            $parts = explode( '`', $sub_question['question'] );
            $option_list[] = [
              'rank' => $option_rank++,
              'name' => $sub_question['title'],
              'description' => [ 'en' => $parts[0], 'fr' => $parts[1] ],
              'exclusive' => 0
            ];
          }

          // figure out which options are exclusive
          $sql = sprintf(
            'SELECT value '.
            'FROM %s.question_attributes '.
            'WHERE attribute = "exclude_all_others" '.
            'AND qid = %d',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );

          foreach( $subresult as $attribute )
          {
            foreach( explode( ';', $attribute['value'] ) as $exclusive_value )
            {
              foreach( $option_list as $index => $option )
              {
                if( $option['name'] == $exclusive_value )
                {
                  $option_list[$index]['exclusive'] = 1;
                  break;
                }
              }
            }
          }

          // look for array filters and apply them as preconditions to question options
          $sql = sprintf(
            'SELECT value '.
            'FROM %s.question_attributes '.
            'WHERE attribute = "array_filter" '.
            'AND qid = %d',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );
          $filter = $subresult->fetch_row();
          if( !is_null( $filter ) )
          {
            foreach( $option_list as $index => $option )
            {
              if( !preg_match( '/_NONE_|_DK_NA_|_REFUSED_/', $option['name'] ) )
                $option_list[$index]['precondition'] = sprintf( '$%s:%s$', current( $filter ), $option['name'] );
            }
          }

          // look for array filters and apply them as preconditions to question options
          $sql = sprintf(
            'SELECT value '.
            'FROM %s.question_attributes '.
            'WHERE attribute = "max_answers" '.
            'AND qid = %d',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );
          $filter = $subresult->fetch_row();
          if( !is_null( $filter ) )
            foreach( $option_list as $index => $option ) $option_list[$index]['exclusive'] = true;

          // now add the question
          $parts = explode( '`', $row['question'] );
          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'list',
            'option_list' => $option_list
          ];

          $page['question_list'][] = $question;
        }
        else if( 'S' == $row['type'] ) // string
        {
          $parts = explode( '`', $row['question'] );
          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'string'
          ];
        }
        else if( 'Q' == $row['type'] ) // multiple string questions
        {
          // we only want the question title to show once
          $parts = explode( '`', $row['question'] );
          $page['description'] = [
            'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
            'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
          ];

          // create a new question for each subquestion
          $question_rank = 1;
          foreach( $this->get_sub_question_list( $row['qid'] ) as $sub_question )
          {
            $parts = explode( '`', $sub_question['question'] );
            $page['question_list'][] = [
              'qid' => $sub_question['qid'],
              'rank' => $question_rank++,
              'name' => $sub_question['title'],
              'description' => [ 'en' => $parts[0], 'fr' => $parts[1] ],
              'type' => 'string'
            ];
          }
        }
        else if( 'T' == $row['type'] ) // text
        {
          $parts = explode( '`', $row['question'] );
          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'text'
          ];
        }
        else if( 'X' == $row['type'] ) // comment
        {
          // the intermission questions have duplicate names, so prefix them with the module name
          $name = $row['title'];
          if( 1 == preg_match( '/^INT_[0-9]+/', $name ) )
          {
            $sql = sprintf(
              'SELECT name FROM module WHERE id = %d',
              $page['module_id']
            );
            $subresult = $this->db->query( $sql );
            if( false === $subresult ) error( $this->db->error );
            $name = sprintf(
              '%s_%s',
              str_replace( ' ', '_', current( $subresult->fetch_row() ) ),
              $name
            );
          }

          $parts = explode( '`', $row['question'] );
          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $name,
            'description' => [
              'en' => preg_replace( '(<script.*<\/script>)s', '', $parts[0] ),
              'fr' => preg_replace( '(<script.*<\/script>)s', '', $parts[1] )
            ],
            'type' => 'comment'
          ];
        }

        $page_list[] = $page;
      }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Writing pages and questions to the database' );

    foreach( $page_list as $page )
    {
      $sql = 'INSERT INTO page( sid, qid, module_id, rank, precondition, name ) VALUES '.sprintf(
        '( %d, %s, %d, %d, %s, "%s" )',
        $page['sid'],
        is_null( $page['qid'] ) ? 'NULL' : $page['qid'],
        $page['module_id'],
        $page['rank'],
        $page['precondition'] ? sprintf( '"%s"', addslashes( $page['precondition'] ) ) : 'NULL',
        $page['name']
      );
      if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
      $page_id = $this->db->insert_id;

      $sql = 'REPLACE INTO page_description( page_id, language_id, value ) VALUES '.sprintf(
        '( %d, %d, %s ), ( %d, %d, %s )',
        $page_id,
        $english_id,
        is_null( $page['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $page['description']['en'] ) ),
        $page_id,
        $french_id,
        is_null( $page['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $page['description']['fr'] ) )
      );
      if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );

      foreach( $page['question_list'] as $question )
      {
        $sql = 'INSERT INTO question( qid, page_id, rank, name, type, mandatory, minimum, maximum ) '.
               'VALUES '.sprintf(
          '( %s, %d, %d, "%s", "%s", %d, %s, %s )',
          is_null( $question['qid'] ) ? 'NULL' : $question['qid'],
          $page_id,
          $question['rank'],
          $question['name'],
          $question['type'],
          array_key_exists( 'mandatory', $question ) ? $question['mandatory'] : 1,
          array_key_exists( 'minimum', $question ) ? $question['minimum'] : 'NULL',
          array_key_exists( 'maximum', $question ) ? $question['maximum'] : 'NULL'
        );
        if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
        $question_id = $this->db->insert_id;

        $sql = 'REPLACE INTO question_description( question_id, language_id, value ) VALUES '.sprintf(
          '( %d, %d, %s ), ( %d, %d, %s )',
          $question_id,
          $english_id,
          is_null( $question['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $question['description']['en'] ) ),
          $question_id,
          $french_id,
          is_null( $question['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $question['description']['fr'] ) )
        );
        if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );

        if( array_key_exists( 'option_list', $question ) )
        {
          foreach( $question['option_list'] as $index => $option )
          {
            $sql = 'INSERT INTO question_option( question_id, rank, name, exclusive, extra, precondition ) VALUES '.sprintf(
              '( %d, %d, "%s", %d, %s, %s )',
              $question_id,
              $option['rank'],
              $option['name'],
              array_key_exists( 'exclusive', $option ) ? $option['exclusive'] : 0,
              'OTHER' == $option['name'] ? '"string"' : 'NULL',
              array_key_exists( 'precondition', $option ) && !is_null( $option['precondition'] ) ?
                sprintf( '"%s"', $option['precondition'] ) : 'NULL'
            );
            if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
            $question_option_id = $this->db->insert_id;

            $sql = 'REPLACE INTO question_option_description( question_option_id, language_id, value ) VALUES '.sprintf(
              '( %d, %d, %s ), ( %d, %d, %s )',
              $question_option_id,
              $english_id,
              is_null( $option['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $option['description']['en'] ) ),
              $question_option_id,
              $french_id,
              is_null( $option['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $option['description']['fr'] ) )
            );
            if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
          }
        }
      }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Reading token attributes' );
    $attribute_list = [ '358653' => [], '126673' => [], '155575' => [] ];
    $sql = sprintf(
      'SELECT sid, attributedescriptions FROM %s.surveys WHERE sid IN( 357653, 126673, 155575 )',
      $this->lsdb
    );
    $result = $this->db->query( $sql );
    if( false === $result ) error( $this->db->error );
    foreach( $result as $row )
    {
      foreach( get_object_vars( json_decode( $row['attributedescriptions' ] ) ) as $ls_attribute => $obj )
      {
        $code = $obj->description;
        $attribute = [ 'code' => $code ];

        if( false !== strpos( $code, 'participant.limesurvey.' ) )
        { // we don't need to put other parts of the qnaire into attributes anymore
          $attribute['name'] = sprintf(
            false === strpos( $code, 'PKD_MED' ) ? '$%s$' : '$PKD_MED_%s$',
            str_replace( 'participant.limesurvey.357653.', '', $code )
          );
        }
        else
        {
          // see if the attribute record already exists
          $name = NULL;
          foreach( $attribute_list as $sid => $attributes )
          {
            foreach( $attributes as $a )
            {
              if( array_key_exists( 'code', $a ) && $code == $a['code'] )
              {
                $name = $a['name'];
                break;
              }
            }

            if( !is_null( $name ) ) break;
          }

          // if not then create it
          if( is_null( $name ) )
          {
            $name = 1 == preg_match( '/participant\.opal\.TokenAttributes\.TrackingF2\.([A-Z0-9_]+)\.(cache|label)/', $code, $matches )
                  ? ( $matches[1].( 'label' == $matches[2] ? '_LABEL' : '' ) )
                  : str_replace( [ '.age()', '.sex' ], [ '_age', '_sex' ], $code );

            $sql = sprintf(
              'INSERT INTO attribute( qnaire_id, name, code ) '.
              'SELECT qnaire.id, "%s", "%s" '.
              'FROM qnaire '.
              'WHERE qnaire.name = "Tracking F2 Main"',
              $name,
              $code
            );
            if( false === $this->db->query( $sql ) ) error( $this->db->error );

            $name = sprintf( '@%s@', $name );
          }

          $attribute['name'] = $name;
        }

        $attribute_list[$row['sid']][strtoupper( $ls_attribute )] = $attribute;
      }
    }

    $page_list = [];

    // get a list of all pages
    $sql = 'SELECT page.id, page.name, page.sid, page.precondition, '.
                  'page.qid AS page_qid, question.qid AS question_qid, '.
                  'COUNT(*) as questions '.
           'FROM page '.
           'JOIN question ON page.id = question.page_id '.
           'GROUP BY page.id '.
           'ORDER BY page.rank';
    $result = $this->db->query( $sql );
    if( false === $result ) error( $this->db->error );
    foreach( $result as $row )
    {
      $qid = is_null( $row['page_qid'] ) ? $row['question_qid'] : $row['page_qid'];

      $page_list[$qid] = [
        'sid' => $row['sid'],
        'questions' => $row['questions'],
        'id' => $row['id'],
        'name' => $row['name'],
        'precondition' => $row['precondition'],
      ];
    }

    // now go through and update all preconditions to replace 0X0X0 codes with $QUESTION$ and write to db
    foreach( $page_list as $qid => $page )
    {
      $precondition = $page['precondition'];

      // replace text-based logical operators
      $precondition = preg_replace( array( '/\band\b/', '/\bor\b/' ), array( '&&', '||' ), $precondition );

      // replace questions
      if( preg_match_all( '/([0-9]+X[0-9]+X[0-9]+)([A-Z][0-9A-Z_]+)?\.NAOK/', $precondition, $matches ) )
      {
        foreach( $matches[1] as $index => $match ) // index 1 will contain the sXgXq code
        {
          $parts = explode( 'X', $match );
          $qid = $parts[2];

          $ref_page = $page_list[$qid];
          if( 1 < $ref_page['questions'] ) // page has multiple questions which used to be sub-questions
          {
            // replace the xSgXq code with the page's name (without the TRF2 suffix)
            $precondition = str_replace(
              $match,
              sprintf( '$%s', str_replace( 'TRF2', '', $ref_page['name'] ) ),
              $precondition
            );

            // replace the NAOK with a closing $
            $precondition = str_replace( '.NAOK', '$', $precondition );
          }
          else // page has a single question, possibly with multiple options
          {
            // replace the xSgXq code with the page's name
            $precondition = str_replace(
              $match,
              sprintf( '$%s$', $page_list[$qid]['name'] ),
              $precondition
            );
          }

          // subquestions need to be delimited by a :
          if( 0 < strlen( $matches[2][$index] ) )
          {
            $precondition = str_replace( '$'.$matches[2][$index], sprintf( ':%s$', $matches[2][$index] ), $precondition );
          }
        }

        // remove all of the NAOK's
        $precondition = str_replace( '.NAOK', '', $precondition );
      }

      // replace attributes
      if( preg_match_all( '/TOKEN:(ATTRIBUTE_[0-9]+)/', $precondition, $matches ) )
        foreach( $matches[1] as $index => $match ) // index 1 will contain the sXgXq code
          $precondition = str_replace( $matches[0][$index], $attribute_list[$page['sid']][$match]['name'], $precondition );

      if( $precondition != $page['precondition'] )
      {
        $sql = sprintf(
          'UPDATE page SET precondition = "%s" WHERE id = %d',
          addslashes( $precondition ),
          $page['id']
        );
        if( false === $this->db->query( $sql ) ) error( $this->db->error );
      }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Removing the temporary gid and qid columns now that we no longer need them' );
    $sql = 'ALTER TABLE module DROP INDEX uq_gid, DROP COLUMN gid';
    if( false === $this->db->query( $sql ) ) error( $this->db->error );
    $sql = 'ALTER TABLE page DROP INDEX uq_sid, DROP INDEX uq_qid, DROP COLUMN sid, DROP COLUMN qid';
    if( false === $this->db->query( $sql ) ) error( $this->db->error );
    $sql = 'ALTER TABLE question DROP INDEX uq_qid, DROP COLUMN qid';
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    out( 'Done' );
  }

  private function get_sub_question_list( $qid, $scale_id = NULL )
  {
    // get the question rows
    $sql = sprintf(
      'SELECT qid, title, GROUP_CONCAT( question ORDER BY language.code SEPARATOR "`" ) AS question '.
      'FROM %s.questions '.
      'JOIN %s.language on questions.language = language.code '.
      'WHERE parent_qid = %d '.
      '%s'.
      'GROUP BY questions.qid '.
      'ORDER BY question_order',
      $this->lsdb,
      $this->cenozodb,
      $qid,
      is_null( $scale_id ) ? '' : sprintf( 'AND scale_id = %d ', $scale_id )
    );
    $result = $this->db->query( $sql );
    if( false === $result ) error( $this->db->error );
    $rows = [];
    foreach( $result as $row ) $rows[] = $row;
    return $rows;
  }

  private function get_exclusive_option_list( $qid )
  {
    // get the list of options for all questions which make up this page
    $sql = sprintf(
      'SELECT answers.code, GROUP_CONCAT( answer ORDER BY language.code SEPARATOR "`" ) AS answer '.
      'FROM %s.answers '.
      'JOIN %s.language ON answers.language = language.code '.
      'WHERE qid = %d '.
      'AND answers.code NOT IN ( "DK", "NA", "DK_NA", "REFUSED" ) '.
      'GROUP BY answers.qid, answers.code '.
      'ORDER BY sortorder',
      $this->lsdb,
      $this->cenozodb,
      $qid
    );
    $result = $this->db->query( $sql );
    if( false === $result ) error( $this->db->error );

    $option_rank = 1;
    $option_list = [];
    $possible_answer_list = [];
    foreach( $result as $row )
    {
      $parts = explode( '`', $row['answer'] );
      $option_list[] = [
        'rank' => $option_rank++,
        'name' => $row['code'],
        'description' => [
          'en' => $parts[0],
          'fr' => $parts[1]
        ],
        'exclusive' => 1
      ];
      $possible_answer_list[] = $row['code'];
    }

    sort( $possible_answer_list );

    return 2 == count( $possible_answer_list ) && 'NOYES' == implode( $possible_answer_list ) ?
      [] : $option_list;
  }

  /**
   * Contains all initialization parameters.
   * @var array
   * @access private
   */
  private $settings = [];
}

$import = new import();
$import->execute();
