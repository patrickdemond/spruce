<?php
/**
 * base_description.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Performs operations which effect how description modules are used in a service
 */
abstract class base_description_module extends \cenozo\service\module
{
  /**
   * Extend parent method
   */
  public function prepare_read( $select, $modifier )
  {
    parent::prepare_read( $select, $modifier );

    $subject = $this->get_subject();
    $parent_subject = str_replace( '_description', '', $subject );

    $modifier->join( 'language', sprintf( '%s.language_id', $subject ), 'language.id' );
    $modifier->join(
      $parent_subject,
      sprintf( '%s.%s_id', $subject, $parent_subject ),
      sprintf( '%s.id', $parent_subject )
    );

    $db_description = $this->get_resource();
    if( !is_null( $db_description ) )
    {
      if( $select->has_column( 'previous_description_id' ) || $select->has_column( 'next_description_id' ) )
      {
        $get_parent_function = sprintf( 'get_%s', $parent_subject );
        $get_description_list_function = sprintf( 'get_%s_list', $subject );

        $parent_record = $db_description->$get_parent_function();
        $description_sel = lib::create( 'database\select' );
        $description_sel->add_column( 'id' );
        $description_mod = lib::create( 'database\modifier' );
        $description_mod->join( 'language', sprintf( '%s.language_id', $subject ), 'language.id' );
        $description_mod->order( 'language.code' );
        $description_list = $parent_record->$get_description_list_function( $description_sel, $description_mod );

        // loop until we find the current description and get the previous/next descriptions from there
        foreach( $description_list as $index => $description )
        {
          if( $description['id'] == $db_description->id )
          {
            $select->add_constant(
              array_key_exists( $index-1, $description_list ) ? $description_list[$index-1]['id'] : NULL,
              'previous_description_id',
              'integer'
            );
            $select->add_constant(
              array_key_exists( $index+1, $description_list ) ? $description_list[$index+1]['id'] : NULL,
              'next_description_id',
              'integer'
            );
            break;
          }
        }
      }
    }
  }
}
