<?php
/**
 * module.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent_mail;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how this module is used in a service
 */
class module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $modifier->join( 'respondent', 'respondent_mail.respondent_id', 'respondent.id' );
    $modifier->join( 'mail', 'respondent_mail.mail_id', 'mail.id' );
    $modifier->left_join( 'reminder', 'respondent_mail.reminder_id', 'reminder.id' );

    if( $select->has_column( 'type' ) )
    {
      $select->add_column(
        'IF( reminder.id IS NULL, "invitation", CONCAT_WS( " ", reminder.offset, reminder.unit ) )',
        'type',
        false
      );
    }
  }
}
