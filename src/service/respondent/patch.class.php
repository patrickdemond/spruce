<?php
/**
 * patch.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent;
use cenozo\lib, cenozo\log, pine\util;

class patch extends \cenozo\service\patch
{
  /**
   * Extend parent method
   */
  public function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      $action = $this->get_argument( 'action', false );
      $oos_operation = sprintf( '%s response', str_replace( '_', ' ', $action ) );
      $db_respondent = $this->get_leaf_record();
      $db_response = $db_respondent->get_current_response();

      if( in_array( $action, ['proceed', 'backup'] ) )
      {
        // the page argument might be the string "null"
        $page_id = $this->get_argument( 'page_id' );
        $db_page = util::string_matches_int( $page_id ) && 0 < $page_id
                 ? lib::create( 'database\page', $page_id )
                 : NULL;

        // the proceed and backup actions always send what page the UI is currently on as an argument
        $out_of_sync = $db_response->get_out_of_sync( $oos_operation, $db_page );
        if( !is_null( $out_of_sync ) )
        {
          $this->set_data( $out_of_sync );
          $this->get_status()->set_code( 409 );
        }
      }
      else if( in_array( $action, ['reopen', 'jump', 'fast_forward_stage', 'rewind_stage'] ) )
      {
        // only jump when in debug mode
        if( 'jump' == $action && !$db_respondent->get_qnaire()->debug ) $this->get_status()->set_code( 403 );

        $out_of_sync = $db_response->get_out_of_sync( $oos_operation );
        if( !is_null( $out_of_sync ) )
        {
          $this->set_data( $out_of_sync );
          $this->get_status()->set_code( 409 );
        }
      }
    }
  }

  /**
   * Extend parent method
   */
  public function execute()
  {
    parent::execute();

    $action = $this->get_argument( 'action', false );
    if( $action )
    {
      $db_respondent = $this->get_leaf_record();

      if( 'reopen' == $action )
      {
        try
        {
          $db_respondent->reopen();
        }
        catch( \cenozo\exception\runtime $e )
        {
          throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__, $e );
        }
      }
      else if( 'resend_mail' == $action )
      {
        $db_respondent->send_all_mail();
      }
      else if( 'remove_mail' == $action )
      {
        $db_respondent->remove_unsent_mail();
      }
      else if( 'update_attributes' == $action )
      {
        $db_response = $db_respondent->get_current_response();

        // if the response doesn't exist then create it now (this will create the attributes
        if( is_null( $db_response ) ) $db_respondent->get_current_response( true );
        // if the response exists then update the attributes
        else $db_response->create_attributes( true );
      }
      else
      {
        // the following actions require the current response, but that record isn't guaranteed to exist
        $db_participant = $db_respondent->get_participant();
        $db_response = $db_respondent->get_current_response();
        if( is_null( $db_response ) )
        {
          throw lib::create( 'exception\runtime', sprintf(
            'Tried to perform a PATCH on %s for qnaire "%s" but no response record exists.',
            is_null( $db_participant ) ?
              sprintf( 'anonymous respondent %d', $db_respondent->id ) :
              sprintf( 'participant %s', $db_participant->uid ),
            $db_respondent->get_qnaire()->name
          ), __METHOD__ );
        }

        if( 'force_submit' == $action )
        {
          // submit the current response
          $db_response->page_id = NULL;
          $db_response->submitted = true;
          $db_response->save();

          // also mark the end date of the respondent as this may not happen if the last response hasn't been started
          $db_respondent->end_datetime = util::get_datetime_object();
          $db_respondent->save();

          // now add the finished event, if there is one
          if( !is_null( $db_participant ) )
          {
            $script_class_name = lib::get_class_name( 'database\script' );
            $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
            if( !is_null( $db_script ) )
              $db_script->add_finished_event( $db_participant, $db_respondent->end_datetime );
          }
        }
        else if( 'export' == $action )
        {
          $db_qnaire = $db_respondent->get_qnaire();
          $db_qnaire->sync_with_parent();
          $db_qnaire->export_respondent_data( $db_respondent );
        }
        else if( 'proceed' == $action )
        {
          try { $db_response->move_forward(); }
          catch( \cenozo\exception\runtime $e )
          {
            throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__, $e );
          }
        }
        else if( 'backup' == $action )
        {
          try { $db_response->move_backward(); }
          catch( \cenozo\exception\runtime $e )
          {
            throw lib::create( 'exception\notice', $e->get_raw_message(), __METHOD__, $e );
          }
        }
        else if( 'jump' == $action )
        {
          $db_module = lib::create( 'database\module', $this->get_argument( 'module_id' ) );
          $db_page = is_null( $db_module )
                   ? NULL
                   : $db_module->get_first_page_for_response( $db_response );
          if( is_null( $db_page ) )
          {
            throw lib::create( 'exception\notice',
              'Unable to start jump to the selected module as there are no valid pages to display.',
              __METHOD__
            );
          }

          $db_response->page_id = $db_page->id;
          $db_response->save();
        }
        else if( 'rewind_stage' == $action )
        {
          $db_qnaire = $db_respondent->get_qnaire();
          if( !$db_qnaire->stages )
          {
            log::warning( sprintf(
              'Tried to rewind a non-stage based qnaire on %s for qnaire "%s".',
              is_null( $db_participant ) ?
                sprintf( 'anonymous respondent %d', $db_respondent->id ) :
                sprintf( 'participant %s', $db_participant->uid ),
              $db_qnaire->name
            ) );
          }
          else
          {
            $db_response_stage = $db_response->get_current_response_stage();
            $db_module = $db_response_stage->get_stage()->get_first_module_for_response( $db_response );
            if( !is_null( $db_module ) )
            {
              $db_page = $db_module->get_first_page_for_response( $db_response );
              if( !is_null( $db_page ) )
              {
                $db_response_stage->page_id = $db_page->id;
                $db_response_stage->save();
                $db_response->page_id = $db_page->id;
                $db_response->save();
              }
            }
          }
        }
        else if( 'fast_forward_stage' == $action )
        {
          $db_qnaire = $db_respondent->get_qnaire();
          if( !$db_qnaire->stages )
          {
            log::warning( sprintf(
              'Tried to fast-forward a non-stage based qnaire on %s for qnaire "%s".',
              is_null( $db_participant ) ?
                sprintf( 'anonymous respondent %d', $db_respondent->id ) :
                sprintf( 'participant %s', $db_participant->uid ),
              $db_qnaire->name
            ) );
          }
          else
          {
            $db_response_stage = $db_response->get_current_response_stage();
            $db_page = $db_response_stage->get_stage()->get_last_page_for_response( $db_response );
            if( !is_null( $db_page ) )
            {
              $db_response_stage->page_id = $db_page->id;
              $db_response_stage->save();
              $db_response->page_id = $db_page->id;
              $db_response->save();
            }
          }
        }
        else if( 'set_language' == $action )
        {
          // set the response's new language
          $language_class_name = lib::get_class_name( 'database\language' );
          $db_language = $language_class_name::get_unique_record( 'code', $this->get_argument( 'code' ) );
          $db_response->set_language( $db_language );
        }
        else throw lib::create( 'exception\argument', 'action', $action, __METHOD__ );

        // only update the last datetime for certain actionso
        if( !in_array( $action, ['export', 'set_language'] ) )
        {
          $db_response->last_datetime = util::get_datetime_object();
          $db_response->save();
        }
      }
    }
  }
}
