<?php
/**
 * expression_manager.class.php
 * 
 * @author Patrick Emond <emondpd@mcmaster.ca>
 */

namespace cenozo\business;
use cenozo\lib, cenozo\log;

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
   * 
   * values:
   *   %NAME% (response attribute)
   *   $NAME$ (question)
   *   $NAME:empty()$ (true if question hasn't been answered, false if it has)
   *   dkna (when a question's answer is don't know or no answer)
   *   refuse (when a question is refused)
   *   null (when a question has no answer - it's skipped)
   *   true|false (boolean)
   *   123 (number)
   *   "string"

   * lists:
   *   $NAME:OPTION$ (always true if it is selected, false if not)
   *   $NAME:OPTION:extra$ (always the 
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
   *   ( must have same number opening as closing
   *   ) must have same number opening as closing
   * 
   */
  public function validate( $precondition )
  {
    $error = NULL;

    $open_bracket = 0; // keep a count of unclosed open brackets
    $in_string = false; // declaring a string (" deliminated)
    $in_number = false; // declaring a number
    $in_attribute = false; // in an attribute declaration (% deliminated)
    $in_question = false; // in a question declaration ($ deliminated)
    $in_constant = false; // declaring a constant
    $in_operator = false; // declaring an operator
    $dkna = ''; // keeps track of DKNA declarations
    $refuse = ''; // keeps track of REFUSE declarations
    $null = ''; // keeps track of NULL declarations
    $escape = false; // escaping the next character (using a backslash)
    $string_value = '';
    $number_value = '';
    $constant_name = '';
    $attribute_name = '';
    $question_name = '';
    $operator_name = '';
    $working_item = NULL;
    
    // loop through the precondition one character at a time
    foreach( str_split( strtolower( $precondition ) ) as $index => $char )
    {
      if( $escape )
      {
        if( $in_string ) $string_value .= $char;
        $escape = false;
      }
      else // we're not escaped
      {
        $process_char = true;

        if( $in_string )
        { // ignore characters until the string has closed
          if( '"' != $char )
          {
            if( '\\' == $char ) $escape = true;
            $string_value .= $char;
          }
          else
          {
            $in_string = false;
            $string_value = '';
            // TODO: test taht working item is an operator
            $working_item = 'string';
          }

          $process_char = false;
        }
        else if( $in_number )
        {
          if( preg_match( '/[0-9.]/', $char ) )
          {
            $number_value .= $char;
            $process_char = false;
          }
          else
          {
            $in_number = false;
            if( !$this->test_number( $number_value ) ) return( sprintf( 'Invalid number "%s"', $number_value ) );
            $number_value = '';
            // TODO: test taht working item is an operator
            $working_item = 'number';
            $process_char = true; // make sure to process the current char below
          }
        }
        else if( $in_constant )
        {
          if( preg_match( '/[a-z]/', $char ) )
          {
            $constant_name .= $char;
            $process_char = false;
          }
          else
          {
            $in_constant = false;
            $type = $this->get_constant_type( $constant_name );
            if( is_null( $type ) ) return( sprintf( 'Invalid constant "%s"', $constant_name ) );
            $constant_name = '';
            // TODO: test taht working item is an operator
            $working_item = $type;
            $process_char = true; // make sure to process the current char below
          }
        }
        else if( $in_operator )
        {
          if( preg_match( '/[=&|]/', $char ) )
          {
            $operator_name .= $char;
            $process_char = false;
          }
          else
          {
            $in_operator = false;
            if( in_array( $operator_name, ['-', '+', '*', '/'] ) )
            { // mathematical operator
              if( !in_array( $working_item, ['number', 'attribute'] ) )
                return( sprintf( 'Expecting a number or attribute before "%s"', $operator_name ) );
            }
            else if( in_array( $operator_name, ['<', '<=', '>', '>='] ) )
            { // quantity operator
              if( !in_array( $working_item, ['number', 'string', 'attribute'] ) )
                return( sprintf( 'Expecting a number, string or attribute before "%s"', $operator_name ) );
            }
            else if( in_array( $operator_name, ['==', '!='] ) )
            { // equality operator
              if( is_null( $working_item ) )
                return( sprintf( 'Expecting an expression before "%s"', $operator_name ) );
            }
            else if( '~=' == $operator_name )
            { // sql LIKE operator
              if( !in_array( $working_item, ['number', 'string', 'attribute'] ) )
                return( sprintf( 'Expecting a number, string or attribute before "%s"', $operator_name ) );
            }
            else if( in_array( $operator_name, ['&&', '||'] ) )
            { // logical operator
              if( !in_array( $working_item, ['boolean'] ) )
                return( sprintf( 'Expecting a boolean before "%s"', $operator_name ) );
            }
            else
            {
              return( sprintf( 'Invalid operator "%s"', $operator_name ) );
            }

            $working_item = $operator_name;
            $operator_name = '';
            $process_char = true; // make sure to process the current char below
          }
        }
        else if( $in_attribute )
        {
          if( '%' == $char )
          {
            // process attribute
            if( !$this->test_attribute( $attribute_name ) ) return( sprintf( 'Invalid attribute "%s"', $attribute_name ) );
            $attribute_name = '';
            // TODO: test taht working item is an operator
            $working_item = 'attribute';

            $in_attribute = false;
          }
          else $attribute_name .= $char;

          $process_char = false;
        }
        else if( $in_question )
        {
          if( '$' == $char )
          {
            // process question
            $type = $this->get_question_type( $question_name );
            if( is_null( $type ) ) return( sprintf( 'Invalid question "%s"', $question_name ) );
            $question_name = '';
            // TODO: test taht working item is an operator
            $working_item = $type;

            $in_question = false;
          }
          else $question_name .= $char;

          $process_char = false;
        }

        if( $process_char )
        {
          if( ' ' == $char ) {} // ignore spaces
          else if( '\\' == $char )
          {
            if( !$in_string ) return sprintf( 'Invalid character found at character %d', $index+1 );
            $escape = true;
          }
          else if( '(' == $char ) $open_bracket++;
          else if( ')' == $char ) $open_bracket--;
          else if( '"' == $char ) $in_string = true;
          else if( '%' == $char ) $in_attribute = true;
          else if( '$' == $char ) $in_question = true;
          else if( preg_match( '/[0-9.]/', $char ) ) $in_number = true;
          else if( preg_match( '/[a-z_]/', $char ) ) $in_constant = true;
          else if( preg_match( '/[-+*\/<>!=~&|]/', $char ) ) $in_operator = true;
        }
      }
    }

    if( $in_string ) return 'Expression has an unclosed string';
    else if( $in_attribute ) return 'Expression has an unclosed attribute';
    else if( $in_question ) return 'Expression has an unclosed question';
    else if( 0 < $open_bracket ) 
    {
      return sprintf(
        'There are %d too many %s brackets in the expression',
        abs( $open_bracket ),
        0 < $open_bracket ? 'opening' : 'closing'
      );
    }

    return NULL;
  }

  /**
   * TODO: document
   */
  private function test_attribute( $name )
  {
    // TODO: implement
    return true;
  }

  /**
   * TODO: document
   */
  private function get_question_type( $name )
  {
    // TODO: implement
    return 'string';
  }

  /**
   * TODO: document
   */
  private function test_number( $value )
  {
    // TODO: implement
    return true;
  }

  /**
   * TODO: document
   */
  private function get_constant_type( $name )
  {
    if( in_array( $name, ['dkna', 'refuse'] ) ) return 'no answer';
    else if( 'null' == $name ) return 'null';
    else if( in_array( $name, ['true', 'false'] ) ) return 'boolean';
    return NULL;
  }
}
