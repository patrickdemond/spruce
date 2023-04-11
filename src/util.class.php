<?php
/**
 * util.class.php
 */

namespace pine;
use cenozo\lib, cenozo\log;

/**
 * util: utility class of static methods
 *
 * Extends cenozo's util class with additional functionality.
 */
class util extends \cenozo\util
{
  public static function prepare_respondent_read_objects( $select, $modifier )
  {
    if( $select->has_column( 'page_progress' ) )
    {
      $select->add_column(
        'CONCAT( '.
          'IF( '.
            'response.submitted, '.
            'qnaire.total_pages, '.
            'IF( response.page_id IS NULL, 0, response.current_page_rank ) '.
          '), '.
          '" of ", qnaire.total_pages '.
        ')',
        'page_progress',
        false
      );
    }

    if( $select->has_column( 'introduction_list' ) )
    {
      // join to the introduction descriptions
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'qnaire.id', '=', 'introduction.qnaire_id', false );
      $join_mod->where( 'introduction.type', '=', 'introduction' );
      $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'introduction' );
      $modifier->join(
        'language',
        'introduction.language_id',
        'introduction_language.id',
        '',
        'introduction_language'
      );
      $select->add_column(
        'GROUP_CONCAT( '.
          'DISTINCT CONCAT_WS( "`", introduction_language.code, IFNULL( introduction.value, "" ) ) '.
          'SEPARATOR "`" '.
        ')',
        'introduction_list',
        false
      );

      if( !$modifier->has_group( 'qnaire.id' ) ) $modifier->group( 'qnaire.id' );
    }

    if( $select->has_column( 'conclusion_list' ) )
    {
      // join to the conclusion descriptions
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'qnaire.id', '=', 'conclusion.qnaire_id', false );
      $join_mod->where( 'conclusion.type', '=', 'conclusion' );
      $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'conclusion' );
      $modifier->join( 'language', 'conclusion.language_id', 'conclusion_language.id', '', 'conclusion_language' );
      $select->add_column(
        'GROUP_CONCAT( '.
          'DISTINCT CONCAT_WS( "`", conclusion_language.code, IFNULL( conclusion.value, "" ) ) '.
          'SEPARATOR "`" '.
        ')',
        'conclusion_list',
        false
      );

      if( !$modifier->has_group( 'qnaire.id' ) ) $modifier->group( 'qnaire.id' );
    }

    if( $select->has_column( 'closed_list' ) )
    {
      // join to the closed descriptions
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'qnaire.id', '=', 'closed.qnaire_id', false );
      $join_mod->where( 'closed.type', '=', 'closed' );
      $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'closed' );
      $modifier->join( 'language', 'closed.language_id', 'closed_language.id', '', 'closed_language' );
      $select->add_column(
        'GROUP_CONCAT( '.
          'DISTINCT CONCAT_WS( "`", closed_language.code, IFNULL( closed.value, "" ) ) '.
          'SEPARATOR "`" '.
        ')',
        'closed_list',
        false
      );

      if( !$modifier->has_group( 'qnaire.id' ) ) $modifier->group( 'qnaire.id' );
    }

    if( $select->has_column( 'problem_prompt_list' ) )
    {
      // join to the problem_prompt descriptions
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'qnaire.id', '=', 'problem_prompt.qnaire_id', false );
      $join_mod->where( 'problem_prompt.type', '=', 'problem prompt' );
      $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'problem_prompt' );
      $modifier->join(
        'language',
        'problem_prompt.language_id',
        'problem_prompt_language.id',
        '',
        'problem_prompt_language'
      );
      $select->add_column(
        'GROUP_CONCAT( '.
          'DISTINCT CONCAT_WS( "`", problem_prompt_language.code, IFNULL( problem_prompt.value, "" ) ) '.
          'SEPARATOR "`" '.
        ')',
        'problem_prompt_list',
        false
      );

      if( !$modifier->has_group( 'qnaire.id' ) ) $modifier->group( 'qnaire.id' );
    }

    if( $select->has_column( 'problem_confirm_list' ) )
    {
      // join to the problem_confirm descriptions
      $join_mod = lib::create( 'database\modifier' );
      $join_mod->where( 'qnaire.id', '=', 'problem_confirm.qnaire_id', false );
      $join_mod->where( 'problem_confirm.type', '=', 'problem confirm' );
      $modifier->join_modifier( 'qnaire_description', $join_mod, '', 'problem_confirm' );
      $modifier->join(
        'language',
        'problem_confirm.language_id',
        'problem_confirm_language.id',
        '',
        'problem_confirm_language'
      );
      $select->add_column(
        'GROUP_CONCAT( '.
          'DISTINCT CONCAT_WS( "`", problem_confirm_language.code, IFNULL( problem_confirm.value, "" ) ) '.
          'SEPARATOR "`" '.
        ')',
        'problem_confirm_list',
        false
      );

      if( !$modifier->has_group( 'qnaire.id' ) ) $modifier->group( 'qnaire.id' );
    }
  }
}
