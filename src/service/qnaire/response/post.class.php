<?php
/**
 * post.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\qnaire\response;
use cenozo\lib, cenozo\log, pine\util;

/**
 * The base class of all post services.
 */
class post extends \cenozo\service\write
{
  /**
   * Extends parent constructor
   */
  public function __construct( $path, $args, $file )
  {
    parent::__construct( 'POST', $path, $args, $file );
  }

  /**
   * Extends parent method
   */
  protected function validate()
  {
    parent::validate();

    if( $this->may_continue() )
    {
      if( !in_array( $this->get_argument( 'mode' ), ['confirm', 'create'] ) ) $this->status->set_code( 400 );
    }
  }

  /**
   * Extends parent method
   */
  protected function execute()
  {
    $db_qnaire = $this->get_parent_record();
    $file = $this->get_file_as_raw();

    if( 'confirm' == $this->get_argument( 'mode' ) )
    {
      $this->set_data( $db_qnaire->import_response_data_from_csv( $file ) );
    }
    else
    {
    }
  }

  /**
   * Overrides the parent method (this service not meant for creating resources)
   */
  protected function create_resource( $index )
  {
    return 0 == $index ? parent::create_resource( $index ) : NULL;
  }
}
