<?php
/**
 * query.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\service\respondent\consent;
use cenozo\lib, cenozo\log, pine\util;

class query extends \cenozo\service\query
{
  /**
   * Extend parent method
   */
  public function get_leaf_parent_relationship()
  {
    $relationship_class_name = lib::get_class_name( 'database\relationship' );
    return $relationship_class_name::MANY_TO_MANY;
  }

  /**
   * Extend parent method
   */
  protected function get_record_count()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    return $respondent_class_name::count( $this->get_modifier() );
  }

  /**
   * Extend parent method
   */
  protected function get_record_list()
  {
    $respondent_class_name = lib::get_class_name( 'database\respondent' );
    return $respondent_class_name::select( $this->select, $this->get_modifier() );
  }

  /**
   * Adds the necessary joins to the modifier used by get_record_count() and get_record_list()
   */
  private function get_modifier()
  {
    $modifier = clone $this->modifier;

    $modifier->join( 'qnaire', 'respondent.qnaire_id', 'qnaire.id' );
    $modifier->join( 'qnaire_consent_type_confirm', 'qnaire.id', 'qnaire_consent_type_confirm.qnaire_id' );
    $modifier->join( 'consent_type', 'qnaire_consent_type_confirm.consent_type_id', 'consent_type.id' );
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'consent_type.id', '=', 'role_has_consent_type.consent_type_id', false );
    $join_mod->where( 'role_has_consent_type.role_id', '=', lib::create( 'business\session' )->get_role()->id );
    $modifier->join_modifier( 'role_has_consent_type', $join_mod, 'left' );
    $modifier->join( 'participant', 'respondent.participant_id', 'participant.id' );
    $join_mod = lib::create( 'database\modifier' );
    $join_mod->where( 'participant.id', '=', 'participant_last_consent.participant_id', false );
    $join_mod->where( 'consent_type.id', '=', 'participant_last_consent.consent_type_id', false );
    $modifier->join_modifier( 'participant_last_consent', $join_mod );
    $modifier->left_join( 'consent', 'participant_last_consent.consent_id', 'consent.id' );
    $modifier->where( 'respondent.id', '=', $this->get_parent_record()->id );

    return $modifier;
  }
}
