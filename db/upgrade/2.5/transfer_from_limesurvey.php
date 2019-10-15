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
    $this->lsdb = $this->settings['survey_db']['database'];
  }

  public function connect_database()
  {
    $server = $this->settings['db']['server'];
    $username = $this->settings['db']['username'];
    $password = $this->settings['db']['password'];
    $name = $this->settings['db']['database_prefix'] . $this->settings['general']['instance_name'];
    $this->db = new \mysqli( $server, $username, $password, $name );
    if( $this->db->connect_error ) error( $this->db->connect_error );
    $this->db->set_charset( 'utf8mb4' );
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

    out( 'Creating the tracking F2 qnaire' );
    $sql = 'INSERT INTO qnaire( name ) VALUES ( "Tracking F2 Main" )';
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
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name, description ) '.
      'SELECT gid, qnaire.id, group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 1" ) ), '.
             'TRIM( groups.description ) '.
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
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name, description ) '.
      'SELECT gid, qnaire.id, @part2_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 2" ) ), '.
             'TRIM( groups.description ) '.
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
      'INSERT IGNORE INTO module( gid, qnaire_id, rank, name, description ) '.
      'SELECT gid, qnaire.id, @part3_offset+group_order+1, '.
             'TRIM( REPLACE( group_name, "INTERMISSION", "INTERMISSION 3" ) ), '.
             'TRIM( groups.description ) '.
      'FROM qnaire, %s.groups '.
      'WHERE qnaire.name = "Tracking F2 Main" '.
      'AND sid = 155575 '.
      'AND language = "en" '.
      'ORDER BY group_order',
      $this->lsdb
    );
    if( false === $this->db->query( $sql ) ) error( $this->db->error );

    ///////////////////////////////////////////////////////////////////////////////////////////////
    out( 'Reading all questions in parts 1, 2 and 3' );

    $page_list = [];
    foreach( [ 357653, 126673, 155575 ] as $sid )
    {
      $sql = sprintf(
        'SELECT module.id AS module_id, questions.qid, title, question, type, relevance '.
        'FROM %s.questions '.
        'JOIN %s.groups USING( gid, language ) '.
        'JOIN module ON groups.gid = module.gid '.
        'WHERE questions.sid = %d '.
        'AND parent_qid = 0 '.
        'AND language = "en" '.
        'AND title NOT LIKE "%%\\_OTSP\\_%%" '. // ignore specify-other questions
        'ORDER BY group_order, question_order',
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
          'precondition' => 1 == $row['relevance'] ? NULL : $row['relevance'],
          'name' => $row['title'],
          'description' => NULL,
          'question_list' => []
        ];

        if( ';' == $row['type'] ) // 2D array
        {
          // we only want the question title to show once
          $page['description'] = preg_replace( '(<script.*<\/script>)s', '', $row['question'] );

          // get the question rows
          $sql = sprintf(
            'SELECT qid, title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'AND scale_id = 0 '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );
          $question_rows = [];
          foreach( $subresult as $subrow ) $question_rows[] = $subrow;

          // get the question cols
          $sql = sprintf(
            'SELECT qid, title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'AND scale_id = 1 '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );
          $question_cols = [];
          foreach( $subresult as $subrow ) $question_cols[] = $subrow;

          // now create a new question for every row/col combination
          $question_rank = 1;
          foreach( $question_rows as $question_row )
          {
            foreach( $question_cols as $question_col )
            {
              $page['question_list'][] = [
                'qid' => NULL,
                'rank' => $question_rank++,
                'name' => sprintf( '%s_%s', str_replace( '_TRF2', '', $question_row['title'] ), $question_col['title'] ),
                'type' => 'string',
                'mandatory' => 0,
                'description' => sprintf( '%s %s', $question_row['question'], $question_col['question'] )
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

          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $name,
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] )
          ];
          $question['type'] = 0 == count( $option_list ) ? 'boolean' : 'list';
          if( 0 < count( $option_list ) ) $question['option_list'] = $option_list;

          $page['question_list'][] = $question;
        }
        else if( 'F' == $row['type'] ) // multiple exclusive list questions
        {
          // we only want the question title to show once
          $page['description'] = preg_replace( '(<script.*<\/script>)s', '', $row['question'] );
          $page['qid'] = $row['qid']; // set the page's qid since it represents all questions

          $option_list = $this->get_exclusive_option_list( $row['qid'] );

          $sql = sprintf(
            'SELECT qid, title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );

          // create a new question for each subquestion
          $question_rank = 1;
          foreach( $subresult as $subrow )
          {
            $question = [
              'qid' => $subrow['qid'],
              'rank' => $question_rank++,
              'name' => sprintf( '%s_%s', str_replace( '_TRF2', '', $row['title'] ), $subrow['title'] ),
              'type' => 'list',
              'description' => $subrow['question']
            ];
            $question['type'] = 0 == count( $option_list ) ? 'boolean' : 'list';
            if( 0 < count( $option_list ) ) $question['option_list'] = $option_list;

            $page['question_list'][] = $question;
          }
        }
        else if( 'N' == $row['type'] ) // number
        {
          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
            'type' => 'number'
          ];

          // look at the question to see if there is a min/max value contained in old javascript
          if( preg_match( '/\bmin: ([0-9]+)/', $row['question'], $matches ) )
            $question['minimum'] = $matches[1];
          if( preg_match( '/\bmax: ([0-9]+)/', $row['question'], $matches ) )
            $question['maximum'] = $matches[1];
          
          $page['question_list'][] = $question;
        }
        else if( 'K' == $row['type'] ) // multiple number questions
        {
          // all multiple number questions are used to provide multiple different units for a single value
          $sql = sprintf(
            'SELECT qid, title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );

          $option_list = [];
          foreach( $subresult as $subrow )
          {
            $type = NULL;
            if( preg_match( '/minutes/', strtolower( $subrow['question'] ) ) ) $type = 'minutes';
            else if( preg_match( '/hours/', strtolower( $subrow['question'] ) ) ) $type = 'hours';
            else if( preg_match( '/weeks/', strtolower( $subrow['question'] ) ) ) $type = 'weeks';
            else if( preg_match( '/months/', strtolower( $subrow['question'] ) ) ) $type = 'months';
            else if( preg_match( '/years/', strtolower( $subrow['question'] ) ) ) $type = 'years';
            else if( preg_match( '/age/', strtolower( $subrow['question'] ) ) ) $type = 'age';
            else if( preg_match( '/year/', strtolower( $subrow['question'] ) ) ) $type = 'year';

            $option_list[] = [
              'rank' => $option_rank++,
              'name' => strtoupper( $type ),
              'description' => $type,
              'exclusive' => 1
            ];
          }

          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
            'type' => 'number'
          ];

          // look at the question to see if there is a min/max value contained in old javascript
          if( preg_match( '/\bmin: ([0-9]+)/', $row['question'], $matches ) )
            $question['minimum'] = $matches[1];
          if( preg_match( '/\bmax: ([0-9]+)/', $row['question'], $matches ) )
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
          $sql = sprintf(
            'SELECT title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'AND title NOT IN ( "DK", "NA", "DK_NA", "REFUSED" ) '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );

          $option_rank = 1;
          $option_list = [];
          foreach( $subresult as $subrow )
          {
            $option_list[] = [
              'rank' => $option_rank++,
              'name' => $subrow['title'],
              'description' => $subrow['question'],
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

          foreach( $subresult as $subrow )
          {
            foreach( explode( ';', $subrow['value'] ) as $exclusive_value )
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

          $question = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
            'type' => 'list',
            'option_list' => $option_list
          ];

          $page['question_list'][] = $question;
        }
        else if( 'S' == $row['type'] ) // string
        {
          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
            'type' => 'string'
          ];
        }
        else if( 'Q' == $row['type'] ) // multiple string questions
        {
          // we only want the question title to show once
          $page['description'] = preg_replace( '(<script.*<\/script>)s', '', $row['question'] );

          $sql = sprintf(
            'SELECT qid, title, question '.
            'FROM %s.questions '.
            'WHERE parent_qid = %d '.
            'AND language = "en" '.
            'ORDER BY question_order',
            $this->lsdb,
            $row['qid']
          );
          $subresult = $this->db->query( $sql );
          if( false === $subresult ) error( $this->db->error );

          // create a new question for each subquestion
          $question_rank = 1;
          foreach( $subresult as $subrow )
          {
            $page['question_list'][] = [
              'qid' => $subrow['qid'],
              'rank' => $question_rank++,
              'name' => $subrow['title'],
              'description' => $subrow['question'],
              'type' => 'string'
            ];
          }
        }
        else if( 'T' == $row['type'] ) // text
        {
          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $row['title'],
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
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

          $page['question_list'][] = [
            'qid' => $row['qid'],
            'rank' => 1,
            'name' => $name,
            'description' => preg_replace( '(<script.*<\/script>)s', '', $row['question'] ),
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
      $sql = 'INSERT INTO page( sid, qid, module_id, rank, precondition, name, description ) VALUES '.sprintf(
        '( %d, %s, %d, %d, %s, "%s", %s )',
        $page['sid'],
        is_null( $page['qid'] ) ? 'NULL' : $page['qid'],
        $page['module_id'],
        $page['rank'],
        $page['precondition'] ? sprintf( '"%s"', addslashes( $page['precondition'] ) ) : 'NULL',
        $page['name'],
        is_null( $page['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $page['description'] ) )
      );
      if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
      $page_id = $this->db->insert_id;

      foreach( $page['question_list'] as $question )
      {
        $sql = 'INSERT INTO question( qid, page_id, rank, name, type, mandatory, minimum, maximum, description ) '.
               'VALUES '.sprintf(
          '( %s, %d, %d, "%s", "%s", %d, %s, %s, %s  )',
          is_null( $question['qid'] ) ? 'NULL' : $question['qid'],
          $page_id,
          $question['rank'],
          $question['name'],
          $question['type'],
          array_key_exists( 'mandatory', $question ) ? $question['mandatory'] : 1,
          array_key_exists( 'minimum', $question ) ? $question['minimum'] : 'NULL',
          array_key_exists( 'maximum', $question ) ? $question['maximum'] : 'NULL',
          is_null( $question['description'] ) ? 'NULL' : sprintf( '"%s"', addslashes( $question['description'] ) )
        );
        if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
        $question_id = $this->db->insert_id;

        if( array_key_exists( 'option_list', $question ) )
        {
          $sql = 'INSERT INTO question_option( question_id, rank, name, description, exclusive, extra ) VALUES ';
          foreach( $question['option_list'] as $index => $option )
          {
            $sql .= ( 0 == $index ? '' : ",\n" );
            $sql .= sprintf(
              '( %d, %d, "%s", "%s", %d, %s )',
              $question_id,
              $option['rank'],
              $option['name'],
              addslashes( $option['description'] ),
              array_key_exists( 'exclusive', $option ) ? $option['exclusive'] : 0,
              'OTHER' == $option['name'] ? '"string"' : 'NULL'
            );
          }
          if( false === $this->db->query( $sql ) ) error( $this->db->error, $sql );
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

  private function get_exclusive_option_list( $qid )
  {
    // get the list of options for all questions which make up this page
    $sql = sprintf(
      'SELECT code, answer '.
      'FROM %s.answers '.
      'WHERE qid = %d '.
      'AND language = "en" '.
      'AND code NOT IN ( "DK", "NA", "DK_NA", "REFUSED" ) '.
      'ORDER BY sortorder',
      $this->lsdb,
      $qid
    );
    $subresult = $this->db->query( $sql );
    if( false === $subresult ) error( $this->db->error );

    $option_rank = 1;
    $option_list = [];
    $possible_answer_list = [];
    foreach( $subresult as $subrow )
    {
      $option_list[] = [
        'rank' => $option_rank++,
        'name' => $subrow['code'],
        'description' => $subrow['answer'],
        'exclusive' => 1
      ];
      $possible_answer_list[] = $subrow['code'];
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
