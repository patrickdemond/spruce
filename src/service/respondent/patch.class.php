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
      // only jump when in debug mode
      if( 'jump' == $this->get_argument( 'action', false ) && !$this->get_leaf_record()->get_qnaire()->debug )
        $this->get_status()->set_code( 403 );
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
        try { $db_respondent->reopen(); }
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
      else
      {
        // the following actions require the current response, but that record isn't guaranteed to exist
        $db_response = $db_respondent->get_current_response();
        if( is_null( $db_response ) )
        {
          throw lib::create( 'exception\runtime', sprintf(
            'Tried to perform a PATCH on respondent %s for qnaire "%s" but no response record exists.',
            $db_respondent->get_participant()->uid,
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
          $script_class_name = lib::get_class_name( 'database\script' );
          $db_script = $script_class_name::get_unique_record( 'pine_qnaire_id', $db_respondent->qnaire_id );
          if( !is_null( $db_script ) )
            $db_script->add_finished_event( $db_respondent->get_participant(), $db_respondent->end_datetime );
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
              'Tried to fast forward a non-stage based qnaire on respondent %s for qnaire "%s".',
              $db_respondent->get_participant()->uid,
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
              'Tried to rewind a non-stage based qnaire on respondent %s for qnaire "%s".',
              $db_respondent->get_participant()->uid,
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

        // update the last datetime anytime the response is changed
        $db_response->last_datetime = util::get_datetime_object();
        $db_response->save();
      }
    }
  }
}
