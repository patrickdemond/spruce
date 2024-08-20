<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\response\response_stage;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Replace parent method
   */
  protected function prepare()
  {
    parent::prepare();

    // update the status of the response before we respond with a list
    $this->get_parent_record()->update_status();
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $expression_manager = lib::create( 'business\expression_manager', $this->get_parent_record() );

    // compute the token_check_precondition for all records
    $list = parent::get_record_list();
    foreach( $list as $index => $record )
    {
      if( array_key_exists( 'token_check', $record ) )
      {
        try
        {
          $list[$index]['token_check'] =
            $expression_manager->evaluate( $record['token_check_precondition'] ) ? 1 : 0;
        }
        catch( \cenozo\exception\runtime $e )
        {
          $db_response_stage = lib::create( 'database\response_stage', $record['id'] );
          $db_stage = $db_response_stage->get_stage();

          $messages = [];
          do { $messages[] = $e->get_raw_message(); } while( $e = $e->get_previous() );
          $e = lib::create( 'exception\notice',
            sprintf(
              "Unable to evaluate token precondition for stage \"%s\".\n\n%s",
              $db_stage->name,
              implode( "\n", $messages )
            ),
            __METHOD__,
            $e
          );

          throw $e;
        }
        log::debug( $list[$index]['id'], $list[$index]['token_check'] );
      }
    }

    return $list;
  }
}
