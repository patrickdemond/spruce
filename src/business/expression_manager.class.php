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
   * @throws exception\argument
   * @access protected
   */
  protected function __construct()
  {
    // nothing required
  }

  /**
   * TODO: document
   */
  public function validate( $db_qnaire, $precondition )
  {
    try { $this->evaluate( $db_qnaire, $precondition ); }
    catch( \cenozo\exception\runtime $e )
    {
      return sprintf( "%s\n\n%s", $e->get_raw_message(), $e->get_previous()->get_raw_message() );
    }
    return NULL;
  }

  /**
   * TODO: document
   */
  public function evaluate( $record, $precondition )
  {
    if( is_a( $record, lib::get_class_name( 'database\qnaire' ) ) )
    {
      $db_response = NULL;
      $db_qnaire = $record;
    }
    else if( is_a( $record, lib::get_class_name( 'database\response' ) ) )
    {
      $db_response = $record;
      $db_qnaire = $db_response->get_qnaire();
    }
    else throw lib::create( 'exception\argument', 'record', $record, __METHOD__ );

    $compiled = $this->compile( $record, $precondition );
    try
    {
      $response = is_null( $db_response ) ? true : eval( sprintf( 'return (%s);', $compiled ) );
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
   * TODO: document
   * 
   * values:
   *   @NAME@ (response attribute)
   *   $NAME$ (question)
   *   $NAME:empty()$ (true if question hasn't been answered, false if it has)
   *   $NAME:dkna()$ (true if a question's answer is don't know or no answer)
   *   $NAME:refuse()$ (true if a question is refused)
   *   null (when a question has no answer - it's skipped)
   *   true|false (boolean)
   *   123 (number)
   *   "string" (may be delimited by ', " or `

   * lists:
   *   $NAME:OPTION$ (always true if it is selected, false if not)
   *   $NAME:count()$ (always a number representing how many options are selected)

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
   */
  public function compile( $record, $precondition, $override_question_object = NULL )
  {
    // if an override object is proided then make sure it's either a question or question_option
    if( !is_null( $override_question_object ) &&
        !is_a( $override_question_object, lib::get_class_name( 'database\question' ) ) &&
        !is_a( $override_question_object, lib::get_class_name( 'database\question_option' ) ) )
      throw lib::create( 'exception\argument', 'override_question_object', $override_question_object, __METHOD__ );

    if( is_a( $record, lib::get_class_name( 'database\qnaire' ) ) )
    {
      $db_response = NULL;
      $db_qnaire = $record;
    }
    else if( is_a( $record, lib::get_class_name( 'database\response' ) ) )
    {
      $db_response = $record;
      $db_qnaire = $db_response->get_qnaire();
    }
    else throw lib::create( 'exception\argument', 'record', $record, __METHOD__ );

    $this->reset();

    // empty preconditions always pass
    if( is_null( $precondition ) ) return true; 

    $compiled = '';

    try{
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
          if( preg_match( '/[a-z]/', $char ) )
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
          else $compiled .= $this->process_attribute( $db_qnaire, $db_response );
          $process_char = false;
        }
        else if( 'question' == $this->active_term )
        {
          if( '$' != $char ) $this->term .= $char;
          else $compiled .= $this->process_question( $db_qnaire, $db_response, $override_question_object );
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
      else if( 'question' == $this->active_term )
        throw lib::create( 'exception\runtime', 'Expression has an unclosed question', __METHOD__ );
      else if( 'number' == $this->active_term ) $compiled .= $this->process_number();
      else if( 'constant' == $this->active_term ) $compiled .= $this->process_constant();
      else if( 'operator' == $this->active_term ) $compiled .= $this->process_operator();
    }
    catch( \cenozo\exception\runtime $e )
    {
      throw lib::create( 'exception\runtime',
        sprintf( 'Error while evaluating precondition:%s', "\n".$precondition ),
        __METHOD__,
        $e
      );
    }

    return strtolower( $compiled );
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
   * TODO: document
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
   * TODO: document
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
   * TODO: document
   */
  private function process_constant()
  {
    $type = NULL;
    if( 'null' == $this->term ) $type = 'null';
    else if( in_array( $this->term, ['true', 'false'] ) ) $type = 'boolean';

    if( is_null( $type ) )
      throw lib::create( 'exception\runtime', sprintf( 'Invalid constant "%s"', $this->term ), __METHOD__ );

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Constant found but expecting an operator', __METHOD__ );

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $type;
    $this->active_term = NULL;

    return $this->term;
  }

  /**
   * TODO: document
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
      if( !in_array( $this->last_term, ['number', 'string', 'attribute'] ) )
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
    { // logical operator
      if( !in_array( $this->last_term, ['boolean'] ) )
      {
        throw lib::create( 'exception\runtime', sprintf(
          'Expecting a boolean before "%s"', $this->term
        ), __METHOD__ );
      }
    }
    else throw lib::create( 'exception\runtime', sprintf( 'Invalid operator "%s"', $this->term ), __METHOD__ );

    $this->last_term = $this->active_term;
    $this->active_term = NULL;

    return sprintf( ' %s ', $this->term );
  }

  /**
   * TODO: document
   */
  private function process_attribute( $db_qnaire, $db_response = NULL )
  {
    $attribute_class_name = lib::get_class_name( 'database\attribute' );
    $response_attribute_class_name = lib::get_class_name( 'database\response_attribute' );

    // make sure the attribute exists in the qnaire
    $db_attribute = $attribute_class_name::get_unique_record(
      array( 'qnaire_id', 'name' ),
      array( $db_qnaire->id, $this->term )
    );

    if( is_null( $db_attribute ) )
      throw lib::create( 'exception\runtime', sprintf( 'Invalid attribute "%s"', $this->term ), __METHOD__ );

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Attribute found but expecting an operator', __METHOD__ );

    // if a response was provided replace the term with the attribute's value
    $compiled = sprintf( '%%%s%%', $this->term );
    if( !is_null( $db_response ) )
    {
      $db_response_attribute = $response_attribute_class_name::get_unique_record(
        array( 'response_id', 'attribute_id' ),
        array( $db_response->id, $db_attribute->id )
      );
      $compiled = sprintf( '%s', addslashes( $db_response_attribute->value ) );

      // add quotes if required
      if( 'null' != $compiled && !util::string_matches_int( $compiled ) && !util::string_matches_float( $compiled ) )
        $compiled = sprintf( "'%s'", $compiled );
    }

    // if the last term was an operator then assume we now represent a boolean expression
    $this->last_term = 'operator' == $this->last_term ? 'boolean' : $this->active_term;
    $this->active_term = NULL;

    return $compiled;
  }

  /**
   * TODO: document
   */
  private function process_question( $db_qnaire, $db_response = NULL, $override_question_object = NULL )
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
      else throw lib::create( 'exception\argument', 'override_question_object', $override_question_object, __METHOD__ );
    }

    $answer_class_name = lib::get_class_name( 'database\answer' );
    $question_option_class_name = lib::get_class_name( 'database\question_option' );

    // figure out the question, and possibly the question option referred to by this term
    $db_question = NULL;
    $db_question_option = NULL;
    $special_function = NULL;

    // question-options and certain functions are defined by question:question_option
    if( preg_match( '/([^.:]+)([.:])([^.:]+)/', $this->term, $matches ) )
    {
      if( 4 != count( $matches ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid question "%s"', $this->term ), __METHOD__ );

      $db_question = $db_qnaire->get_question( $matches[1] );
      if( is_null( $db_question ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid question "%s"', $matches[1] ), __METHOD__ );

      if( '.' == $matches[2] && in_array( $matches[3], ['empty()', 'dkna()', 'refuse()'] ) )
      {
        $special_function = substr( $matches[3], 0, -2 );
      }
      else if( ':' == $matches[2] )
      {
        $db_question_option = $question_option_class_name::get_unique_record(
          array( 'question_id', 'name' ),
          array( $db_question->id, $matches[3] )
        );
        if( is_null( $db_question_option ) )
        {
          throw lib::create( 'exception\runtime',
            sprintf( 'Invalid question option "%s" for question "%s"', $matches[3], $matches[1] ),
            __METHOD__
          );
        }
      }
    }
    else // questions are defined by name
    {
      $db_question = $db_qnaire->get_question( $this->term );
      if( is_null( $db_question ) )
        throw lib::create( 'exception\runtime', sprintf( 'Invalid question "%s"', $this->term ), __METHOD__ );

      if( 'text' == $db_question->type || 'comment' == $db_question->type )
        throw lib::create( 'exception\runtime', sprintf( 'Cannot use question type "%s"', $db_question->type ), __METHOD__ );
    }

    // test that the working item is an operator
    if( !is_null( $this->last_term ) && 'operator' != $this->last_term )
      throw lib::create( 'exception\runtime', 'Question found but expecting an operator', __METHOD__ );

    // if a response was provided and there is no override then replace the term with the attribute's value
    $compiled = sprintf( '$%s$', $this->term );
    if( !is_null( $db_response ) && (
      // make sure the question isn't overridden
      is_null( $db_override_question ) ||
      $db_question->page_id != $db_override_question->page_id ||
      $db_question->rank >= $db_override_question->rank
    ) && (
      // make sure the question_option isn't overridden
      is_null( $db_override_question_option ) ||
      $db_question_option->question_id != $db_override_question_option->question_id ||
      $db_question_option->rank >= $db_override_question_option->rank
    ) ) {
      $db_answer = $answer_class_name::get_unique_record(
        array( 'response_id', 'question_id' ),
        array( $db_response->id, $db_question->id )
      );
      $value = is_null( $db_answer ) ? NULL : util::json_decode( $db_answer->value );
      $dkna = is_object( $value ) && property_exists( $value, 'dkna' ) && $value->dkna;
      $refuse = is_object( $value ) && property_exists( $value, 'refuse' ) && $value->refuse;

      if( 'empty' == $special_function ) $compiled = is_null( $value ) ? 'true' : 'false';
      else if( 'dkna' == $special_function ) $compiled = $dkna ? 'true' : 'false';
      else if( 'refuse' == $special_function ) $compiled = $refuse ? 'true' : 'false';
      else if( is_null( $value ) || $dkna || $refuse ) $compiled = 'NULL';
      else if( !is_null( $db_question_option ) )
      {
        // set whether or not the response checked off the option
        $compiled = 'false';
        if( is_array( $value ) )
        {
          foreach( $value as $selected_option )
          {
            if( ( is_object( $selected_option ) && $db_question_option->id == $selected_option->id ) ||
                ( !is_object( $selected_option ) && $db_question_option->id == $selected_option ) )
            {
              $compiled = 'true';
              break;
            }
          }
        }
      }
      else
      {
        if( 'boolean' == $db_question->type ) $compiled = $value ? 'true' : 'false';
        else if( 'list' == $db_question->type )
        {
          // compile a list of all option values as a CSV
          $value_list = array();
          foreach( $value as $selected_option )
          {
            $question_option_id = is_object( $selected_option ) ? $selected_option->id : $selected_option;
            $db_question_option = lib::create( 'database\question_option', $question_option_id );
            $value_list[] = sprintf( '"%s"', addslashes( $db_question_option->name ) );
          }
          $compiled = implode( ',', $value_list );
        }
        else if( 'string' == $db_question->type ) $compiled = sprintf( "'%s'", str_replace( "'", "\\'", $value ) );
        else $compiled = $value;
      }
    }

    $this->last_term = !is_null( $db_question_option )
                     ? ( is_null( $db_question_option->extra ) ? 'boolean' : $db_question_option->extra )
                     : ( is_null( $special_function ) ? $db_question->type : 'boolean' );
    $this->active_term = NULL;

    return $compiled;
  }

  /**
   * TODO: document
   */
  private function process_character( $char )
  {
    $compiled = '';

    if( '(' == $char || ')' == $char )
    {
      $compiled .= $char;
      $this->open_bracket += '(' == $char ? 1 : -1;
      if( 0 > $this->open_bracket )
        throw lib::create( 'exception\runtime', 'Found closing bracket without a matching opening bracket.', __METHOD__ );
    }
    else if( in_array( $char, ["'", '"', '`'] ) )
    {
      $this->active_term = 'string';
      $this->quote = $char;
    }
    else if( ' ' == $char ) ; // ignore spaces
    else if( '@' == $char ) $this->active_term = 'attribute';
    else if( '$' == $char ) $this->active_term = 'question';
    else if( preg_match( '/[0-9.]/', $char ) ) $this->active_term = 'number';
    else if( preg_match( '/[a-z_]/', $char ) ) $this->active_term = 'constant';
    else if( preg_match( '/[-+*\/<>!=~&|?:]/', $char ) ) $this->active_term = 'operator';

    $this->term = in_array( $char, ['(', ')', "'", '"', '`', ' ', '@', '$'] ) ? '' : $char;
    return $compiled;
  }

  /**
   * TODO: document
   */
  private $db_qnaire;
  
  /**
   * What type of quote was used to open the string
   */
  private $quote;
  
  /**
   * Stores whether some element is being declared (string, number, attribute, question, constant or operator)
   */
  private $active_term;
  
  /**
   * Stores the active term as it is read
   */
  private $term;

  /**
   * TODO: document
   */
  private $last_term;

  /**
   * TODO: document
   */
  private $open_bracket;
}
