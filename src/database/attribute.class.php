<?php
/**
 * attribute.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * attribute: record
 */
class attribute extends \cenozo\database\record
{
  /**
   * Gets a participant's value for this attribute
   * 
   * Warning, this method can throw exceptions when fetching values from the data manager
   * @param database\participant $db_participant
   * @return string
   */
  public function get_participant_value( $db_participant )
  {
    $data_manager = lib::create( 'business\data_manager' );
    return util::utf8_encode(
      0 === strpos( $this->code, 'participant.' ) ?
      $data_manager->get_participant_value( $db_participant, $this->code ) :
      $data_manager->get_value( $this->code )
    );
  }

  /**
   * Creates a attribute from an object
   * @param object $attribute
   * @param database\qnaire $db_qnaire The qnaire to associate the attribute to
   * @return database\attribute
   * @static
   */
  public static function create_from_object( $attribute, $db_qnaire )
  {
    $db_attribute = new static();
    $db_attribute->qnaire_id = $db_qnaire->id;
    $db_attribute->name = $attribute->name;
    $db_attribute->code = $attribute->code;
    $db_attribute->note = $attribute->note;
    $db_attribute->save();

    return $db_attribute;
  }
}
