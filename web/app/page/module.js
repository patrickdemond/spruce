define( [ 'question' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'page', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'page' );

  module.identifier.parent = {
    subject: 'module',
    column: 'module.id'
  };

  module.addInput( '', 'average_time', { title: 'Average Time (seconds)', type: 'string', isConstant: true, isExcluded: 'add' } );
  module.addInput( '', 'max_time', {
    title: 'Max Time (seconds)',
    type: 'string',
    format: 'integer',
    isConstant: true,
    help: 'Maximum page time is automatically calculated to exclude major outliers by setting its value to that of the outer fence ' +
      '(3 times the interquartile width above the upper quartile).'
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'qnaire_id', { column: 'qnaire.id', isExcluded: true } );
  module.addInput( '', 'qnaire_name', { column: 'qnaire.name', isExcluded: true } );
  module.addInput( '', 'debug', { column: 'qnaire.debug', isExcluded: true } );
  module.addInput( '', 'base_language', { column: 'base_language.code', isExcluded: true } );
  module.addInput( '', 'prompts', { isExcluded: true } );
  module.addInput( '', 'module_prompts', { isExcluded: true } );
  module.addInput( '', 'popups', { isExcluded: true } );
  module.addInput( '', 'module_popups', { isExcluded: true } );
  module.addInput( '', 'module_id', { isExcluded: true } );
  module.addInput( '', 'parent_name', { column: 'module.name', isExcluded: true } );
  cenozo.insertPropertyAfter( module.columnList, 'question_count', 'average_time', {
    title: 'Average Time',
    type: 'seconds'
  } );

  module.addExtraOperation( 'view', {
    title: 'Preview',
    operation: function( $state, model ) {
      $state.go(
        'page.render',
        { identifier: model.viewModel.record.getIdentifier() },
        { reload: true }
      );
    }
  } );

  // used by services below to returns the index of the option matching the second argument
  function searchOptionList( optionList, id ) {
    var optionIndex = null;
    if( angular.isArray( optionList ) ) optionList.some( function( option, index ) {
      if( option == id || ( angular.isObject( option ) && option.id == id ) ) {
        optionIndex = index;
        return true;
      }
    } );
    return optionIndex;
  }

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'page' );

          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Module',
              go: function() { return $state.go( 'module.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'module.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Pages'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'page.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageRender', [
    'CnPageModelFactory', 'CnTranslationHelper', 'CnSession', 'CnHttpFactory', '$q', '$state', '$document',
    function( CnPageModelFactory, CnTranslationHelper, CnSession, CnHttpFactory, $q, $state, $document ) {
      return {
        templateUrl: module.getFileUrl( 'render.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          function isNumpadInput( event ) {
            // only send keyup events when on the render page and the key is a numpad number
            return ['render','run'].includes( $scope.model.getActionFromState() ) && (
              ( 13 == event.which && 'NumpadEnter' == event.code ) || // numpad enter
              ( 97 <= event.which && event.which <= 105 ) // numpad 0 to 9
            );
          }

          $scope.data = {
            page_id: null,
            qnaire_id: null,
            qnaire_name: null,
            base_language: null,
            title: null,
            uid: null
          };
          $scope.isComplete = false;
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;

          /*
          DISABLING THIS FOR NOW BECAUSE IT DOESN'T WORK WELL FOR END-USERS
          // bind keyup (first unbind to prevent duplicates)
          $document.unbind( 'keyup' );
          $document.bind( 'keyup', function( event ) {
            if( isNumpadInput( event ) ) {
              event.stopPropagation();
              $scope.model.renderModel.onKeyup( 13 == event.which ? 'enter' : event.which - 96 );
              $scope.$apply();
            }
          } );
          */

          // prevent numpad keys from entering into inputs and textareas
          $scope.validateKeydown = function( event ) {
            /*
            if( isNumpadInput( event ) ) {
              event.returnValue = false;
              event.preventDefault();
            }
            */
          };

          if( angular.isUndefined( $scope.progress ) ) $scope.progress = 0;

          $scope.text = function( address ) { return $scope.model.renderModel.text( address ); };

          function render() {
            var promiseList = [];
            if( 'respondent' != $scope.model.getSubjectFromState() || null != $scope.data.page_id ) {
              promiseList.push(
                $scope.model.viewModel.onView( true ).then( function() {
                  angular.extend( $scope.data, {
                    page_id: $scope.model.viewModel.record.id,
                    qnaire_id: $scope.model.viewModel.record.qnaire_id,
                    qnaire_name: $scope.model.viewModel.record.qnaire_name,
                    base_language: $scope.model.viewModel.record.base_language,
                    uid: $scope.model.viewModel.record.uid
                  } );

                  $scope.progress = Math.round(
                    100 * $scope.model.viewModel.record.qnaire_page / $scope.model.viewModel.record.qnaire_pages
                  );
                  return $scope.model.renderModel.onLoad();
                } )
              );
            } else if( null == $scope.data.page_id ) {
              promiseList.push( $scope.model.renderModel.reset() );
            }

            $q.all( promiseList ).then( function() {
              CnHttpFactory.instance( {
                path: [ 'qnaire', $scope.data.qnaire_id, 'language' ].join( '/' ),
                data: { select: { column: [ 'id', 'code', 'name' ] } }
              } ).query().then( function( response ) {
                $scope.languageList = response.data;
              } );

              CnSession.setBreadcrumbTrail( [ {
                title: $scope.data.qnaire_name,
                go: function() { return $state.go( 'qnaire.view', { identifier: $scope.data.qnaire_id } ); }
              }, {
                title: $scope.data.uid ? $scope.data.uid : 'Preview'
              } ] );

              if( null == $scope.model.renderModel.currentLanguage )
                $scope.model.renderModel.currentLanguage = $scope.data.base_language;

              $scope.isComplete = true;
            } );
          }

          if( 'respondent' != $scope.model.getSubjectFromState() ) render();
          else {
            // check for the respondent using the token
            var params = '?assert_response=1';
            if( $state.params.show_hidden ) params += '&&show_hidden=1';
            CnHttpFactory.instance( {
              path: 'respondent/token=' + $state.params.token + params,
              data: { select: { column: [
                'qnaire_id', 'introductions', 'conclusions', 'closes',
                { table: 'qnaire', column: 'closed' },
                { table: 'qnaire', column: 'name', alias: 'qnaire_name' },
                { table: 'response', column: 'page_id' },
                { table: 'response', column: 'submitted' },
                { table: 'participant', column: 'uid' },
                { table: 'language', column: 'code', alias: 'base_language' }
              ] } },
              onError: function( response ) {
                $state.go( 'error.' + response.status, response );
              }
            } ).get().then( function( response ) {
              var showHidden = angular.isDefined( $state.params.show_hidden ) ? $state.params.show_hidden : false;
              $scope.data = response.data;
              $scope.data.introductions = CnTranslationHelper.parseDescriptions( $scope.data.introductions, showHidden );
              $scope.data.conclusions = CnTranslationHelper.parseDescriptions( $scope.data.conclusions, showHidden );
              $scope.data.closes = CnTranslationHelper.parseDescriptions( $scope.data.closes, showHidden );
              $scope.data.title = null != $scope.data.page_id ? '' : $scope.data.submitted ? 'Conclusion' : 'Introduction';
              render();
            } );
          }
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageRenderFactory', [
    'CnHttpFactory', 'CnTranslationHelper', 'CnModalMessageFactory', 'CnModalDatetimeFactory', '$q', '$state', '$timeout',
    function( CnHttpFactory, CnTranslationHelper, CnModalMessageFactory, CnModalDatetimeFactory, $q, $state, $timeout ) {
      var object = function( parentModel ) {
        var self = this;

        function getDate( date ) {
          if( 'now' == date ) date = moment().format( 'YYYY-MM-DD' );
          return date && !angular.isObject( date ) ? moment( new Date( date ) ) : null;
        }
        function formatDate( date ) { var m = getDate( date ); return m ? m.format( 'dddd, MMMM Do YYYY' ) : null; }
        function isDkna( value ) { return angular.isObject( value ) && true === value.dkna; }
        function isRefuse( value ) { return angular.isObject( value ) && true === value.refuse; }
        function isDknaOrRefuse( value ) { return angular.isObject( value ) && ( true === value.dkna || true === value.refuse ); }

        angular.extend( this, {
          parentModel: parentModel,
          prevModuleList: [],
          nextModuleList: [],
          working: false,
          activeAttributeList: [],
          questionList: [],
          optionListById: {},
          currentLanguage: null,
          keyQuestionIndex: null,
          writePromiseList: [],
          promiseIndex: 0,
          // Used to maintain a semaphore of queries so that they are all executed in sequence without any bumping the queue
          runQuery: function( fn ) {
            return $q.all( this.writePromiseList ).then( function() {
              var newIndex = self.promiseIndex++;
              var promise = fn().finally( function() {
                // remove the promise from the write promise list
                var index = self.writePromiseList.findIndexByProperty( 'index', newIndex );
                if( null != index ) self.writePromiseList.splice( index, 1 );
              } );

              // mark the promise with a new index and store it in the write promise list
              promise.index = newIndex;
              self.writePromiseList.push( promise );
              return promise;
            } );
          },

          convertValueToModel: function( question ) {
            if( 'boolean' == question.type ) {
              question.answer = {
                yes: true === question.value,
                no: false === question.value
              };
            } else if( 'list' == question.type ) {
              var selectedOptions = angular.isArray( question.value ) ? question.value : [];
              question.answer = {
                optionList: question.optionList.reduce( function( list, option ) {
                  var optionIndex = searchOptionList( selectedOptions, option.id );
                  list[option.id] = option.multiple_answers
                                  ? { valueList: [], formattedValueList: [] }
                                  : { selected: null != optionIndex };

                  if( option.extra ) {
                    if( null != optionIndex ) {
                      list[option.id].valueList = option.multiple_answers
                                                ? selectedOptions[optionIndex].value
                                                : [selectedOptions[optionIndex].value];
                      list[option.id].formattedValueList = 'date' != option.extra
                                                         ? null
                                                         : option.multiple_answers
                                                         ? formatDate( selectedOptions[optionIndex].value )
                                                         : [formatDate( selectedOptions[optionIndex].value )];
                    } else {
                      list[option.id].valueList = option.multiple_answers ? [] : [null];
                      list[option.id].formattedValueList = option.multiple_answers ? [] : [null];
                    }
                  }

                  return list;
                }, {} )
              }
            } else {
              question.answer = {
                value: angular.isString( question.value ) || angular.isNumber( question.value ) ? question.value : null,
                formattedValue: 'date' != question.type ? null : formatDate( question.value )
              };
            }

            question.answer.dkna = isDkna( question.value );
            question.answer.refuse = isRefuse( question.value );
          },

          // Returns true if complete, false if not and the option ID if an option's extra data is missing
          questionIsComplete: function( question ) {
            // comment questions are always complete
            if( 'comment' == question.type ) return true;

            // hidden questions are always complete
            if( !this.evaluate( question.precondition ) ) return true;

            // null values are never complete
            if( null == question.value ) return false;

            // dkna/refuse questions are always complete
            if( isDknaOrRefuse( question.value ) ) return true;

            if( 'list' == question.type ) {
              // get the list of all preconditions for all options belonging to this question
              var preconditionListById = question.optionList.reduce( function( object, option ) {
                object[option.id] = option.precondition;
                return object;
              }, {} );

              // make sure that any selected item with extra data has provided that data
              for( var index = 0; index < question.value.length; index++ ) {
                var selectedOption = question.value[index];
                var selectedOptionId = angular.isObject( selectedOption ) ? selectedOption.id : selectedOption;

                if( angular.isObject( selectedOption ) ) {
                  if( this.evaluate( preconditionListById[selectedOptionId] ) && (
                      ( angular.isArray( selectedOption.value ) && 0 == selectedOption.value.length ) ||
                      null == selectedOption.value
                  ) ) return null == selectedOption.value ? selectedOption.id : false;
                }
              }

              // make sure there is at least one selected option
              for( var index = 0; index < question.value.length; index++ ) {
                var selectedOption = question.value[index];
                var selectedOptionId = angular.isObject( selectedOption ) ? selectedOption.id : selectedOption;

                if( this.evaluate( preconditionListById[selectedOptionId] ) ) {
                  if( angular.isObject( selectedOption ) ) {
                    if( angular.isArray( selectedOption.value ) ) {
                      // make sure there is at least one option value
                      for( var valueIndex = 0; valueIndex < selectedOption.value.length; valueIndex++ )
                        if( null != selectedOption.value[valueIndex] ) return true;
                    } else if( null != selectedOption.value ) return true;
                  } else if( null != selectedOption ) return true;
                }
              }

              return false;
            }

            return true;
          },

          evaluateLimit: function( limit ) { return this.evaluate( limit, true ); },
          evaluate: function( expression, isLimit ) {
            if( angular.isUndefined( isLimit ) ) isLimit = false;

            // handle empty expressions
            if( null == expression ) return isLimit ? null : true;

            if( isLimit ) {
              expression = expression.replace( /\bcurrent_year\b/, moment().format( 'YYYY' ) );
              expression = expression.replace( /\bcurrent_month\b/, moment().format( 'MM' ) );
              expression = expression.replace( /\bcurrent_day\b/, moment().format( 'DD' ) );
            }

            // non-limit boolean expressions are already evaluated
            if( !isLimit && true == expression || false == expression ) return expression;

            // replace any attributes
            if( 'respondent' != self.parentModel.getSubjectFromState() ) {
              self.activeAttributeList.forEach( function( attribute ) {
                var qualifier = 'showhidden' == attribute.name ? '\\b' : '@';
                var re = new RegExp( qualifier + attribute.name + qualifier );
                var value = attribute.value;
                if( null == value ) {
                  // do nothing
                } else if( '' == value ) {
                  value = null;
                } else if( 'true' == value ) {
                  value = true;
                } else if( 'false' == value ) {
                  value = false;
                } else {
                  var num = parseFloat( value );
                  if( num == value ) value = num;
                  else value = '"' + value + '"';
                }

                expression = expression.replace( re, value );
              } );

              // replace any remaining expressions with null
              expression = expression.replace( /@[^@]+@/g, isLimit ? null : 'null' );
            }

            // everything else needs to be evaluated
            var matches = expression.match( /\$[^$]+\$/g );
            if( null != matches ) matches.forEach( function( match ) {
              var parts = match.slice( 1, -1 ).toLowerCase().split( '.' );
              var fnName = 1 < parts.length ? parts[1] : null;

              var subparts = parts[0].toLowerCase().split( ':' );
              var questionName = subparts[0];
              var optionName = null;
              if( 1 < subparts.length ) {
                if( 'count()' == subparts[1] ) fnName = 'count()';
                else optionName = subparts[1];
              }

              // find the referenced question
              var matchedQuestion = null;
              self.questionList.some( function( q ) {
                if( questionName == q.name.toLowerCase() ) {
                  matchedQuestion = q;
                  return true;
                }
              } );

              var compiled = 'null';
              if( null != matchedQuestion ) {
                if( 'empty()' == fnName ) {
                  compiled = null == matchedQuestion.value ? 'true' : 'false';
                } else if( 'dkna()' == fnName ) {
                  compiled = isDkna( matchedQuestion.value ) ? 'true' : 'false';
                } else if( 'refuse()' == fnName ) {
                  compiled = isRefuse( matchedQuestion.value ) ? 'true' : 'false';
                } else if( 'count()' == fnName ) {
                  compiled = angular.isArray( matchedQuestion.value ) ? matchedQuestion.value.length : 0;
                } else if( 'boolean' == matchedQuestion.type ) {
                  if( true === matchedQuestion.value ) compiled = 'true';
                  else if( false === matchedQuestion.value ) compiled = 'false';
                } else if( 'number' == matchedQuestion.type ) {
                  if( angular.isNumber( matchedQuestion.value ) ) compiled = matchedQuestion.value;
                } else if( 'date' == matchedQuestion.type ) {
                  if( angular.isString( matchedQuestion.value ) ) compiled = matchedQuestion.value;
                } else if( 'string' == matchedQuestion.type ) {
                  if( angular.isString( matchedQuestion.value ) ) compiled = "'" + matchedQuestion.value.replace( /'/g, "\\'" ) + "'";
                } else if( 'list' == matchedQuestion.type ) {
                  // find the referenced option
                  var matchedOption = null;
                  matchedQuestion.optionList.some( function( o ) {
                    if( optionName == o.name.toLowerCase() ) {
                      matchedOption = o;
                      return true;
                    }
                  } );

                  if( null != matchedOption && angular.isArray( matchedQuestion.value ) ) {
                    if( null == matchedOption.extra ) {
                      compiled = matchedQuestion.value.includes( matchedOption.id ) ? 'true' : 'false';
                    } else {
                      var answer = matchedQuestion.value.findByProperty( 'id', matchedOption.id );
                      if( !angular.isObject( answer ) ) {
                        compiled = 'false';
                      } else {
                        if( matchedOption.multiple_answers ) {
                          // make sure at least one of the answers isn't null
                          compiled = answer.value.some( v => v != null ) ? 'true' : 'false';
                        } else {
                          compiled = null != answer.value ? 'true' : 'false';
                        }
                      }
                    }
                  }
                }
              }

              expression = expression.replace( match, compiled );
            } );

            if( isLimit ) return expression;

            // create a function which can be used to evaluate the compiled precondition without calling eval()
            function evaluateExpression( precondition ) {
              return Function('"use strict"; return ' + precondition + ';')();
            }
            return evaluateExpression( expression );
          },

          getVisibleQuestionList: function() {
            return this.questionList.filter( question => self.evaluate( question.precondition ) );
          },

          getVisibleOptionList: function( question ) {
            return question.optionList.filter( option => self.evaluate( option.precondition ) );
          },

          reset: function() {
            angular.extend( this, {
              questionList: [],
              keyQuestionIndex: null,
              activeAttributeList: [],
              prevModuleList: [],
              nextModuleList: []
            } );
          },

          onLoad: function() {
            function getAttributeNames( precondition ) {
              // scan the precondition for active attributes (also include the showhidden constant)
              var list = [];
              if( angular.isString( precondition ) ) {
                var matches = precondition.match( /@[^@]+@|\bshowhidden\b/g );
                if( null != matches && 0 < matches.length ) list = matches.map( m => m.replace( /@/g, '' ) );
              }
              return list;
            }

            this.reset();
            return CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourceBasePath() + '/question',
              data: {
                select: { column: [
                  'rank', 'name', 'type', 'mandatory', 'dkna_refuse', 'minimum', 'maximum', 'precondition', 'prompts', 'popups'
                ] },
                modifier: { order: 'question.rank' }
              }
            } ).query().then( function( response ) {
              var promiseList = [];
              self.questionList = response.data;

              // set the current language to the first question's language
              if( 0 < self.questionList.length && angular.isDefined( self.questionList[0].language ) ) {
                self.currentLanguage = self.questionList[0].language;
                cenozoApp.setLang( self.currentLanguage );
              }

              // if in debug mode then get a list of all modules before and after the current
              if( self.parentModel.viewModel.record.debug ) {
                CnHttpFactory.instance( {
                  path: ['qnaire', self.parentModel.viewModel.record.qnaire_id , 'module'].join( '/' ),
                  data: {
                    select: { column: [ 'id', 'rank', 'name' ] },
                    module: { order: 'module.rank' }
                  }
                } ).query().then( function( response ) {
                  var foundCurrentModule = false;
                  response.data.forEach( function( module ) {
                    if( !foundCurrentModule && module.id == self.parentModel.viewModel.record.module_id ) {
                      foundCurrentModule = true
                    } else {
                      if( foundCurrentModule ) self.nextModuleList.push( module );
                      else self.prevModuleList.push( module );
                    }
                  } );
                } );
              }

              var activeAttributeList = [];
              self.questionList.forEach( function( question, questionIndex ) {
                question.incomplete = false;
                question.prompts = CnTranslationHelper.parseDescriptions( question.prompts );
                question.popups = CnTranslationHelper.parseDescriptions( question.popups );
                question.value = angular.fromJson( question.value );
                question.backupValue = angular.copy( question.value );
                activeAttributeList = activeAttributeList.concat( getAttributeNames( question.precondition ) );

                // make sure we have the first non-comment question set as the first key question
                if( null == self.keyQuestionIndex && 'comment' != question.type ) self.keyQuestionIndex = questionIndex;

                // if the question is a list type then get the options
                if( 'list' == question.type ) {
                  promiseList.push( CnHttpFactory.instance( {
                    path: ['question', question.id, 'question_option'].join( '/' ) + (
                      'respondent' == self.parentModel.getSubjectFromState() ? '?token=' + $state.params.token : ''
                    ),
                    data: {
                      select: { column: [
                        'name', 'exclusive', 'extra', 'multiple_answers', 'minimum', 'maximum', 'precondition', 'prompts', 'popups'
                      ] },
                      modifier: { order: 'question_option.rank' }
                    }
                  } ).query().then( function( response ) {
                    question.optionList = response.data;
                    question.optionList.forEach( function( option ) {
                      activeAttributeList = activeAttributeList.concat( getAttributeNames( option.precondition ) );
                      option.prompts = CnTranslationHelper.parseDescriptions( option.prompts );
                      option.popups = CnTranslationHelper.parseDescriptions( option.popups );
                      self.optionListById[option.id] = option;
                    } );
                  } ) );
                }
              } );

              return $q.all( promiseList ).then( function() {
                self.questionList.forEach( question => self.convertValueToModel( question ) );

                // sort active attribute and make a unique list
                self.activeAttributeList = activeAttributeList
                  .sort()
                  .filter( ( attribute, index, array ) => index === array.indexOf( attribute ) )
                  .map( attribute => ( { name: attribute, value: null } ) );
              } );
            } );
          },

          setLanguage: function() {
            cenozoApp.setLang( self.currentLanguage );
            if( 'respondent' == this.parentModel.getSubjectFromState() && null != this.currentLanguage ) {
              return this.runQuery( function() {
                return CnHttpFactory.instance( {
                  path: self.parentModel.getServiceResourceBasePath().replace( 'page/', 'respondent/' ) +
                    '?action=set_language&code=' + self.currentLanguage
                } ).patch();
              } );
            }
          },

          onKeyup: function( key ) {
            $q.all( this.writePromiseList ).then( function() {
              // proceed to the next page when the enter key is clicked
              if( 'enter' == key ) {
                self.proceed();
                return;
              }

              // do nothing if we have no key question index (which means the page only has comments)
              if( null == self.keyQuestionIndex ) return;

              var question = self.questionList[self.keyQuestionIndex];

              if( 'boolean' == question.type ) {
                var value = undefined;
                if( 1 == key ) value = question.answer.yes ? null: true;
                else if( 2 == key ) value = question.answer.no ? null : false;
                else if( 3 == key ) value = question.answer.dkna ? null : { dkna: true };
                else if( 4 == key ) value = question.answer.refuse ? null : { refuse: true };

                if( angular.isDefined( value ) ) self.setAnswer( question, value );
              } else if( 'list' == question.type ) {
                // check if the key is within the option list or the 2 dkna/refuse options
                if( key <= question.optionList.length ) {
                  var option = question.optionList[key-1];
                  if( question.answer.optionList[option.id].selected ) self.removeOption( question, option );
                  else self.addOption( question, option );
                } else if( key == question.optionList.length + 1 ) {
                  self.setAnswer( question, question.answer.dkna ? null : { dkna: true } );
                } else if( key == question.optionList.length + 2 ) {
                  self.setAnswer( question, question.answer.refuse ? null : { refuse: true } );
                }
              } else {
                // 1 is dkna and 2 is refuse
                if( 1 == key ) self.setAnswer( question, question.answer.dkna ? null : { dkna: true } );
                else if( 2 == key ) self.setAnswer( question, question.answer.refuse ? null : { refuse: true } );
              }

              // advance to the next non-comment question, looping back to the first when we're at the end of the list
              do {
                self.keyQuestionIndex++;
                if( self.keyQuestionIndex == self.questionList.length ) self.keyQuestionIndex = 0;
              } while( 'comment' == self.questionList[self.keyQuestionIndex].type );
            } );
          },

          setAnswer: function( question, value, noCompleteCheck ) {
            if( angular.isUndefined( noCompleteCheck ) ) noCompleteCheck = false;

            // if the question's type is a number then make sure it falls within the min/max values
            var minimum = this.evaluateLimit( question.minimum );
            var maximum = this.evaluateLimit( question.maximum );
            var tooSmall = 'number' == question.type &&
                           null != value &&
                           !angular.isObject( value ) &&
                           ( null != minimum && value < minimum );
            var tooLarge = 'number' == question.type &&
                           null != value &&
                           !angular.isObject( value ) &&
                           ( null != maximum && value > maximum );

            return this.runQuery(
              tooSmall || tooLarge ?

              // When the number is out of bounds then alert the user
              function() {
                return CnModalMessageFactory.instance( {
                  title: self.text( tooSmall ? 'misc.minimumTitle' : 'misc.maximumTitle' ),
                  message: self.text( 'misc.limitMessage' ) + ' ' + (
                    null == maximum ? self.text( 'misc.equalOrGreater' ) + ' ' + minimum + '.' :
                    null == minimum ? self.text( 'misc.equalOrLess' ) + ' ' + maximum + '.' :
                    [self.text( 'misc.between' ), minimum, self.text( 'misc.and' ), maximum + '.'].join( ' ' )
                  )
                } ).show().then( function() {
                  question.value = angular.copy( question.backupValue );
                  self.convertValueToModel( question );
                } );
              } :

              // No out of bounds detected, so proceed with setting the value
              function() {
                // Note that we need to treat entering text values a bit differently than other question types.
                // Some participants may wish to fill in a value after they have already selected dkna or refuse.
                // When entering their text they may then immediatly click the selected dkna/refuse button to
                // cancel its selection.  To prevent this button press from immediately clearing their text anwer
                // we must briefly ignore the answer for this question being set to null.
                if( 'text' == question.type ) {
                  if( angular.isString( value ) ) {
                    var ignore = null;
                    if( isDkna( question.value ) ) ignore = 'ignoreDkna';
                    else if( isRefuse( question.value ) ) ignore = 'ignoreRefuse';
                    if( null != ignore ) { question[ignore] = true; }
                    $timeout( function() { if( null != ignore ) delete question[ignore]; }, 500 );
                  } else if(
                    question.ignoreDkna && ( null === value || isDkna( value ) ) ||
                    question.ignoreRefuse && ( null === value || isRefuse( value ) )
                  ) {
                    // we may have tried setting dkna or refuse when it should be ignored, so change it in the model
                    self.convertValueToModel( question );
                    return $q.all().finally( function() { self.working = false; } );
                  }
                }

                self.working = true;
                if( "" === value ) value = null;
                var promise = 'respondent' == self.parentModel.getSubjectFromState() ?
                  // first communicate with the server (if we're working with a respondent)
                  CnHttpFactory.instance( {
                    path: 'answer/' + question.answer_id,
                    data: { value: angular.toJson( value ) },
                    onError: function() {
                      question.value = angular.copy( question.backupValue );
                      self.convertValueToModel( question );
                    }
                  } ).patch() : $q.all();

                return promise.then( function() {
                  question.value = value;
                  question.backupValue = angular.copy( question.value );
                  self.convertValueToModel( question );

                  // now blank out answers to questions which are no longer visible (this is done automatically on the server side)
                  var visibleQuestionList = self.getVisibleQuestionList();
                  self.questionList.forEach( function( q ) {
                    if( null == visibleQuestionList.findByProperty( 'id', q.id ) ) {
                      // q isn't visible so set its value to null if it isn't already
                      if( null != q.value ) {
                        q.value = null;
                        self.convertValueToModel( q );
                      }
                    } else {
                      // q is visible, now check its options (assuming we haven't selected dkna/refused)
                      if( 'list' == q.type && !isDknaOrRefuse( q.value ) ) {
                        var visibleOptionList = self.getVisibleOptionList( q );
                        q.optionList.forEach( function( o ) {
                          if( null == visibleOptionList.findByProperty( 'id', o.id ) ) {
                            // o isn't visible so make sure it isn't selected
                            var v = angular.isArray( q.value ) ? q.value : [];
                            var i = searchOptionList( v, o.id );
                            if( null != i ) v.splice( i, 1 );
                            if( 0 == v.length ) v = null;

                            q.value = v;
                            self.convertValueToModel( q );
                          }
                        } );
                      }
                    }
                  } );

                  if( !noCompleteCheck ) {
                    var complete = self.questionIsComplete( question );
                    question.incomplete = false === complete ? true
                                        : true === complete ? false
                                        : complete;
                  }

                  /*
                  NOTE: The "element.value = null" line below was causing answers in different questions on the same page to blank out.
                  By removing the following code the problem goes away, but it isn't clear if removing this code will cause some other
                  bug.  If the code needs to be re-added then make sure that when there are two questions on the same page, both which
                  have the option to add a value to an option that clicking one of the options doesn't remove the answer to the already
                  selected option's value in the other question.

                  if( 'respondent' == self.parentModel.getSubjectFromState() ) {
                    if( 'list' == question.type ) {
                      for( var element of document.getElementsByName( 'answerValue' ) ) {
                        var match = element.id.match( /option([0-9]+)value[0-9]+/ );
                        if( 1 < match.length ) {
                          var optionId = match[1];
                          if( null == searchOptionList( question.value, optionId ) ) element.value = null;
                        }
                      }
                    }
                  }
                  */
                } ).finally( function() { self.working = false; } );
              }
            );
          },

          getValueForNewOption: function( question, option ) {
            var data = option.extra ? { id: option.id, value: option.multiple_answers ? [] : null } : option.id;
            var value = [];
            if( option.exclusive ) {
              value.push( data );
            } else {
              // get the current value array, remove exclusive options, add the new option and sort
              if( angular.isArray( question.value ) ) value = question.value;
              value = value.filter( o => !self.optionListById[angular.isObject(o) ? o.id : o].exclusive );
              if( null == searchOptionList( value, option.id ) ) value.push( data );
              value.sort( function(a,b) { return ( angular.isObject( a ) ? a.id : a ) - ( angular.isObject( b ) ? b.id : b ); } );
            }

            return value;
          },

          addOption: function( question, option ) {
            this.setAnswer( question, this.getValueForNewOption( question, option ) ).then( function() {
              // if the option has extra data then focus its associated input
              if( null != option.extra ) {
                $timeout( function() { document.getElementById( 'option' + option.id + 'value0' ).focus(); }, 50 );
              }
            } );
          },

          removeOption: function( question, option ) {
            // get the current value array and remove the option from it
            var value = angular.isArray( question.value ) ? question.value : [];
            var optionIndex = searchOptionList( value, option.id );
            if( null != optionIndex ) value.splice( optionIndex, 1 );
            if( 0 == value.length ) value = null;

            this.setAnswer( question, value );
          },

          addAnswerValue: function( question, option ) {
            var value = angular.isArray( question.value ) ? question.value : [];
            var optionIndex = searchOptionList( value, option.id );
            if( null == optionIndex ) {
              value = this.getValueForNewOption( question, option );
              optionIndex = searchOptionList( value, option.id );
            }

            var valueIndex = value[optionIndex].value.indexOf( null );
            if( -1 == valueIndex ) valueIndex = value[optionIndex].value.push( null ) - 1;
            this.setAnswer( question, value, true ).then( function() {
              // focus the new answer value's associated input
              $timeout( function() { document.getElementById( 'option' + option.id + 'value' + valueIndex ).focus(); }, 50 );
            } );
          },

          removeAnswerValue: function( question, option, valueIndex ) {
            var value = question.value;
            var optionIndex = searchOptionList( value, option.id );
            value[optionIndex].value.splice( valueIndex, 1 );
            if( 0 == value[optionIndex].value.length ) value.splice( optionIndex, 1 );
            if( 0 == value.length ) value = null;

            this.setAnswer( question, value, true );
          },

          selectDateForOption: function( question, option, valueIndex, answerValue ) {
            CnModalDatetimeFactory.instance( {
              locale: self.currentLanguage,
              date: answerValue,
              pickerType: 'date',
              minDate: getDate( this.evaluateLimit( option.minimum ) ),
              maxDate: getDate( this.evaluateLimit( option.maximum ) ),
              emptyAllowed: true
            } ).show().then( function( response ) {
              if( false !== response ) self.setAnswerValue(
                question,
                option,
                valueIndex,
                null == response ? null : response.replace( /T.*/, '' )
              );
            } );
          },

          selectDate: function( question, value ) {
            CnModalDatetimeFactory.instance( {
              locale: self.currentLanguage,
              date: value,
              pickerType: 'date',
              minDate: getDate( this.evaluateLimit( question.minimum ) ),
              maxDate: getDate( this.evaluateLimit( question.maximum ) ),
              emptyAllowed: true
            } ).show().then( function( response ) {
              if( false !== response ) self.setAnswer(
                question,
                null == response ? null : response.replace( /T.*/, '' )
              );
            } );
          },

          setAnswerValue: function( question, option, valueIndex, answerValue ) {
            // if the question option's extra type is a number then make sure it falls within the min/max values
            var minimum = this.evaluateLimit( option.minimum );
            var maximum = this.evaluateLimit( option.maximum );
            var tooSmall = 'number' == option.extra && null != answerValue && ( null != minimum && answerValue < minimum );
            var tooLarge = 'number' == option.extra && null != answerValue && ( null != maximum && answerValue > maximum );

            if( tooSmall || tooLarge ) {
              this.runQuery( function() {
                return CnModalMessageFactory.instance( {
                  title: self.text( tooSmall ? 'misc.minimumTitle' : 'misc.maximumTitle' ),
                  message: self.text( 'misc.limitMessage' ) + ' ' + (
                    null == maximum ? self.text( 'misc.equalOrGreater' ) + ' ' + minimum + '.' :
                    null == minimum ? self.text( 'misc.equalOrLess' ) + ' ' + maximum + '.' :
                    [self.text( 'misc.between' ), minimum, self.text( 'misc.and' ), maximum + '.'].join( ' ' )
                  )
                } ).show().then( function() {
                  // put the old value back
                  var element = document.getElementById( 'option' + option.id + 'value' + valueIndex );
                  element.value = question.answer.optionList[option.id].valueList[valueIndex];
                } );
              } );
            } else {
              var value = question.value;
              var optionIndex = searchOptionList( value, option.id );
              if( null != optionIndex ) {
                if( option.multiple_answers ) {
                  if( null == answerValue || ( angular.isString( answerValue ) && 0 == answerValue.trim().length ) ) {
                    // if the value is blank then remove it
                    value[optionIndex].value.splice( valueIndex, 1 );
                  } else {
                    // does the value already exist?
                    var existingValueIndex = value[optionIndex].value.indexOf( answerValue );
                    if( 0 <= existingValueIndex ) {
                      // don't add the answer, instead focus on the existing one and highlight it
                      document.getElementById( 'option' + option.id + 'value' + valueIndex ).value = null;
                      var element = document.getElementById( 'option' + option.id + 'value' + existingValueIndex );
                      element.focus();
                      element.select();
                    } else {
                      value[optionIndex].value[valueIndex] = answerValue;
                    }
                  }
                } else {
                  value[optionIndex].value = '' !== answerValue ? answerValue : null;

                  if( 'date' == option.extra )
                    question.answer.optionList[option.id].formattedValueList[valueIndex] = formatDate( value[optionIndex].value );
                }
              }

              this.setAnswer( question, value );
            }
          },

          viewPage: function() {
            $state.go(
              'page.view',
              { identifier: this.parentModel.viewModel.record.getIdentifier() },
              { reload: true }
            );
          },

          renderPreviousPage: function() {
            $state.go(
              'page.render',
              { identifier: this.parentModel.viewModel.record.previous_id },
              { reload: true }
            );
          },

          renderNextPage: function() {
            $state.go(
              'page.render',
              { identifier: this.parentModel.viewModel.record.next_id },
              { reload: true }
            );
          },

          proceed: function() {
            this.working = true;

            // check to make sure that all questions are complete, and highlight any which aren't
            var mayProceed = true;
            this.questionList.some( function( question ) {
              var complete = self.questionIsComplete( question );
              question.incomplete = false === complete ? true
                                  : true === complete ? false
                                  : complete;
              if( question.incomplete ) {
                mayProceed = false;
                return true;
              }
            } );

            if( mayProceed ) {
              // proceed to the respondent's next valid page
              return this.runQuery( function() {
                return CnHttpFactory.instance( {
                  path: 'respondent/token=' + $state.params.token + '?action=proceed'
                } ).patch().then( function() {
                  return self.parentModel.reloadState( true );
                } ).finally( function() { self.working = false; } );
              } );
            } else {
              self.working = false;
            }
          },

          backup: function() {
            // back up to the respondent's previous page
            this.working = true;
            return this.runQuery( function() {
              return CnHttpFactory.instance( {
                path: 'respondent/token=' + $state.params.token + '?action=backup'
              } ).patch().then( function() {
                return self.parentModel.reloadState( true );
              } ).finally( function() { self.working = false; } );
            } );
          },

          jump: function( moduleId ) {
            // jump to the first page in the provided module
            this.working = true;
            return this.runQuery( function() {
              return CnHttpFactory.instance( {
                path: 'respondent/token=' + $state.params.token + '?action=jump&module_id=' + moduleId,
              } ).patch().then( function() {
                return self.parentModel.reloadState( true );
              } ).finally( function() { self.working = false; } );
            } );
          },

          text: function( address ) {
            return CnTranslationHelper.translate( address, this.currentLanguage );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  // extend the add factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnPageAddFactory', [
    '$delegate', 'CnSession',
    function( $delegate, CnSession ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel ) {
        var object = instance( parentModel );

        // see if the form has a record in the data-entry module
        var onNew = object.onNew;
        angular.extend( object, {
          onNew: function( record ) {
            return onNew( record ).then( function() {
              // set the default page max time
              if( angular.isUndefined( record.max_time ) ) record.max_time = CnSession.setting.defaultPageMaxTime;
            } );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );

  // extend the view factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnPageViewFactory', [
    '$delegate', '$filter', 'CnTranslationHelper',
    function( $delegate, $filter, CnTranslationHelper ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        var object = instance( parentModel, root );

        // see if the form has a record in the data-entry module
        angular.extend( object, {
          onView: function( force ) {
            var self = this;
            return this.$$onView( force ).then( function() {
              self.record.average_time = $filter( 'cnSeconds' )( Math.round( self.record.average_time ) );
              self.record.prompts = CnTranslationHelper.parseDescriptions( self.record.prompts );
              self.record.popups = CnTranslationHelper.parseDescriptions( self.record.popups );
              self.record.module_prompts = CnTranslationHelper.parseDescriptions( self.record.module_prompts );
              self.record.module_popups = CnTranslationHelper.parseDescriptions( self.record.module_popups );
            } );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );

  // extend the base model factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnPageModelFactory', [
    '$delegate', 'CnPageRenderFactory', '$state',
    function( $delegate, CnPageRenderFactory, $state ) {
      function extendModelObject( object ) {
        angular.extend( object, {
          renderModel: CnPageRenderFactory.instance( object ),

          getServiceResourceBasePath: function( resource ) {
            // when we're looking at a respondent use its token to figure out which page to load
            return 'respondent' == this.getSubjectFromState() ?
              'page/token=' + $state.params.token : this.$$getServiceResourcePath( resource );
          },

          getServiceResourcePath: function( resource ) { return this.getServiceResourceBasePath( resource ); },

          getServiceCollectionPath: function( ignoreParent ) {
            var path = this.$$getServiceCollectionPath( ignoreParent );
            if( 'respondent' == this.getSubjectFromState() )
              path = path.replace( 'respondent/undefined', 'module/token=' + $state.params.token );
            return path;
          }
        } );
        return object;
      }

      var instance = $delegate.instance;
      $delegate.root = extendModelObject( $delegate.root );
      $delegate.instance = function( parentModel, root ) { return extendModelObject( instance( root ) ); };

      return $delegate;
    }
  ] );
} );
