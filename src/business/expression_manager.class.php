<?php
/**
 * expression_manager.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business;
use cenozo\lib, cenozo\log, pine\util;

/**
 * Manages the preconditions defined in modules, pages, questions and question_options
 */
class expression_manager extends \cenozo\singleton
{
  /**
   * Constructor.
   * 
   * @param database\qnaire|database\response $record The questionnaire or response context to evaluate expressions
   * @throws exception\argument
   * @access protected
   */
  protected function __construct( $arguments )
  {
    $record = current( $arguments );

    if( is_a( $record, lib::get_class_name( 'database\qnaire' ) ) )
    {
      $this->db_qnaire = $record;
      $this->show_hidden = true;
      $this->db_response = NULL;
    }
    else if( is_a( $record, lib::get_class_name( 'database\response' ) ) )
    {
      $this->db_response = $record;
      $this->show_hidden = $this->db_response->show_hidden;
      $this->db_qnaire = $this->db_response->get_qnaire();
    }
    else throw lib::create( 'exception\argument', 'record', $record, __METHOD__ );
  }

  /**
   * Returns whether to show hidden text and preconditions
   * @return boolean
   * @access public
   */
  public function get_show_hidden()
  {
    return $this->show_hidden;
  }

  /**
   * Sets whether to show hidden text and preconditions
   * @param boolean $show
   * @access public
   */
  public function set_show_hidden( $show )
  {
    $this->show_hidden = $show;
  }

  /**
   * Converts hidden text codes into hidden/shown text
   * 
   * Text can be marked as "hidden" by enclosing it inside of double curly braces {{}}
   * This text will only appear when the "show_hidden" argument is included in the survey's URL
   * Also, text can be "reverse-hidden" by putting enclosing it inside of double curly braces with an exclamation {{!!}}
   * This text will only appear when the "show_hidden" argument is not included in the survey's URL
   * 
   * @param array $array An array referrence containing 'prompts' and 'popups' elements containing qnaire text
   */
  public function process_hidden_text( &$array )
  {
    $search1 = $this->show_hidden ? array( '/{{/', '/}}/' ) : '/{{.*?}}/s';
    $replace1 = $this->show_hidden ? array( '<span class="text-warning">', '</span>' ) : '';
    $search2 = !$this->show_hidden ? array( '/{{!/', '/!}}/' ) : '/{{!.*?!}}/s';
    $replace2 = !$this->show_hidden ? array( '<span class="text-warning">', '</span>' ) : '';
    foreach( $array as $key => $value )
      if( false !== strpos( $key, 'prompts' ) || false !== strpos( $key, 'popups' ) )
        $array[$key] = preg_replace( $search1, $replace1, preg_replace( $search2, $replace2, $value ) );
  }

  /**
   * Validates a precondition making sure the syntax is correct
   * 
   * This method is used when changing a qnaire element's precondition to make sure that it is valid.
   * 
   * @param string $precondition The precondition string to evaluate
   * @return string
   * @throws exception\runtime
   */
  public function validate( $precondition )
  {
    try { $this->evaluate( $precondition ); }
    catch( \cenozo\exception\runtime $e )
    {
      return sprintf( "%s\n\n%s", $e->get_raw_message(), $e->get_previous()->get_raw_message() );
    }
    return NULL;
  }

  /**
   * Evaluates a precondition
   * 
   * @param string $precondition The precondition string to evaluate
   * @return string
   * @throws exception\runtime
   */
  public function evaluate( $precondition )
  {
    $compiled = $this->compile( $precondition );
    try
    {
      $response = is_null( $this->db_response ) ? true : eval( sprintf( 'return (%s);', $compiled ) );
    }
    catch( \ParseError $e )
    {
      throw lib::create( 'exception\runtime',
        sprintf(
          "An error in a precondition has been detected:\n  Expression: %s\n  Compiled: %s\n  Error: %s",
          $precondition,
          $compiled,
          $e->getMessage()
        ),
        __METHOD__
      );
    }

    return $response;
  }

  /**
   * Converts a string into an expression that can be natively evaluated by PHP
   * 
   * values:
   *   @NAME@ (response attribute)
   *   $NAME$ (question value or stage: true if the stage is either skipped or complete)
   *   $NAME.empty()$ (true if question hasn't been answered, false if it has)
   *   $NAME.dkna()$ (true if a question's answer is don't know or no answer)
   *   $NAME.refuse()$ (true if a question is refused)
   *   $NAME.status()$ (gets a stage's current status)
   *   $respondent.token$ (gets the respondent's token)
   *   $respondent.language$ (gets the current language code)
   *   showhidden true if showing hidden elements (launched by phone) false if not (launched by web)
   *   current_year The current year in YYYY format
   *   current_month The current month in MM format
   *   current_day The current day in DD format
   *   today The current date in YYYY-MM-DD format
   *   null (when a question has no answer - it's skipped)
   *   true|false (boolean)
   *   123 (number)
   *   "string" (may be delimited by ', " or `

   * lookup:
   *   @NAME.indicator("LOOKUP","INDICATOR")@ (true if attribute has a particular indicator for the given lookup)
   *   $NAME.indicator("LOOKUP","INDICATOR")$ (true if answer has a particular indicator for the given lookup)

   * lists:
   *   $NAME:OPTION$ (always true if it is selected, false if not)
   *   $NAME:count()$ (always a number representing how many options are selected)
   *   $NAME.extra(OPTION)$ Used to get the extra value associated with a selected option

   * operators:
   *   -  function(x,y) where x,y must be number, A:NAME, Q:NAME(type=number)
   *   +  function(x,y) where x,y must be number, A:NAME, Q:NAME(type=number)
   *   *  function(x,y) where x,y must be number, A:NAME, Q:NAME(type=number)
   *   /  function(x,y) where x,y must be number, A:NAME, Q:NAME(type=number)
   *   <  function(x,y) where x,y must be number, string, A:NAME, Q:NAME(type=number|string|text)
   *   <= function(x,y) where x,y must be number, string, A:NAME, Q:NAME(type=number|string|text)
   *   >  function(x,y) where x,y must be number, string, A:NAME, Q:NAME(type=number|string|text)
   *   >= function(x,y) where x,y must be number, string, A:NAME, Q:NAME(type=number|string|text)
   *   != function(x,y) where x,y can be any non comparison
   *   == function(x,y) where x,y can be any non comparison
   *   ~= function(x,y) where x,y must be number, string, A:NAME, Q:NAME(type=number|string|text)
   *   && function(x,y) where x,y must be boolean, Q:NAME(type=boolean), comparison
   *   || function(x,y) where x,y must be boolean, Q:NAME(type=boolean), comparison
   *   ? function(x,y) where x must be boolean and y can be any non comparison
   *   : function(x,y) where x,y can be any non comparison
   *   ( must have same number opening as closing
   *   ) must have same number opening as closing
   * 
   * @param string $precondition The precondition to compile
   * @param database\question|database\question_option $override_question_object A question or option to leave uncompiled
   * @return string
   */
  public function compile( $precondition, $override_question_object = NULL )
  {
    // if an override object is proided then make sure it's either a question or question_option
    if( !is_null( $override_question_object ) &&
        !is_a( $override_question_object, lib::get_class_name( 'database\question' ) ) &&
        !is_a( $override_question_object, lib::get_class_name( 'database\question_option' ) ) )
    {
      throw lib::create( 'exception\argument',
        'override_question_object',
        $override_question_object,
        __METHOD__
      );
    }

    $this->reset();

    // empty preconditions always pass
    if( is_null( $precondition ) ) return true; 

    $compiled = '';

    try
    {
      // loop through the precondition one character at a time
      foreach( str_split( strtolower( $precondition ) ) as $index => $char )
      {
        $process_char = true;

        if( 'string' == $this->active_term )
        { // ignore characters until the string has closed
          if( $this->quote != $char ) $this->term .= $char;
          else $compiled .= $this->process_string();
          $process_char = false;
        }
        else if( 'number' == $this->active_term )
        {
          if( preg_match( '/[0-9.]/', $char ) )
          {
            $this->term .= $char;
            $process_char = false;
          }
          else
          {
            $compiled .= $this->process_number();
            $process_char = true;
          }
        }
        else if( 'constant' == $this->active_term )
        {
          if( preg_match( '/[a-z_]/', $char ) )
          {
            $this->term .= $char;
            $process_char = false;
          }
          else
          {
            $compiled .= $this->process_constant();
            $process_char = true;
          }
        }
        else if( 'operator' == $this->active_term )
        {
          if( preg_match( '/[-+*\/<>!=~&|]/', $char ) )
          {
            $this->term .= $char;
            $process_char = false;
          }
          else
          {
            $compiled .= $this->process_operator();
            $process_char = true;
          }
        }
        else if( 'attribute' == $this->active_term )
        {
          if( '@' != $char ) $this->term .= $char;
          else $compiled .= $this->process_attribute();
          $process_char = false;
        }
        else if( 'variable' == $this->active_term )
        {
          if( '$' != $char ) $this->term .= $char;
          else
          {
            $question = true;

            // determine if this is a respondent, stage or question variable
            if( preg_match( '/^respondent\.(.+)$/', $this->term, $matches ) )
            {
              $compiled .= $this->process_response_value( $matches[1] );
              $question = false;
            }
            else
            {
              if( $this->db_qnaire->stages && preg_match( '/^([^.]+)(\.([^.]+))?$/', $this->term, $matches ) )
              {
                // see if the first part of the argument is a stage name
                $stage_name = $matches[1];

                $db_stage = $stage_class_name::get_unique_record(
                  array( 'qnaire_id', 'name' ),
                  array( $this->db_qnaire->id, $matches[1] )
                );
                if( !is_null( $db_stage ) )
                {
                  $compiled .= $this->process_stage(
                    $db_stage,
                    array_key_exists( 3, $matches ) ? $matches[3] : NULL
                  );
                  $question = false;
                }
              }

              if( $question ) $compiled .= $this->process_question( $override_question_object );
            }
          }
          $process_char = false;
        }

        if( $process_char ) $compiled .= $this->process_character( $char );
      }

      if( 0 != $this->open_bracket ) 
      {
        throw lib::create( 'exception\runtime',
          sprintf(
            'There are %d too many %s brackets in the expression',
            abs( $this->open_bracket ),
            0 < $this->open_bracket ? 'opening' : 'closing'
          ),
          __METHOD__
        );
      }
      else if( 'operator' == $this->active_term )
        throw lib::create( 'exception\runtime', 'Expecting expression after operator', __METHOD__ );
      else if( 'string' == $this->active_term )
        throw lib::create( 'exception\runtime', 'Expression has an unclosed string', __METHOD__ );
      else if( 'attribute' == $this->active_term )
        throw lib::create( 'exception\runtime', 'Expression has an unclosed attribute', __METHOD__ );
      else if( 'variable' == $this->active_term )
        throw lib::create( 'exception\runtime', 'Expression has an unclosed variable', __METHOD__ );
      else if( 'number' == $this->active_term ) $compiled .= $this->process_number();
      else if( 'constant' == $this->active_term ) $compiled .= $this->process_constant();
      else if( 'operator' == $this->active_term ) $compiled .= $this->process_operator();
    }
    catch( \cenozo\exception\runtime $e )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Error while evaluating expression:%s', "\n".$precondition ),
        __METHOD__,
        $e
      );
    }

    // make comparisons to boolean values exact (convert == to === and != to !==)
    $compiled = strtolower( $compiled );
    $compiled = preg_replace( '/ (==|!=) (true|false)/', ' $1= $2', $compiled );
    $compiled = preg_replace( '/(true|false) (==|!=) /', '$1 $2= ', $compiled );

    return $compiled;
  }

  /**
   * Resets the manager so that it is prepared to process another precondition
   */
  private function reset()
  {
    $this->quote = NULL;
    $this->active_term = NULL;
    $this->term = NULL;
    $this->last_term = NULL;
    $this->open_bracket = 0;
  }

  /**
   * Processes the current term as a string
   * @return string
   */
  private function process_string()
  {
    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'String found bug expecting an operator', __METHOD__ );

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $this->active_term;
    $this->active_term = NULL;
    $this->quote = NULL;

    return sprintf( '"%s"', addslashes( $this->term ) );
  }

  /**
   * Processes the current term as a number
   * @return string
   */
  private function process_number()
  {
    // test that the number is valid
    if( 1 < substr_count( $this->term, '.' ) )
      throw lib::create( 'exception\runtime', sprintf( 'Invalid number "%s"', $this->term ), __METHOD__ );

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Number found but expecting an operator', __METHOD__ );

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = $this->active_term;
    $this->active_term = NULL;

    return (string)(float)$this->term;
  }

  /**
   * Processes the current term as a constant
   * @return string
   */
  private function process_constant()
  {
    $type = NULL;
    if( 'null' == $this->term ) $type = 'null';
    else if( in_array( $this->term, ['true', 'false', 'showhidden'] ) ) $type = 'boolean';
    else if( in_array( $this->term, ['current_year', 'current_month', 'current_day', 'today'] ) )
      $type = 'string';

    if( is_null( $type ) )
      throw lib::create( 'exception\runtime', sprintf( 'Invalid constant "%s"', $this->term ), __METHOD__ );

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Constant found but expecting an operator', __METHOD__ );

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $type;
    $this->active_term = NULL;

    if( 'showhidden' == $this->term ) return $this->show_hidden ? 'true' : 'false';
    if( 'today' == $this->term ) return util::get_datetime_object()->format( 'YYYY-MM-DD' );
    return $this->term;
  }

  /**
   * Processes the current term as an operator
   * @return string
   */
  private function process_operator()
  {
    if( in_array( $this->term, ['-', '+', '*', '/'] ) )
    { // mathematical operator
      if( !in_array( $this->last_term, ['number', 'boolean', 'attribute'] ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Expecting a number or attribute before "%s"', $this->term
        ), __METHOD__ );
      }
    }
    else if( in_array( $this->term, ['<', '<=', '>', '>='] ) )
    { // quantity operator
      // Note that boolean was added to the acceptible list since attributes are sometimes interpreted
      // as boolean when they come immediately after a logical operator.  This isn't ideal, but quantity
      // operators can work with boolean values without an error so it's the best compromise.
      if( !in_array( $this->last_term, ['boolean', 'number', 'string', 'attribute'] ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Expecting a number, string or attribute before "%s"', $this->term
        ), __METHOD__ );
      }
    }
    else if( in_array( $this->term, ['==', '!=', '?', ':'] ) )
    { // equality operator
      if( is_null( $this->last_term ) || 'operator' == $this->last_term )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Expecting an expression before "%s"', $this->term
        ), __METHOD__ );
      }
    }
    else if( '~=' == $this->term )
    { // sql LIKE operator
      if( !in_array( $this->last_term, ['number', 'string', 'attribute'] ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Expecting a number, string or attribute before "%s"', $this->term
        ), __METHOD__ );
      }
    }
    else if( in_array( $this->term, ['&&', '||'] ) )
    { // logical operator but allow in any circumstance because we don't track compounded expressions
    }
    else throw lib::create( 'exception\runtime', sprintf( 'Invalid operator "%s"', $this->term ), __METHOD__ );

    $this->last_term = $this->active_term;
    $this->active_term = NULL;

    return sprintf( ' %s ', $this->term );
  }

  /**
   * Processes the current term as an attribute
   * @return string
   */
  private function process_attribute()
  {
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );

    $db_lookup = NULL;
    $db_indicator = NULL;
    $special_function = NULL;

    // attributes may have the indicator function
    if( preg_match( '/([^.]+)\.indicator\( *"([^"]+)" *, *"([^"]+)" *\)/', $this->term, $matches ) )
    {
      $special_function = 'indicator';
      $attribute_name = $matches[1];

      $db_lookup = $lookup_class_name::get_unique_record( 'name', $matches[2] );

      if( is_null( $db_lookup ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'No lookup named "%s" found.', $matches[2] ),
          __METHOD__
        );
      }

      $db_indicator = $indicator_class_name::get_unique_record(
        array( 'lookup_id', 'name' ),
        array( $db_lookup->id, $matches[3] )
      );

      if( is_null( $db_indicator ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'No indicator named "%s" found for the lookup "%s".', $matches[3], $db_lookup->name ),
          __METHOD__
        );
      }
    }
    else // attributes are defined by name
    {
      $attribute_name = $this->term;
    }

    // make sure the attribute exists in the qnaire
    $db_attribute = $attribute_class_name::get_unique_record(
      array( 'qnaire_id', 'name' ),
      array( $this->db_qnaire->id, $attribute_name )
    );

    if( is_null( $db_attribute ) )
      throw lib::create( 'exception\runtime', sprintf( 'Invalid attribute "%s"', $attribute_name ), __METHOD__ );

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Attribute found but expecting an operator', __METHOD__ );

    // if a response was provided replace the term with the attribute's value
    $compiled = sprintf( '%%%s%%', $attribute_name );
    if( !is_null( $this->db_response ) )
    {
      $this->db_response_attribute = $response_attribute_class_name::get_unique_record(
        array( 'response_id', 'attribute_id' ),
        array( $this->db_response->id, $db_attribute->id )
      );

      if( 'indicator' == $special_function )
      {
        $compiled = 'false';

        $db_lookup_item = $lookup_item_class_name::get_unique_record(
          array( 'lookup_id', 'identifier' ),
          array( $db_lookup->id, $this->db_response_attribute->value )
        );

        if( !is_null( $db_lookup_item ) )
        {
          $lookup_item_mod = lib::create( 'database\modifier' );
          $lookup_item_mod->where( 'lookup_item.id', '=', $db_lookup_item->id );
          if( $db_indicator->get_lookup_item_count( $lookup_item_mod ) ) $compiled = 'true';
        }
      }
      else
      {
        $compiled = is_null( $this->db_response_attribute->value )
                  ? 'null'
                  : sprintf( '%s', addslashes( $this->db_response_attribute->value ) );

        // add quotes if required
        if( 'null' != $compiled &&
            !util::string_matches_int( $compiled ) &&
            !util::string_matches_float( $compiled ) ) $compiled = sprintf( "'%s'", $compiled );
      }
    }

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $this->active_term;
    $this->active_term = NULL;

    return $compiled;
  }

  /**
   * Processes the current term as a respondent variable
   * @param string $variable The selected respondent variable (either 'token', or 'language')
   * @return string
   */
  private function process_response_value( $variable )
  {
    if( 'token' == $variable )
    {
      $compiled = is_null( $this->db_response ) ? '' : $this->db_response->get_respondent()->token;
    }
    else if( 'language' == $variable )
    {
      $compiled = is_null( $this->db_response )
                ? $this->db_qnaire->get_base_language()->code
                : $this->db_response->get_language()->code;
    }
    else
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Invalid expression, "%s"', $this->term ),
        __METHOD__
      );
    }

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $this->active_term;

    return $compiled;
  }

  /**
   * Processes the current term as a question
   * @param database\question|database\question_option $override_question_object A question or option to leave uncompiled
   * @return string
   */
  private function process_question( $override_question_object = NULL )
  {
    $db_override_question = NULL;
    $db_override_question_option = NULL;
    if( !is_null( $override_question_object ) )
    {
      if( is_a( $override_question_object, lib::get_class_name( 'database\question' ) ) )
      {
        $db_override_question = $override_question_object;
      }
      else if( is_a( $override_question_object, lib::get_class_name( 'database\question_option' ) ) )
      {
        $db_override_question_option = $override_question_object;
      }
      else
      {
        throw lib::create( 'exception\argument',
          'override_question_object',
          $override_question_object,
          __METHOD__
        );
      }
    }
    if( !is_null( $db_override_question_option ) )
      $db_override_question = $db_override_question_option->get_question();

    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    // figure out the question, and possibly the question option referred to by this term
    $db_question = NULL;
    $db_question_option = NULL;
    $db_lookup = NULL;
    $db_indicator = NULL;
    $special_function = NULL;

    // question-options and certain functions are defined by question:question_option
    if( preg_match( '/([^.:]+)([.:])([^.:]+)/', $this->term, $matches ) )
    {
      if( 4 != count( $matches ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid question "%s"', $this->term ), __METHOD__ );

      $db_question = $this->db_qnaire->get_question( $matches[1] );
      if( is_null( $db_question ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'No question name "%s" found.', $matches[1] ),
          __METHOD__
        );
      }

      if( '.' == $matches[2] )
      {
        if( 'extra(' == substr( $matches[3], 0, 6 ) )
        {
          $special_function = 'extra';

          if( !preg_match( '/extra\(([^)]+)\)/', $matches[3], $sub_matches ) )
            throw lib::create( 'exception\runtime', sprintf( 'Invalid syntax "%s"', $matches[3] ), __METHOD__ );

          $db_question_option = $question_option_class_name::get_unique_record(
            array( 'question_id', 'name' ),
            array( $db_question->id, $sub_matches[1] )
          );
          if( is_null( $db_question_option ) )
          {
            throw lib::create( 'exception\runtime',
              sprintf( 'Invalid question option "%s" for question "%s"', $sub_matches[1], $matches[1] ),
              __METHOD__
            );
          }
          else if( is_null( $db_question_option->extra ) )
          {
            throw lib::create( 'exception\runtime',
              sprintf(
                'Question option "%s" for question "%s" does not have extra values.',
                $sub_matches[1],
                $matches[1]
              ),
              __METHOD__
            );
          }
        }
        else if( 'indicator(' == substr( $matches[3], 0, 10 ) )
        {
          $special_function = 'indicator';

          if( !preg_match( '/indicator\( *"([^"]+)" *, *"([^"]+)" *\)/', $matches[3], $sub_matches ) )
            throw lib::create( 'exception\runtime', sprintf( 'Invalid syntax "%s"', $matches[3] ), __METHOD__ );

          $db_lookup = $lookup_class_name::get_unique_record( 'name', $sub_matches[1] );

          if( is_null( $db_lookup ) )
          {
            throw lib::create( 'exception\runtime',
              sprintf( 'No lookup named "%s" found.', $sub_matches[1] ),
              __METHOD__
            );
          }

          $db_indicator = $indicator_class_name::get_unique_record(
            array( 'lookup_id', 'name' ),
            array( $db_lookup->id, $sub_matches[2] )
          );

          if( is_null( $db_indicator ) )
          {
            throw lib::create( 'exception\runtime',
              sprintf( 'No indicator named "%s" found for the lookup "%s".', $sub_matches[2], $db_lookup->name ),
              __METHOD__
            );
          }
        }
        else if( in_array( $matches[3], ['empty()', 'dkna()', 'refuse()'] ) )
        {
          $special_function = substr( $matches[3], 0, -2 );
        }
        else
        {
          throw lib::create( 'exception\runtime', sprintf( 'No such function "%s"', $matches[3] ), __METHOD__ );
        }
      }
      else if( ':' == $matches[2] )
      {
        if( 'count()' == $matches[3] )
        {
          // return how many options are selected
          $special_function = 'count';
        }
        else
        {
          $db_question_option = $question_option_class_name::get_unique_record(
            array( 'question_id', 'name' ),
            array( $db_question->id, $matches[3] )
          );
          if( is_null( $db_question_option ) )
          {
            throw lib::create( 'exception\runtime',
              sprintf( 'No question option name "%s" found for question "%s".', $matches[3], $matches[1] ),
              __METHOD__
            );
          }
        }
      }
    }
    else // questions are defined by name
    {
      $db_question = $this->db_qnaire->get_question( $this->term );
      if( is_null( $db_question ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'No question name "%s" found.', $this->term ),
          __METHOD__
        );
      }

      if( 'list' == $db_question->type )
      {
        throw lib::create( 'exception\runtime',
          sprintf(
            'Question "%s" is a list type so you must reference a question option using '.
            'the QUESTION:OPTION format.',
            $this->term
          ),
          __METHOD__
        );
      }

      if( in_array( $db_question->type, ['comment', 'device', 'text'] ) )
      {
        throw lib::create( 'exception\runtime',
          sprintf( 'Cannot use question type "%s"', $db_question->type ),
          __METHOD__
        );
      }
    }

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Question found but expecting an operator', __METHOD__ );

    // if a response was provided and there is no override then replace the term with the attribute's value
    $compiled = sprintf( '$%s$', $this->term );
    if( !is_null( $this->db_response ) && (
      // make sure the question isn't overridden
      is_null( $db_override_question ) ||
      $db_question->page_id != $db_override_question->page_id ||
      $db_question->rank >= $db_override_question->rank
    ) && (
      // make sure the question_option isn't overridden
      is_null( $db_question_option ) ||
      is_null( $db_override_question_option ) ||
      $db_question_option->question_id != $db_override_question_option->question_id ||
      $db_question_option->rank >= $db_override_question_option->rank
    ) )
    {
      $db_answer = $answer_class_name::get_unique_record(
        array( 'response_id', 'question_id' ),
        array( $this->db_response->id, $db_question->id )
      );
      $value = is_null( $db_answer ) ? NULL : util::json_decode( $db_answer->value );
      $dkna = is_object( $value ) && property_exists( $value, 'dkna' ) && $value->dkna;
      $refuse = is_object( $value ) && property_exists( $value, 'refuse' ) && $value->refuse;

      if( 'empty' == $special_function ) $compiled = is_null( $value ) ? 'true' : 'false';
      else if( 'dkna' == $special_function ) $compiled = $dkna ? 'true' : 'false';
      else if( 'refuse' == $special_function ) $compiled = $refuse ? 'true' : 'false';
      else if( 'count' == $special_function ) { $compiled = is_array( $value ) ? count( $value ) : 0; }
      else if( is_null( $value ) || $dkna || $refuse ) $compiled = 'NULL';
      else if( !is_null( $db_question_option ) )
      {
        // set whether or not the response checked off the option, or provide extra data if requested
        $compiled = 'extra' == $special_function ? 'NULL' : 'false';
        if( is_array( $value ) )
        {
          foreach( $value as $selected_option )
          {
            if( 'extra' == $special_function )
            {
              if( ( is_object( $selected_option ) && $db_question_option->id == $selected_option->id ) )
              {
                $compiled = 'number' == $db_question_option->extra
                          ? $selected_option->value
                          : sprintf( '"%s"', str_replace( '"', '\"', $selected_option->value ) );
              }
            }
            else

            if( ( is_object( $selected_option ) && $db_question_option->id == $selected_option->id ) ||
                ( !is_object( $selected_option ) && $db_question_option->id == $selected_option ) )
            {
              $compiled = 'true';
              break;
            }
          }
        }
      }
      else if( 'indicator' == $special_function )
      {
        $compiled = 'false';

        $db_lookup_item = $lookup_item_class_name::get_unique_record(
          array( 'lookup_id', 'identifier' ),
          array( $db_lookup->id, $value )
        );

        if( !is_null( $db_lookup_item ) )
        {
          $lookup_item_mod = lib::create( 'database\modifier' );
          $lookup_item_mod->where( 'lookup_item.id', '=', $db_lookup_item->id );
          if( $db_indicator->get_lookup_item_count( $lookup_item_mod ) ) $compiled = 'true';
        }
      }
      else
      {
        if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
        else if( 'string' == $db_question->type )
          $compiled = sprintf( "'%s'", str_replace( "'", "\\'", $value ) );
        else $compiled = $value;
      }
    }

    // determine the last term
    if( is_null( $special_function ) )
      $this->last_term = !is_null( $db_question_option ) ? 'boolean' : $db_question->type;
    else if( 'extra' == $special_function ) $this->last_term = $db_question_option->extra;
    else $this->last_term = 'count' == $special_function ? 'number' : 'boolean';

    $this->active_term = NULL;

    return $compiled;
  }

  /**
   * Processes the current term as a stage
   * @param database\stage $db_stage The selected stage
   * @param string $special_function The stage function (can either be NULL or 'status()')
   * @return string
   */
  private function process_stage( $db_stage, $special_function = NULL )
  {
    $response_stage_class_name = lib::get_class_name( 'database\response_stage' );

    if( !$this->db_qnaire->stages )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Expression references stage "%s" in qnaire without stages.', $this->term ),
        __METHOD__
      );
    }

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Stage found but expecting an operator', __METHOD__ );

    // determine the last term
    $this->last_term = is_null( $special_function ) ? 'boolean' : 'string';

    $compiled = sprintf( '$%s$', $this->term );
    if( !is_null( $this->db_response ) )
    {
      $db_response_stage = $response_stage_class_name::get_unique_record(
        array( 'response_id', 'stage_id' ),
        array( $this->db_response->id, $db_stage->id )
      );

      $compiled = 'status()' == $special_function
                ? sprintf( "'%s'", $db_response_stage->status )
                : ( in_array( $db_response_stage->status, ['skipped', 'completed'] ) ? 'true' : 'false' );
    }

    $this->active_term = NULL;

    return $compiled;
  }

  /**
   * Used by the compile() method one character at a time
   * @param string $char
   * @return string
   */
  private function process_character( $char )
  {
    $compiled = '';

    if( '(' == $char || ')' == $char )
    {
      $compiled .= $char;
      $this->open_bracket += '(' == $char ? 1 : -1;
      if( 0 > $this->open_bracket )
      {
        throw lib::create( 'exception\runtime',
          'Found closing bracket without a matching opening bracket.',
          __METHOD__
        );
      }
    }
    else if( '{' == $char || '}' == $char )
    {
      throw lib::create( 'exception\runtime', 'Curly braces, { and }, are not allowed.', __METHOD__ );
    }
    else if( in_array( $char, ["'", '"', '`'] ) )
    {
      $this->active_term = 'string';
      $this->quote = $char;
    }
    else if( ' ' == $char ) ; // ignore spaces
    else if( '@' == $char ) $this->active_term = 'attribute';
    else if( '$' == $char ) $this->active_term = 'variable';
    else if( preg_match( '/[0-9.]/', $char ) ) $this->active_term = 'number';
    else if( preg_match( '/[a-z_]/', $char ) ) $this->active_term = 'constant';
    else if( preg_match( '/[-+*\/<>!=~&|?:]/', $char ) ) $this->active_term = 'operator';

    $this->term = in_array( $char, ['(', ')', "'", '"', '`', ' ', '@', '$'] ) ? '' : $char;
    return $compiled;
  }

  /**
   * Used by the manager to evaluate experssions within the context of a specific qnaire
   * @var database\qnaire db_qnaire
   */
  private $db_qnaire = NULL;

  /**
   * Used by the manager to evaluate experssions within the context of a specific response
   * @var database\response db_response
   */
  private $db_response = NULL;

  /**
   * What type of quote was used to open the string
   * @var string $quote
   */
  private $quote;
  
  /**
   * Stores whether some element is being declared (string, number, attribute, variable, constant or operator)
   * @var string $active_term
   */
  private $active_term;
  
  /**
   * Stores the active term as it is read
   * @var string $term
   */
  private $term;

  /**
   * Stores the previous active term
   * @var string $last_term
   */
  private $last_term;

  /**
   * Counts how many levels of brackets the expression is currently in
   * @var integer $open_bracket
   */
  private $open_bracket;

  /**
   * Determines whether to show hidden elements (used by phone versions of the qnaire vs web versions)
   * @var boolean $show_hidden
   */
  private $show_hidden = false;
}
