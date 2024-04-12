<?php
/**
 * expression_manager.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace pine\business;
use cenozo\lib, cenozo\log, pine\util, \Flow\JSONPath\JSONPath;

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
    $search1 = $this->show_hidden ? array( '/{{/', '/}}/' ) : '/{{.*?}}/s'; // s = include newlines in .*
    $replace1 = $this->show_hidden ? array( '<span class="text-warning">', '</span>' ) : '';
    $search2 = !$this->show_hidden ? array( '/{{!/', '/!}}/' ) : '/{{!.*?!}}/s'; // s = include newlines in .*
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
   * Evaluates an expression
   * 
   * @param string $expression The expresssion string to evaluate
   * @return string
   * @throws exception\runtime
   */
  public function evaluate( $expression )
  {
    $compiled = $this->compile( $expression );
    try
    {
      $response = is_null( $this->db_response ) ? true : eval( sprintf( 'return (%s);', $compiled ) );
    }
    catch( \ParseError $e )
    {
      throw lib::create( 'exception\runtime',
        sprintf(
          "An error in an expresssion has been detected:\n  Expression: %s\n  Compiled: %s\n  Error: %s",
          $expression,
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
   *   $NAME$ (question value)
   *   $NAME.empty()$ (true if question hasn't been answered, false if it has)
   *   $NAME.not_empty()$ (true if question has been answered, false if it hasn't)
   *   $NAME.dkna()$ (true if a question's answer is "Don't Know / No Answer")
   *   $NAME.refuse()$ (true if a question's answer is "Refused")
   *   $NAME.dkna_refuse()$ (true if a question's answer is "DK/NA" or "Refused")
   *   $NAME.dkna_refuse_empty()$ (true if a question's answer is "DK/NA" or "Refused" or not answered)
   *   $NAME.not_dkna_refuse()$ (true if a question's answer is not "DK/NA" and not "Refused")
   *   $NAME.not_dkna_refuse_empty()$ (true if a question's answer is not "DK/NA" and not "Refused" and must be answered)
   *   $NAME.value("PATH")$ (a particular property of an object-based answer)
   *   $respondent.token$ (gets the respondent's token)
   *   $respondent.interview_type$ (will be empty if there is no special interview_type)
   *   $respondent.language$ (gets the current language code)
   *   $respondent.start_date$ (gets the date the response was launched in YYYY-MM-DD format)
   *   showhidden true if showing hidden elements (launched by phone) false if not (launched by web)
   *   current_year The current year in YYYY format
   *   current_month The current month in MM format
   *   current_day The current day in DD format
   *   today The current date in YYYY-MM-DD format
   *   null (when a question has no answer - it's skipped)
   *   true|false (boolean)
   *   123 (number)
   *   "string" (may be delimited by ', " or `
   *
   * numeric iteration:
   *   Any boolean (true/false) variable enclosed by @ or $ can include a numerical iteration.
   *   For example, the expression $NAME#10#$ will be converted to:
   *     $NAME1$ || $NAME2$ || ... || $NAME10$
   *   This also works for values with expressions, where $NAME#10#.value("PATH")$ will be converted to:
   *     $NAME1.value("PATH")$ || $NAME2.value("PATH")$ || .. || $NAME10.value("PATH")$
   * 
   * lookup:
   *   @NAME.indicator("LOOKUP","INDICATOR")@ (true if attribute has a particular indicator for the given lookup)
   *   $NAME.indicator("LOOKUP","INDICATOR")$ (true if answer has a particular indicator for the given lookup)
   *
   * lists:
   *   $NAME:OPTION$ (always true if it is selected, false if not)
   *   $NAME:count()$ (always a number representing how many options are selected)
   *   $NAME.extra(OPTION)$ Used to get the extra value associated with a selected option
   *
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
      $last_char = NULL;
      foreach( str_split( strtolower( $precondition ) ) as $index => $char )
      {
        $process_char = true;

        if( 'string' == $this->active_term )
        { // ignore characters until the string has closed
          if( $this->quote === $char && '\\' !== $last_char ) $compiled .= $this->process_string();
          else $this->term .= $char;
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
            // determine if this is a respondent or question variable
            $compiled .= (
              preg_match( '/^respondent\.(.+)$/', $this->term, $matches ) ?
                $this->process_respondent_value( $matches[1] ) :
                $this->process_question( $override_question_object )
            );
          }
          $process_char = false;
        }

        if( $process_char ) $compiled .= $this->process_character( $char );
        $last_char = $char;
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
        sprintf(
          "Error while evaluating expression:%s\n\n%s",
          $precondition,
          $e->get_raw_message()
        ),
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

    // enclose the term in double quotes, making sure to escape any un-escaped double quote in the term
    return sprintf( '"%s"', preg_replace( '/^"|([^\\\])"/', '\1\"', $this->term ) );
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
    if( 'today' == $this->term ) return util::get_datetime_object()->format( 'Y-m-d' );
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

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Attribute found but expecting an operator', __METHOD__ );

    $attribute_name_list = [];

    // check for a numerical iterator
    if( preg_match( '/(.*)#([0-9]+)#(.*)/', $attribute_name, $iterator_matches ) )
    {
      $max = intval( $iterator_matches[2] );
      for( $i = 1; $i <= $max; $i++ ) $attribute_name_list[] = preg_replace( '/#[0-9]+#/', $i, $attribute_name );
    }
    else $attribute_name_list[] = $attribute_name;

    $compiled_list = [];
    foreach( $attribute_name_list as $attribute_name )
    {
      // make sure the attribute exists in the qnaire
      $db_attribute = $attribute_class_name::get_unique_record(
        array( 'qnaire_id', 'name' ),
        array( $this->db_qnaire->id, $attribute_name )
      );

      if( is_null( $db_attribute ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid attribute "%s"', $attribute_name ), __METHOD__ );

      // if a response was provided replace the term with the attribute's value
      $compiled = sprintf( '%%%s%%', $attribute_name );
      if( !is_null( $this->db_response ) )
      {
        $this->db_response_attribute = $response_attribute_class_name::get_unique_record(
          array( 'response_id', 'attribute_id' ),
          array( $this->db_response->id, $db_attribute->id )
        );

        // Try creating any missing response attributes (this may happen with new attributes)
        if( is_null( $this->db_response_attribute ) )
        {
          $this->db_response_attribute = lib::create( 'database\response_attribute' );
          $this->db_response_attribute->response_id = $this->db_response->id;
          $this->db_response_attribute->attribute_id = $db_attribute->id;
          // participant-specific attributes will always be NULL for anonymous respondents
          $this->db_response_attribute->value =
            $db_attribute->get_participant_value( $this->db_response->get_participant() );
          $this->db_response_attribute->save();
        }

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
          $compiled = is_null( $this->db_response_attribute->value ) ?
            'null' : addslashes( $this->db_response_attribute->value );

          // add quotes if required
          if( 'null' != $compiled &&
              !util::string_matches_int( $compiled ) &&
              !util::string_matches_float( $compiled ) ) $compiled = sprintf( "'%s'", $compiled );
        }
      }

      $compiled_list[] = $compiled;
    }

    // if we iterated over a list of attributes then we now represent a boolean expression
    if( 1 < count( $compiled_list ) ) $this->last_term = 'boolean';
    // also, if the last term was an operator then we now represent a boolean expression
    else if( 'operator' == $this->last_term ) $this->last_term = 'boolean';
    else $this->last_term = $this->active_term;
    $this->active_term = NULL;

    return 1 < count( $compiled_list ) ?
      sprintf( '(%s)', implode( ' || ', $compiled_list ) ) :
      current( $compiled_list );
  }

  /**
   * Processes the current term as a respondent variable
   * @param string $variable The selected respondent variable (token, interview_type, language, start_date)
   * @return string
   */
  private function process_respondent_value( $variable )
  {
    if( 'token' == $variable )
    {
      $compiled = is_null( $this->db_response ) ? '' : $this->db_response->get_respondent()->token;
    }
    else if( 'interview_type' == $variable )
    {
      $compiled = is_null( $this->db_response ) || is_null( $this->db_response->interview_type ) ?
        '' : $this->db_response->interview_type;
    }
    else if( 'language' == $variable )
    {
      $compiled = is_null( $this->db_response )
                ? $this->db_qnaire->get_base_language()->code
                : $this->db_response->get_language()->code;
    }
    else if( 'start_date' == $variable )
    {
      $compiled = is_null( $this->db_response ) || is_null( $this->db_reponse->start_datetime )
                ? ''
                : $this->db_response->start_datetime->format( 'YYYY-MM-DD' ) ;
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
    $this->active_term = NULL;

    // always wrap the response in quotes since all possible values are string-based
    return sprintf( '"%s"', $compiled );
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

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Question found but expecting an operator', __METHOD__ );

    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );
    $lookup_class_name = lib::get_class_name( 'database\lookup' );
    $lookup_item_class_name = lib::get_class_name( 'database\lookup_item' );
    $indicator_class_name = lib::get_class_name( 'database\indicator' );

    // figure out the question(s), and possibly the question option(s) referred to by this term
    $question_list = [];
    $question_option_list = [];
    $db_lookup = NULL;
    $db_indicator = NULL;
    $special_function = NULL;
    $object_path = NULL;

    // question-options and certain functions are defined by question:question_option
    if( preg_match( '/([^.:]+)([.:])([^.:]+)/', $this->term, $matches ) )
    {
      if( 4 != count( $matches ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid question "%s"', $this->term ), __METHOD__ );

      $question_name = $matches[1];
      $question_operator = $matches[2];
      $question_function = $matches[3];

      $question_name_list = [];

      // check for a numerical iterator
      if( preg_match( '/(.*)#([0-9]+)#(.*)/', $question_name, $iterator_matches ) )
      {
        $max = intval( $iterator_matches[2] );
        for( $i = 1; $i <= $max; $i++ ) $question_name_list[] = preg_replace( '/#[0-9]+#/', $i, $question_name );
      }
      else $question_name_list[] = $question_name;

      foreach( $question_name_list as $question_name )
      {
        $db_question = $this->db_qnaire->get_question( $question_name );
        $db_question_option = NULL;
        if( is_null( $db_question ) )
        {
          throw lib::create( 'exception\runtime',
            sprintf( 'No question name "%s" found.', $question_name ),
            __METHOD__
          );
        }

        if( '.' == $question_operator )
        {
          if( 'extra(' == substr( $question_function, 0, 6 ) )
          {
            $special_function = 'extra';

            if( !preg_match( '/extra\(([^)]+)\)/', $question_function, $sub_matches ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf( 'Invalid syntax "%s"', $question_function ),
                __METHOD__
              );
            }

            $db_question_option = $question_option_class_name::get_unique_record(
              array( 'question_id', 'name' ),
              array( $db_question->id, $sub_matches[1] )
            );
            if( is_null( $db_question_option ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf( 'Invalid question option "%s" for question "%s"', $sub_matches[1], $question_name ),
                __METHOD__
              );
            }
            else if( is_null( $db_question_option->extra ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf(
                  'Question option "%s" for question "%s" does not have extra values.',
                  $sub_matches[1],
                  $question_name
                ),
                __METHOD__
              );
            }
          }
          else if( 'indicator(' == substr( $question_function, 0, 10 ) )
          {
            $special_function = 'indicator';

            if( !preg_match( '/indicator\( *"([^"]+)" *, *"([^"]+)" *\)/', $question_function, $sub_matches ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf( 'Invalid syntax "%s"', $question_function ),
                __METHOD__
              );
            }

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
          else if( 'value(' == substr( $question_function, 0, 6 ) )
          {
            $special_function = 'value';

            if( !preg_match( '/value\( *"([^"]+)" *\)/', $this->term, $sub_matches ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf( 'Invalid syntax "%s"', $this->term ),
                __METHOD__
              );
            }

            $object_path = $sub_matches[1];
          }
          else if( in_array(
            $question_function,
            [
              'empty()', 'not_empty()', 'dkna()', 'refuse()', 'dkna_refuse()',
              'dkna_refuse_empty()', 'not_dkna_refuse()', 'not_dkna_refuse_empty()'
            ]
          ) )
          {
            $special_function = substr( $question_function, 0, -2 );
          }
          else
          {
            throw lib::create( 'exception\runtime',
              sprintf( 'No such function "%s"', $question_function ),
              __METHOD__
            );
          }
        }
        else if( ':' == $question_operator )
        {
          if( 'count()' == $question_function )
          {
            // return how many options are selected
            $special_function = 'count';
          }
          else
          {
            $db_question_option = $question_option_class_name::get_unique_record(
              array( 'question_id', 'name' ),
              array( $db_question->id, $question_function )
            );
            if( is_null( $db_question_option ) )
            {
              throw lib::create( 'exception\runtime',
                sprintf(
                  'No question option name "%s" found for question "%s".',
                  $question_function,
                  $question_name
                ),
                __METHOD__
              );
            }
          }
        }

        $question_list[] = $db_question;
        $question_option_list[] = $db_question_option;
      }
    }
    else // questions are defined by name
    {
      $question_name = $this->term;
      $question_name_list = [];

      // check for a numerical iterator
      if( preg_match( '/(.*)#([0-9]+)#(.*)/', $question_name, $iterator_matches ) )
      {
        $max = intval( $iterator_matches[2] );
        for( $i = 1; $i <= $max; $i++ ) $question_name_list[] = preg_replace( '/#[0-9]+#/', $i, $question_name );
      }
      else $question_name_list[] = $question_name;

      foreach( $question_name_list as $question_name )
      {
        $db_question = $this->db_qnaire->get_question( $question_name );
        if( is_null( $db_question ) )
        {
          throw lib::create( 'exception\runtime',
            sprintf( 'No question name "%s" found.', $question_name ),
            __METHOD__
          );
        }

        if( 'list' == $db_question->type )
        {
          throw lib::create( 'exception\runtime',
            sprintf(
              'Question "%s" is a list type so you must reference a question option using '.
              'the QUESTION:OPTION format.',
              $question_name
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

        $question_list[] = $db_question;
        $question_option_list[] = NULL;
      }
    }

    $compiled_list = [];
    foreach( $question_list as $index => $db_question )
    {
      $db_question_option = $question_option_list[$index];

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
        $dkna = is_null( $db_answer ) ? false : $db_answer->is_dkna();
        $refuse = is_null( $db_answer ) ? false : $db_answer->is_refuse();

        if( 'empty' == $special_function ) $compiled = is_null( $value ) ? 'true' : 'false';
        else if( 'not_empty' == $special_function ) $compiled = is_null( $value ) ? 'false' : 'true';
        else if( 'dkna' == $special_function ) $compiled = $dkna ? 'true' : 'false';
        else if( 'refuse' == $special_function ) $compiled = $refuse ? 'true' : 'false';
        else if( 'dkna_refuse' == $special_function ) $compiled = $dkna || $refuse ? 'true' : 'false';
        else if( 'dkna_refuse_empty' == $special_function )
          $compiled = $dkna || $refuse || is_null( $value ) ? 'true' : 'false';
        else if( 'not_dkna_refuse' == $special_function ) $compiled = $dkna || $refuse ? 'false' : 'true';
        else if( 'not_dkna_refuse_empty' == $special_function )
          $compiled = $dkna || $refuse || is_null( $value ) ? 'false' : 'true';
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
              else if(
                ( is_object( $selected_option ) && $db_question_option->id == $selected_option->id ) ||
                ( !is_object( $selected_option ) && $db_question_option->id == $selected_option )
              ) {
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
        else if( 'value' == $special_function )
        {
          $sub_path = (new JSONPath( $value ))->find( sprintf( '$.%s', $object_path ) );
          if( !$sub_path->valid() )
          {
            throw lib::create( 'exception\runtime',
              sprintf(
                "JSON Path \"%s\" for question \"%s\" not found in answer data:\n%s",
                $object_path,
                $db_question->name,
                $db_answer->value
              ),
              __METHOD__
            );
          }

          $compiled = $sub_path->data()[0];
          if( is_null( $value ) ) $compiled = 'NULL';
          else if( is_bool( $value ) ) $compiled = $value ? 'true' : 'false';
          else if( is_string( $value ) )
            $compiled = sprintf( "'%s'", str_replace( "'", "\\'", $value ) );
        }
        else
        {
          if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
          else if( 'string' == $db_question->type )
            $compiled = sprintf( "'%s'", str_replace( "'", "\\'", $value ) );
          else $compiled = $value;
        }
      }

      $compiled_list[] = $compiled;
    }

    // if we iterated over a list of questions then we now represent a boolean expression
    if( 1 < count( $compiled_list ) ) $this->last_term = 'boolean';
    else if( is_null( $special_function ) )
      $this->last_term = !is_null( $db_question_option ) ? 'boolean' : $db_question->type;
    else if( 'extra' == $special_function ) $this->last_term = $db_question_option->extra;
    else $this->last_term = 'count' == $special_function ? 'number' : 'boolean';

    $this->active_term = NULL;

    return 1 < count( $compiled_list ) ?
      sprintf( '(%s)', implode( ' || ', $compiled_list ) ) :
      current( $compiled_list );
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
