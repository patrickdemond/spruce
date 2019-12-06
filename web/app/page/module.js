define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'page', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module );

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'module',
        column: 'module.id'
      }
    },
    name: {
      singular: 'page',
      plural: 'pages',
      possessive: 'page\'s'
    },
    columnList: {
      rank: {
        title: 'Rank',
        type: 'rank'
      },
      has_precondition: {
        title: 'Precondition',
        type: 'boolean'
      },
      name: {
        title: 'Name'
      }
    },
    defaultOrder: {
      column: 'rank',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    rank: {
      title: 'Rank',
      type: 'rank'
    },
    name: {
      title: 'Name',
      type: 'string'
    },
    precondition: {
      title: 'Precondition',
      type: 'text',
      help: 'A special expression which restricts whether or not to show this page.'
    },
    note: {
      title: 'Note',
      type: 'text'
    },

    qnaire_id: { column: 'qnaire.id', isExcluded: true },
    qnaire_name: { column: 'qnaire.name', isExcluded: true },
    base_language: { column: 'base_language.code', isExcluded: true },
    descriptions: { isExcluded: true },
    module_descriptions: { isExcluded: true },
    module_id: { isExcluded: true },
    module_name: { column: 'module.name', isExcluded: true }
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

  // used by services below to convert a list of descriptions into an object
  function parseDescriptions( descriptionList ) {
    var code = null;
    return descriptionList.split( '`' ).reduce( function( list, part ) {
      if( null == code ) {
        code = part;
      } else {
        list[code] = part;
        code = null;
      }
      return list;
    }, {} );
  }

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
  cenozo.providers.directive( 'cnPageAdd', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageList', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
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

          // bind keyup (first unbind to prevent duplicates)
          $document.unbind( 'keyup.render' );
          $document.bind( 'keyup.render', function( event ) {
            if( isNumpadInput( event ) ) {
              event.stopPropagation();
              $scope.model.renderModel.onKeyup( 13 == event.which ? 'enter' : event.which - 96 );
              $scope.$apply();
            }
          } );

          // prevent numpad keys from entering into inputs and textareas
          $scope.validateKeydown = function( event ) {
            if( isNumpadInput( event ) ) {
              event.returnValue = false;
              event.preventDefault();
            }
          };

          if( angular.isUndefined( $scope.progress ) ) $scope.progress = 0;

          $scope.text = function( address, language ) {
            return CnTranslationHelper.translate( address, $scope.model.renderModel.currentLanguage );
          };

          function render() {
            var promiseList = [];
            if( 'response' != $scope.model.getSubjectFromState() || null != $scope.data.page_id ) promiseList.push(
              $scope.model.viewModel.onView( true ).then( function() {
                $scope.data = {
                  page_id: $scope.model.viewModel.record.id,
                  qnaire_id: $scope.model.viewModel.record.qnaire_id,
                  qnaire_name: $scope.model.viewModel.record.qnaire_name,
                  base_language: $scope.model.viewModel.record.base_language,
                  title: $scope.model.viewModel.record.module_name,
                  uid: null
                };

                $scope.progress = Math.round(
                  100 * $scope.model.viewModel.record.qnaire_page / $scope.model.viewModel.record.qnaire_pages
                );
                return $scope.model.renderModel.onLoad();
              } )
            );

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
              }, {
                title: $scope.data.title
              } ] );

              if( null == $scope.model.renderModel.currentLanguage )
                $scope.model.renderModel.currentLanguage = $scope.data.base_language;

              $scope.isComplete = true;
            } );
          }

          if( 'response' != $scope.model.getSubjectFromState() ) render();
          else {
            // test to see if the response has a current page
            CnHttpFactory.instance( {
              path: 'response/token=' + $state.params.token,
              data: { select: { column: [
                'qnaire_id', 'page_id', 'submitted', 'introductions', 'conclusions',
                { table: 'participant', column: 'uid' },
                { table: 'language', column: 'code', alias: 'base_language' },
                { table: 'qnaire', column: 'name', alias: 'qnaire_name' },
                { table: 'module', column: 'name', alias: 'module_name' }
              ] } },
              onError: function( response ) {
                $state.go( 'error.' + response.status, response );
              }
            } ).get().then( function( response ) {
              $scope.data = response.data;
              $scope.data.introductions = parseDescriptions( $scope.data.introductions );
              $scope.data.conclusions = parseDescriptions( $scope.data.conclusions );
              $scope.data.title = null != $scope.data.module_name
                                ? $scope.data.module_name
                                : $scope.data.submitted
                                ? 'Conclusion'
                                : 'Introduction';
              render();
            } );
          }
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageView', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageRenderFactory', [
    'CnHttpFactory', 'CnModalMessageFactory', '$q', '$state', '$document', '$transitions', '$timeout',
    function( CnHttpFactory, CnModalMessageFactory, $q, $state, $document, $transitions, $timeout ) {
      var object = function( parentModel ) {
        var self = this;

        angular.extend( this, {
          parentModel: parentModel,
          questionList: [],
          optionListById: {},
          currentLanguage: null,
          keyQuestionIndex: null,
          pageComplete: false,
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
                                  ? { valueList: [] }
                                  : { selected: null != optionIndex };

                  if( option.extra ) {
                    if( null != optionIndex ) {
                      list[option.id].valueList = option.multiple_answers
                                                ? selectedOptions[optionIndex].value
                                                : [selectedOptions[optionIndex].value];
                    } else {
                      list[option.id].valueList = option.multiple_answers ? [] : [null];
                    }
                  }

                  return list;
                }, {} )
              }
            } else {
              question.answer = {
                value: angular.isString( question.value ) || angular.isNumber( question.value ) ? question.value : null
              };
            }

            question.answer.dkna = angular.isObject( question.value ) && true === question.value.dkna;
            question.answer.refuse = angular.isObject( question.value ) && true === question.value.refuse;
          },

          pageIsDone: function() {
            return !this.questionList.some( function( question ) {
              // null values are never complete
              if( null == question.value ) return true;

              if( 'list' == question.type ) {
                // extra options without a value don't count as an answer
                return angular.isArray( question.value ) && question.value.some( function( o ) {
                  var option = self.optionListById[angular.isObject( o ) ? o.id : o];
                  if( option.extra ) {
                    if( option.multiple_answers ) {
                      // make sure there is at least one non null value
                      return !angular.isArray( o.value ) || !o.value.some( function( value ) { return null != value; } );
                    } else {
                      // make sure the value is not null
                      return null == o.value;
                    }
                  } else {
                    // make sure the option is not null
                    return null == o;
                  }
                } );
              }
            } );
          },

          onLoad: function() {
            return CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '/question',
              data: { select: { column: [ 'rank', 'name', 'type', 'mandatory', 'dkna_refuse', 'minimum', 'maximum', 'descriptions' ] } }
            } ).query().then( function( response ) {
              var promiseList = [];
              angular.extend( self, {
                questionList: response.data,
                keyQuestionIndex: null,
                pageComplete: false
              } );

              // set the current language to the first question's language
              if( 0 < self.questionList.length && angular.isDefined( self.questionList[0].language ) ) {
                self.currentLanguage = self.questionList[0].language;
              }

              self.questionList.forEach( function( question, questionIndex ) {
                question.descriptions = parseDescriptions( question.descriptions );
                question.value = angular.fromJson( question.value );
                question.backupValue = angular.copy( self.value );

                // make sure we have the first non-comment question set as the first key question
                if( null == self.keyQuestionIndex && 'comment' != question.type ) self.keyQuestionIndex = questionIndex;

                // if the question is a list type then get the options
                if( 'list' == question.type ) {
                  promiseList.push( CnHttpFactory.instance( {
                    path: ['question', question.id, 'question_option'].join( '/' ),
                    data: {
                      select: { column: ['name', 'exclusive', 'extra', 'multiple_answers', 'descriptions'] },
                      modifier: { order: 'question_option.rank' }
                    }
                  } ).query().then( function( response ) {
                    question.optionList = response.data;
                    question.optionList.forEach( function( option ) {
                      option.descriptions = parseDescriptions( option.descriptions );
                      self.optionListById[option.id] = option;
                    } );
                  } ) );
                }
              } );

              return $q.all( promiseList ).then( function() {
                self.questionList.forEach( question => self.convertValueToModel( question ) );
                self.pageComplete = self.pageIsDone();
              } );
            } );
          },

          setLanguage: function() {
            if( 'response' == this.parentModel.getSubjectFromState() && null != this.currentLanguage ) {
              return this.runQuery( function() {
                return CnHttpFactory.instance( {
                  path: self.parentModel.getServiceResourcePath().replace( 'page/', 'response/' ) +
                    '?action=set_language&code=' + self.currentLanguage
                } ).patch();
              } );
            }
          },

          onKeyup: function( key ) {
            $q.all( this.writePromiseList ).then( function() {
              // proceed to the next page when the enter key is clicked
              if( 'enter' == key ) {
                if( self.pageComplete ) self.proceed();
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
                var noAnswerType = 1 == key ? 'dkna'
                           : 2 == key ? 'refuse'
                           : null;

                if( null != noAnswerType ) {
                  data[noAnswerType] = !data[noAnswerType];
                  self.setAnswer( noAnswerType, question );
                }
              }

              // advance to the next non-comment question, looping back to the first when we're at the end of the list
              do {
                self.keyQuestionIndex++;
                if( self.keyQuestionIndex == self.questionList.length ) self.keyQuestionIndex = 0;
              } while( 'comment' == self.questionList[self.keyQuestionIndex].type );
            } );
          },

          setAnswer: function( question, value ) {
            if( "" === value ) value = null;

            // first communicate with the server (if we're working with a response)
            if( 'response' == self.parentModel.getSubjectFromState() ) {
              return this.runQuery( function() {
                return CnHttpFactory.instance( {
                  path: 'answer/' + question.answer_id,
                  data: { value: angular.toJson( value ) }
                } ).patch().then( function() {
                  question.backupValue = angular.copy( question.value );
                  question.value = value;
                  self.pageComplete = self.pageIsDone();
                  self.convertValueToModel( question );

                  if( 'list' == question.type ) {
                    for( var element of document.getElementsByName( 'answerValue' ) ) {
                      var match = element.id.match( /option([0-9]+)value[0-9]+/ );
                      if( 1 < match.length ) {
                        var optionId = match[1];
                        if( null == searchOptionList( question.value, optionId ) ) element.value = null;
                      }
                    }
                  }
                } );
              } );
            }

            return $q.all();
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
            console.log( 'add', question.id, option.id );
            this.setAnswer( question, this.getValueForNewOption( question, option ) ).then( function() {
              // if the option has extra data then focus its associated input
              if( null != option.extra ) $timeout( function() { document.getElementById( 'option' + option.id + 'value0' ).focus(); }, 50 );
            } );
          },

          removeOption: function( question, option ) {
            console.log( 'remove', question.id, option.id );
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
            this.setAnswer( question, value ).then( function() {
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

            this.setAnswer( question, value );
          },

          setAnswerValue: function( question, option, valueIndex, answerValue ) {
            console.log( 'set', question.id, option.id, valueIndex, answerValue );
            var value = question.value;
            var optionIndex = searchOptionList( value, option.id );
            if( null != optionIndex ) {
              if( option.multiple_answers ) {
                if( 0 < answerValue.trim().length ) {
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
                } else {
                  // if the value is blank then remove it
                  value[optionIndex].value.splice( valueIndex, 1 );
                }
              } else {
                value[optionIndex].value = answerValue ? answerValue : null;
              }
            }

            this.setAnswer( question, value );
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
            // proceed to the response's next valid page
            return this.runQuery( function() {
              return CnHttpFactory.instance( {
                path: 'response/token=' + $state.params.token + '?action=proceed'
              } ).patch().then( function() {
                self.parentModel.reloadState( true );
              } );
            } );
          },

          backup: function() {
            // back up to the response's previous page
            return this.runQuery( function() {
              return CnHttpFactory.instance( {
                path: 'response/token=' + $state.params.token + '?action=backup'
              } ).patch().then( function() {
                self.parentModel.reloadState( true );
              } );
            } );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageViewFactory', [
    'CnBaseViewFactory', 'CnBaseQnairePartViewFactory',
    function( CnBaseViewFactory, CnBaseQnairePartViewFactory ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseQnairePartViewFactory.construct( this, 'page' );

        angular.extend( this, {
          onView: function( force ) {
            return this.$$onView( force ).then( function() {
              self.record.descriptions = parseDescriptions( self.record.descriptions );
              self.record.module_descriptions = parseDescriptions( self.record.module_descriptions );
            } );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageModelFactory', [
    'CnBaseModelFactory', 'CnPageAddFactory', 'CnPageListFactory', 'CnPageRenderFactory', 'CnPageViewFactory', '$state',
    function( CnBaseModelFactory, CnPageAddFactory, CnPageListFactory, CnPageRenderFactory, CnPageViewFactory, $state ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnPageAddFactory.instance( this );
        this.listModel = CnPageListFactory.instance( this );
        this.renderModel = CnPageRenderFactory.instance( this );
        this.viewModel = CnPageViewFactory.instance( this, root );

        this.getBreadcrumbParentTitle = function() {
          return this.viewModel.record.module_name;
        };

        this.getServiceResourcePath = function( resource ) {
          // when we're looking at a response use its token to figure out which page to load
          return 'response' == this.getSubjectFromState() ?
            'page/token=' + $state.params.token : this.$$getServiceResourcePath( resource );
        };

        this.getServiceCollectionPath = function( ignoreParent ) {
          var path = this.$$getServiceCollectionPath( ignoreParent );
          if( 'response' == this.getSubjectFromState() )
            path = path.replace( 'response/undefined', 'module/token=' + $state.params.token );
          return path;
        };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
