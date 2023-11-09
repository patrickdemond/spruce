<?php
/**
 * qnaire_document.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\database;
use cenozo\lib, cenozo\log, pine\util;

/**
 * qnaire_document: record
 */
class qnaire_document extends \cenozo\database\record
{
  /**
   * 
  /**
   * Creates a qnaire_document from an object
   * @param object $qnaire_document
   * @param database\qnaire $db_qnaire The qnaire to associate the qnaire_document to
   * @return database\qnaire_document
   * @static
   */
  public static function create_from_object( $qnaire_document, $db_qnaire )
  {
    $db_qnaire_document = new static();
    $db_qnaire_document->qnaire_id = $db_qnaire->id;
    $db_qnaire_document->name = $qnaire_document->name;
    $db_qnaire_document->data = $qnaire_document->data;
    $db_qnaire_document->save();

    return $db_qnaire_document;
  }


  /**
   * Applies a patch file to the qnaire_document and returns an object containing all elements which are affected by the patch
   * @param stdObject $patch_object An object containing all (nested) parameters to change
   * @param boolean $apply Whether to apply or evaluate the patch
   */
  public function process_patch( $patch_object, $apply = false )
  {
    $difference_list = [];

    foreach( $patch_object as $property => $value )
    {
      if( $patch_object->$property != $this->$property )
      {
        if( $apply ) $this->$property = $patch_object->$property;
        else $difference_list[$property] = $patch_object->$property;
      }
    }

    if( $apply )
    {
      $this->save();
      return null;
    }
    else return 0 == count( $difference_list ) ? NULL : (object)$difference_list;
  }
}
