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

          // bind keypresses (first unbind to prevent duplicates)
          $document.unbind( 'keyup.render' );
          $document.bind( 'keyup.render', function( event ) {
            // only send keyup events when on the render page and the key is a numpad number
            if( ['render','run'].includes( $scope.model.getActionFromState() ) && (
              // keypad enter or number keys
              13 == event.which || ( 97 <= event.which && event.which <= 105 )
            ) ) {
              $scope.model.renderModel.onKeyup( 13 == event.which ? 'enter' : event.which - 96 );
              $scope.$apply();
            }
          } );

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

        function setExclusiveAnswer( questionId, value ) {
          var data = self.data[questionId];
          var list = data.selectedOptionList;

          // unselect all values other than the selected one
          for( var p in list ) {
            if( list.hasOwnProperty( p ) ) {
              if( value != p && list[p] ) {
                list[p] = angular.isString( list[p] ) ? null : false;
              }
            }
          }

          // unselect boolean yes/no answers if they are not selected
          if( angular.isDefined( data.yes ) && 'yes' != value ) data.yes = false;
          if( angular.isDefined( data.no ) && 'no' != value ) data.no = false;

          // unselect the dkna/refuse options if they are not selected
          if( angular.isDefined( data.dkna ) && 'dkna' != value ) data.dkna = false;
          if( angular.isDefined( data.refuse ) && 'refuse' != value ) data.refuse = false;

          // clear extra data
          for( var optionId in data.answerExtraList ) {
            if( data.answerExtraList.hasOwnProperty( optionId ) ) {
              if( value != optionId && angular.isDefined( data.answerExtraList[optionId] ) ) {
                var option = self.questionList.findByProperty( 'id', questionId ).optionList.findByProperty( 'id', optionId );
                if( option.multiple_answers ) data.answerExtraList[optionId] = [];
                else data.answerExtraList[optionId] = [ { id: undefined, value: undefined } ];
              }
            }
          }
        }

        angular.extend( this, {
          parentModel: parentModel,
          questionList: [],
          currentLanguage: null,
          data: {},
          backupData: {},
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
          pageNotDone: function() {
            // TODONEXT: this needs to be overhauled, then do the same on server side
            return false;
            /*
            return this.questionList.some( function( question ) {
              // empty questions are comments so they're always considered complete
              if( angular.equals( {}, self.data ) ) return false;

              for( var property in self.data ) {
                if( self.data.hasOwnProperty( property ) ) {
                  if( 'answerExtraList' == property ) {
                    for( var optionId in self.data.answerExtraList ) {
                      if( self.data.answerExtraList.hasOwnProperty( optionId ) ) {
                        if( self.data.answerExtraList[optionId] ) {
                          return !self.data.answerExtraList[optionId].some( function( ae ) {
                            return angular.isDefined( ae.value ) && '' != ae.value;
                          } );
                        }
                      }
                    }
                  } else if( 'selectedOptionList' == property ) {
                    for( var optionId in self.data.selectedOptionList ) {
                      if( self.data.selectedOptionList.hasOwnProperty( optionId ) ) {
                        if( self.data.selectedOptionList[optionId] ) {
                          return false;
                        }
                      }
                    }
                  } else {
                    // check if the value is set (careful, a value of "0" is a valid answer
                    if( self.data[property] || 0 === self.data[property] ) return false;
                  }
                }
              }

              return true;
            } );
          */
          },

          onLoad: function() {
            return CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '/question',
              data: { select: { column: [ 'rank', 'name', 'type', 'mandatory', 'dkna_refuse', 'minimum', 'maximum', 'descriptions' ] } }
            } ).query().then( function( response ) {
              var promiseList = [];
              angular.extend( self, {
                questionList: response.data,
                data: {},
                backupData: {},
                keyQuestionIndex: null,
                pageComplete: false
              } );

              // set the current language to the first question's language
              if( 0 < self.questionList.length && angular.isDefined( self.questionList[0].language ) ) {
                self.currentLanguage = self.questionList[0].language;
              }

              self.questionList.forEach( function( question, index ) {
                question.descriptions = parseDescriptions( question.descriptions );

                // all questions may have no answer
                var data = 'comment' == question.type ? {} : { dkna: question.dkna, refuse: question.refuse };

                if( 'boolean' == question.type ) {
                  angular.extend( data, {
                    yes: 1 === parseInt( question.value ),
                    no: 0 === parseInt( question.value )
                  } );
                } else if( 'number' == question.type ) {
                  data.value = parseFloat( question.value );
                } else if( ['string', 'text'].includes( question.type ) ) {
                  data.value = question.value;
                } else if( 'list' == question.type ) {
                  // parse the question option list
                  question.question_option_list = null != question.question_option_list
                                              ? question.question_option_list.split( ',' ).map( v => parseInt( v ) )
                                              : [];

                  data.selectedOptionList = {};
                  data.answerExtraList = {};

                  promiseList.push(
                    CnHttpFactory.instance( {
                      path: ['question', question.id, 'question_option' ].join( '/' ),
                      data: {
                        select: { column: [ 'name', 'exclusive', 'extra', 'multiple_answers', 'descriptions' ] },
                        modifier: { order: 'question_option.rank' }
                      }
                    } ).query().then( function( response ) {
                      var subPromiseList = [];
                      question.optionList = response.data;
                      question.optionList.forEach( function( option ) {
                        option.descriptions = parseDescriptions( option.descriptions );
                        data.selectedOptionList[option.id] = question.question_option_list.includes( option.id );
                        if( null != option.extra ) {
                          data.answerExtraList[option.id] = option.multiple_answers ? [] : [ { id: undefined, value: undefined } ];

                          // get answer_extra data one option at a time
                          if( data.selectedOptionList[option.id] ) {
                            subPromiseList.push(
                              CnHttpFactory.instance( {
                                path: ['answer', question.answer_id, 'answer_extra'].join( '/' ),
                                data: {
                                  select: { column: [ 'id', 'value' ] },
                                  modifier: { where: { column: 'question_option_id', operator: '=', value: option.id } }
                                }
                              } ).query().then( function( response ) {
                                data.answerExtraList[option.id] = response.data;
                              } )
                            );
                          }
                        }
                      } );

                      return $q.all( subPromiseList );
                    } )
                  );
                } else if( 'comment' != question.type ) {
                  data.value = null;
                }

                // make sure we have the first non-comment question set as the first key question
                if( null == self.keyQuestionIndex && 'comment' != question.type ) self.keyQuestionIndex = index;

                self.data[question.id] = data;
              } );

              return $q.all( promiseList ).then( function() {
                self.backupData = angular.copy( self.data );
                self.pageComplete = !self.pageNotDone();
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
              var data = self.data[question.id];

              if( 'boolean' == question.type ) {
                // 1 is yes, 2 is no, 3 is dkna and 4 is refuse
                var answer = 1 == key ? 'yes'
                           : 2 == key ? 'no'
                           : 3 == key ? 'dkna'
                           : 4 == key ? 'refuse'
                           : null;

                if( null != answer ) {
                  data[answer] = !data[answer];
                  self.setAnswer( 'boolean', question, answer );
                }
              } else if( 'list' == question.type ) {
                // check if the key is within the option list or the 2 dkna/refuse options
                if( key <= question.optionList.length ) {
                  var answer = question.optionList[key-1];
                  data.selectedOptionList[answer.id] = !data.selectedOptionList[answer.id];
                  self.setAnswer( 'option', question, answer );
                } else if( key == question.optionList.length + 1 ) {
                  data.dkna = !data.dkna;
                  self.setAnswer( 'dkna', question );
                } else if( key == question.optionList.length + 2 ) {
                  data.refuse = !data.refuse;
                  self.setAnswer( 'refuse', question );
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

          setAnswer: function( type, question, option ) {
            var data = self.data[question.id];
            var promiseList = [];

            // first communicate with the server (if we're working with a response)
            if( 'response' == self.parentModel.getSubjectFromState() ) {
              if( 'option' == type ) {
                if( !option.multiple_answers ) {
                  // we're adding or removing an option
                  promiseList.push( this.runQuery( function() {
                    return data.selectedOptionList[option.id] ?
                      CnHttpFactory.instance( {
                        path: ['answer', question.answer_id, 'question_option'].join( '/' ),
                        data: option.id,
                        onError: function( response ) {
                          data = angular.copy( self.backupData[question.id] );
                          CnModalMessageFactory.httpError( response );
                        }
                      } ).post() :
                      CnHttpFactory.instance( {
                        path: ['answer', question.answer_id, 'question_option', option.id].join( '/' ),
                        onError: function( response ) {
                          // ignore 404's, it just means the client and server are out of sync
                          if( 404 != response.status ) {
                            data = angular.copy( self.backupData[question.id] );
                            CnModalMessageFactory.httpError( response );
                          }
                        }
                      } ).delete();
                  } ) );
                }
              } else {
                if( 'extra' != type ) {
                  // determine the patch data
                  var patchData = {};
                  if( 'boolean' == type ) {
                    patchData.value_boolean = data[option] ? 'yes' == option : null;
                  } else if( 'value' == type ) {
                    patchData['value_' + question.type] = data.value;
                  } else if( 'dkna' == type || 'refuse' == type ) { // must be dkna or refuse
                    patchData[type] = data[type];
                  } else {
                    throw new Error( 'Tried to set answer with invalid type "' + type + '"' );
                  }

                  promiseList.push( this.runQuery( function() {
                    return CnHttpFactory.instance( {
                      path: 'answer/' + question.answer_id,
                      data: patchData,
                      onError: function( response ) {
                        data = angular.copy( self.backupData[question.id] );
                        CnModalMessageFactory.httpError( response );
                      }
                    } ).patch()
                  } ) );
                }
              }
            }

            return $q.all( promiseList ).then( function() {
              if( 'dkna' == type || 'refuse' == type ) {
                if( data[type] ) setExclusiveAnswer( question.id, type );
              } else if( 'boolean' == type ) {
                // unselect all other values
                for( var property in data ) {
                  if( data.hasOwnProperty( property ) ) {
                    if( option != property ) data[property] = false;
                  }
                }
              } else if( 'option' == type ) {
                // unselect certain values depending on the chosen option
                if( data.selectedOptionList[option.id] ) {
                  if( option.exclusive ) {
                    setExclusiveAnswer( question.id, option.id );
                  } else {
                    // unselect all no-answer and exclusive values
                    data.dkna = false;
                    data.refuse = false;
                    question.optionList.filter( option => option.exclusive ).forEach( function( option ) {
                      data.selectedOptionList[option.id] = false;
                    } );
                  }
                }

                // handle the special circumstance when clicking an option with an extra added input
                if( null != option.extra ) {
                  if( option.multiple_answers ) self.addAnswerExtra( question );
                  $timeout( function() {
                    
                    // document.getElementById( 'answerExtra' ).focus();
                  }, 50 );
                }
              }

              // resize any elastic text areas in case their data was changed
              angular.element( 'textarea[cn-elastic]' ).trigger( 'elastic' );

              // change is successful so overwrite the backup
              self.backupData[question.id] = angular.copy( data );

              // re-determine whether the page is complete
              self.pageComplete = !self.pageNotDone();

              var deferred = $q.defer();
              $timeout( function() { deferred.resolve(); }, 100 );
              return deferred;
            } );
          },

          addAnswerExtra: function( question, option ) {
            var index = self.data[question.id].answerExtraList[option.id].length;
            self.data[question.id].answerExtraList[option.id].push( { id: undefined, value: undefined, index: index } );
          },

          setAnswerExtra: function( question, option, answerExtra ) {
            if( angular.isDefined( answerExtra.id ) ) {
              // the ID already exists
              if( 0 < answerExtra.value.length ) {
                if( 'response' == self.parentModel.getSubjectFromState() ) {
                  // determine the patch data
                  var patchData = { question_option_id: option.id };
                  patchData['value_' + option.extra] = answerExtra.value;

                  // patch the existing record
                  return this.runQuery( function() {
                    return CnHttpFactory.instance( {
                      path: ['answer', question.answer_id, 'answer_extra', answerExtra.id].join( '/' ),
                      data: patchData
                    } ).patch().then( function() {
                      self.pageComplete = !self.pageNotDone();
                    } );
                  } );
                }
              } else {
                // delete the existing record (since the value was set to an empty string)
                return self.removeAnswerExtra( question, answerExtra );
              }
            } else {
              // the ID doesn't exist, so create a new record
              var promiseList = [];

              if( 'response' == self.parentModel.getSubjectFromState() ) {
                // determine the patch data
                var patchData = { question_option_id: option.id };
                patchData['value_' + option.extra] = answerExtra.value;

                promiseList.push( this.runQuery( function() {
                  return CnHttpFactory.instance( {
                    path: ['answer', question.answer_id, 'answer_extra'].join( '/' ),
                    data: patchData
                  } ).post().then( function( response ) {
                    answerExtra.id = response.data;
                  } );
                } ) );
              }

              return $q.all( promiseList ).then( function() {
                // unselect all no-answer and exclusive values
                var data = self.data[question.id];
                data.dkna = false;
                data.refuse = false;
                question.optionList.filter( o => o.id != option.id && o.exclusive ).forEach( function( o ) {
                  data.selectedOptionList[o.id] = false;
                } );

                self.pageComplete = !self.pageNotDone();
              } );
            }
          },

          removeAnswerExtra: function( question, option, answerExtra ) {
            var promiseList = [];
            if( 'response' == self.parentModel.getSubjectFromState() )
              promiseList.push( this.runQuery( function() {
                return CnHttpFactory.instance( {
                  path: 'answer_extra/' + answerExtra.id
                } ).delete();
              } ) );

            return $q.all( promiseList ).then( function() {
              var data = self.data[question.id];

              // remove the answer extra and renumber the indeces of the remaining items
              data.answerExtraList[option.id] = data.answerExtraList[option.id].reduce( function( list, ae ) {
                if( ae.index != answerExtra.index ) {
                  if( ae.index > answerExtra.index ) ae.index--;
                  list.push( ae );
                }
                return list;
              }, [] );
              self.pageComplete = !self.pageNotDone();
            } );
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
