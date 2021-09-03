define( [ 'address', 'participant', 'question' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'page', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'page' );

  module.identifier.parent = {
    subject: 'module',
    column: 'module.id'
  };

  module.addInput( '', 'average_time', {
    title: 'Average Time (seconds)',
    type: 'string',
    isConstant: true,
    isExcluded: 'add'
  } );
  module.addInput( '', 'max_time', {
    title: 'Max Time (seconds)',
    type: 'string',
    format: 'integer',
    isConstant: true,
    help:
      'Maximum page time is automatically calculated to exclude major outliers by setting its value to that ' +
      'of the outer fence (3 times the interquartile width above the upper quartile).'
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'qnaire_id', { column: 'qnaire.id', isExcluded: true } );
  module.addInput( '', 'qnaire_name', { column: 'qnaire.name', isExcluded: true } );
  module.addInput( '', 'variable_suffix', { column: 'qnaire.variable_suffix', isExcluded: true } );
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
    operation: async function( $state, model ) {
      await $state.go(
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
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'page' );

          await $scope.model.onLoad();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Module',
            go: async function() { await $state.go( 'module.list' ); }
          }, {
            title: $scope.model.parentSourceName,
            go: async function() { await $state.go( 'module.view', { identifier: $scope.model.sourceParentId } ); }
          }, {
            title: 'Pages'
          }, {
            title: $scope.model.sourceName,
            go: async function() { await $state.go( 'page.view', { identifier: $scope.model.sourceId } ); }
          }, {
            title: 'move/copy'
          } ] );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageNavigator', [
    function() {
      return {
        templateUrl: module.getFileUrl( 'page_navigator.tpl.html' ),
        restrict: 'E',
        scope: {
          model: '=?',
          isComplete: '=',
          placement: '@'
        },
        controller: function( $scope ) {
          $scope.text = function( address ) { return $scope.model.renderModel.text( address ); };
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageRender', [
    'CnPageModelFactory', 'CnTranslationHelper', 'CnSession', 'CnHttpFactory', '$state', '$document',
    function( CnPageModelFactory, CnTranslationHelper, CnSession, CnHttpFactory, $state, $document ) {
      return {
        templateUrl: module.getFileUrl( 'render.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
          angular.extend( $scope, {
            isComplete: false,
            text: function( address ) { return $scope.model.renderModel.text( address ); },
            patch: async function( property ) {
              var model = ['address1', 'address2', 'city', 'region_id', 'postcode'].includes( property )
                        ? $scope.model.renderModel.addressModel
                        : $scope.model.renderModel.participantModel;

              if( model.getEditEnabled() ) {
                var element = cenozo.getFormElement( property );
                var valid = model.testFormat( property, model.viewModel.record[property] );

                if( element ) {
                  element.$error.format = !valid;
                  cenozo.updateFormElement( element, true );
                }

                if( valid ) {
                  var data = {};
                  data[property] = model.viewModel.record[property];
                  await model.viewModel.onPatch( data );
                }
              }
            }
          } );

          // bind keydown (first unbind to prevent duplicates)
          $document.unbind( 'keydown' );
          $document.bind( 'keydown', async function( event ) {
            // deactivate hot-keys when inside a number, text or textbox
            if( !$scope.model.renderModel.showHidden || ['number','text','textarea'].includes( event.target.type ) ) return;

            var action = null;
            if( $scope.isComplete && !$scope.model.renderModel.working && !$scope.model.renderModel.hotKeyDisabled ) {
              if( ['ShiftLeft', 'ShiftRight'].includes( event.code ) ) {
                $scope.model.renderModel.upperDigitsActivated = true;
                $scope.$apply();
              }
            }
          } );

          // bind keyup (first unbind to prevent duplicates)
          $document.unbind( 'keyup' );
          $document.bind( 'keyup', async function( event ) {
            // deactivate hot-keys when inside a number, text or textbox
            if( !$scope.model.renderModel.showHidden || ['number','text','textarea'].includes( event.target.type ) ) return;

            var action = null;
            if( $scope.isComplete && !$scope.model.renderModel.working && !$scope.model.renderModel.hotKeyDisabled ) {
              if( ['ShiftLeft', 'ShiftRight'].includes( event.code ) ) {
                $scope.model.renderModel.upperDigitsActivated = false;
                $scope.$apply();
              } else {
                if( 'Minus' == event.code || 'NumpadSubtract' == event.code ) {
                  // proceed to the previous page when the minus key is pushed (keyboard or numpad)
                  if( null != $scope.model.viewModel.record.previous_id ) action = 'prevPage';
                } else if( 'Equal' == event.code || 'NumpadAdd' == event.code ) {
                  // proceed to the next page when the plus key is pushed (keyboard "=" key or numpad)
                  if( angular.isUndefined( $scope.model.viewModel.record.next_id ) ||
                      null != $scope.model.viewModel.record.next_id )
                    action = 'nextPage';
                } else if( 'BracketLeft' == event.code ) {
                  // focus on the previous question when the open square bracket key is pushed (keyboard "[")
                  action = 'prevQuestion';
                } else if( 'BracketRight' == event.code ) {
                  // focus on the next question when the close square bracket key is pushed (keyboard "]")
                  action = 'nextQuestion';
                } else {
                  var match = event.code.match( /^Digit([0-9])$/ );
                  if( match ) {
                    action = parseInt( match[1] );
                    if( 0 == action ) action += 10; // zero comes after 1-9 on the keyboard
                    if( event.shiftKey ) action += 10; // shift moves things up to the next set of numbers
                  }
                }

                if( null != action ) {
                  event.stopPropagation();
                  if( ['prevPage', 'nextPage'].includes( action ) ) {
                    // move to the prev or next page
                    await Promise.all( $scope.model.renderModel.writePromiseList );
                    await 'prevPage' == action ? $scope.model.renderModel.backup() : $scope.model.renderModel.proceed();
                    $scope.$apply();
                  } else if( ['prevQuestion', 'nextQuestion'].includes( action ) ) {
                    // move to the prev or next question
                    $scope.model.renderModel.focusQuestion( 'prevQuestion' == action );
                    $scope.$apply();
                  } else if( null != action ) {
                    await $scope.model.renderModel.onDigitHotKey( action );
                    $scope.$apply();
                  }
                }
              }
            }
          } );

          try {
            await $scope.model.renderModel.onReady();

            CnSession.setBreadcrumbTrail( [ {
              title: $scope.model.renderModel.data.qnaire_name,
              go: async function() {
                await $state.go( 'qnaire.view', { identifier: $scope.model.renderModel.data.qnaire_id } );
              }
            }, {
              title: $scope.model.renderModel.data.uid ? $scope.model.renderModel.data.uid : 'Preview'
            } ] );
          } finally {
            $scope.isComplete = true;
          }
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageRenderFactory', [
    'CnModalConfirmFactory', 'CnModalMessageFactory', 'CnModalDatetimeFactory',
    'CnModalInputFactory', 'CnModalTextFactory', 'CnModalPreStageFactory',
    'CnParticipantModelFactory', 'CnAddressModelFactory', 'CnHttpFactory', 'CnTranslationHelper',
    '$state', '$timeout', '$interval',
    function( CnModalConfirmFactory, CnModalMessageFactory, CnModalDatetimeFactory,
              CnModalInputFactory, CnModalTextFactory, CnModalPreStageFactory,
              CnParticipantModelFactory, CnAddressModelFactory, CnHttpFactory, CnTranslationHelper,
              $state, $timeout, $interval ) {
      var object = function( parentModel ) {
        var self = this;

        // private helper functions
        function formatDate( date ) { var m = getDate( date ); return m ? m.format( 'dddd, MMMM Do YYYY' ) : null; }
        function isDkna( value ) { return angular.isObject( value ) && true === value.dkna; }
        function isRefuse( value ) { return angular.isObject( value ) && true === value.refuse; }
        function isDknaOrRefuse( value ) {
          return angular.isObject( value ) && ( true === value.dkna || true === value.refuse );
        }

        function getDate( date ) {
          if( 'now' == date ) date = moment().format( 'YYYY-MM-DD' );
          return date && !angular.isObject( date ) ? moment( new Date( date ) ) : null;
        }

        function getAttributeNames( precondition ) {
          // scan the precondition for active attributes (also include the showhidden constant)
          var list = [];
          if( angular.isString( precondition ) ) {
            var matches = precondition.match( /@[^@]+@|\bshowhidden\b/g );
            if( null != matches && 0 < matches.length ) list = matches.map( m => m.replace( /@/g, '' ) );
          }
          return list;
        }

        function focusElement( id ) {
          // keep trying until the element exists (10 tries max)
          var promise = $interval(
            function() { $timeout(
              function() {
                var element = document.getElementById( id );
                if( null != element ) { element.focus(); $interval.cancel( promise ); }
              }, 50
            ) }, 50, 10
          );
          return promise;
        }

        angular.extend( this, {
          parentModel: parentModel,
          participantModel: CnParticipantModelFactory.root,
          addressModel: CnAddressModelFactory.root,
          prevModuleList: [],
          nextModuleList: [],
          working: false,
          progress: 0,
          previewMode: 'respondent' != parentModel.getSubjectFromState(),
          data: {
            token: null,
            scanned_token: null,
            response_id: null,
            participant_id: null,
            address_id: null,
            qnaire_id: null,
            qnaire_name: null,
            start_datetime: null,
            end_datetime: null,
            comments: null,
            checked_in: null,
            stage_id: null,
            page_id: null,
            stage_selection: null,
            base_language: null,
            stages: null,
            closed: null,
            submitted: null,
            uid: null,
            introductions: null,
            conclusions: null,
            closes: null,
            title: null,
          },
          responseStageList: null,
          consentList: null,
          consentEnumList: [
            { value: null, name: '(empty)', disabled: true },
            { value: true, name: 'Yes', disabled: false },
            { value: false, name: 'No', disabled: false }
          ],
          deviationTypeList: null,
          languageList: null,
          showHidden: false,
          focusQuestionId: null,
          hotKeyDisabled: false,
          upperDigitsActivated: false,
          activeAttributeList: [],
          questionList: [],
          optionListById: {},
          currentLanguage: null,
          writePromiseList: [],
          promiseIndex: 0,

          reset: function() {
            angular.extend( this, {
              questionList: [],
              activeAttributeList: [],
              prevModuleList: [],
              nextModuleList: [],
              focusQuestionId: null
            } );
          },

          setConsent: async function( consent ) {
            if( null == consent.consent_id ) {
              await CnHttpFactory.instance( {
                path: 'consent',
                data: {
                  participant_id: this.data.participant_id,
                  consent_type_id: consent.consent_type_id,
                  accept: consent.accept,
                  written: false,
                  datetime: moment().format(),
                  note: 'Added by Pine during "' + this.data.qnaire_name + '" interview with token "' + $state.params.token + '".'
                }
              } ).post();
            } else {
              await CnHttpFactory.instance( {
                path: 'consent/' + consent.consent_id,
                data: { accept: consent.accept }
              } ).patch();
            }
          },

          setCheckIn: async function( checkedIn ) {
            // update the token if we've changed it
            var updatedToken = null;
            if( checkedIn && this.data.token != this.data.scanned_token ) {
              await CnHttpFactory.instance( {
                path: 'respondent/token=' + $state.params.token,
                data: { token: this.data.scanned_token }
              } ).patch();
              updatedToken = this.data.scanned_token;
            }

            // mark the response as checked in
            await CnHttpFactory.instance( {
              path: 'response/' + this.data.response_id,
              data: { checked_in: checkedIn }
            } ).patch();

            if( null == updatedToken ) {
              // the token hasn't changed so just reload the state
              await this.parentModel.reloadState( true );
            } else {
              // the token has changed so we have to change the URL
              await $state.go( 'respondent.run', { token: updatedToken } );
            }
          },

          text: function( address ) { return CnTranslationHelper.translate( address, this.currentLanguage ); },

          getVisibleQuestionList: function() {
            return this.questionList.filter( question => self.evaluate( question.precondition ) );
          },

          getVisibleOptionList: function( question ) {
            return question.optionList.filter( option => self.evaluate( option.precondition ) );
          },

          getFocusableQuestionList: function() {
            return this.getVisibleQuestionList().filter( question => 'comment' != question.type );
          },

          openHotkeyHint: async function() {
            await CnModalMessageFactory.instance( {
              title: 'Hot-key Hints',
              message: '<ul>' +
                '<li>1) Navigation "-" and "+" hot-keys (keyboard or numpad) moves page backward and forward, respectively\n' +
                '<li>2) Navigation "[" and "]" hot-keys moves focused question backward and forward, respectively\n' +
                '<li>3) When page is first loaded, no question is focused\n' +
                '<li>4) Focused question is highlighted in pale yellow and numeric options identified by a number in ' +
                       'square brackets (e.g.: "[1]")\n' +
                '<li>5) Numeric hot-keys "1" through "9" and "0" (numpad not included) will do the following based on ' +
                       'the question type which is currently focused:\n' +
                '<ul>' +
                '<li>a) List: the option will be toggled (off to on, or on to off). ' +
                       'Note that when extra information is required by the selected option the input box will ' +
                       'automatically be given focus.\n' +
                '<li>b) Date: the date picker will show\n' +
                '<li>c) Number/String/Text: the input box is focused. ' +
                       'Note that this only happens when [1] is selected, input boxes are not focused when the ' +
                       'question is first selected using the "[" and "]" navigation hot-keys.\n' +
                '</ul>' +
                '<li>6) All hot-keys are deactivated when no question is focused, or when focus is in an input box\n' +
                '<li>7) Hot-keys are disabled when questionnaire is launched via a web-link ' +
                       '(directly by the participant and not through Sabretooth, Beartooth or Cedar)\n' +
                '</ul>'
            } ).show();
          },

          getHotKey: function( question, item ) {
            var key = null;

            if( this.focusQuestionId == question.id ) {
              if( 'boolean' == question.type ) {
                if( 'dkna' == item ) {
                  key = 3;
                } else if ( 'refuse' == item ) {
                  key = question.dkna_allowed ? 4 : 3;
                } else {
                  var bool = item;
                  key = bool ? 1 : 2;
                }
              } else if( 'list' == question.type ) {
                var optionList = this.getVisibleOptionList( question );
                if( 'dkna' == item ) {
                  key = optionList.length + 1;
                } else if ( 'refuse' == item ) {
                  key = optionList.length + ( question.dkna_allowed ? 1 : 0 ) + 1;
                } else {
                  var optionId = item;
                  var index = optionList.findIndexByProperty( 'id', optionId );
                  if( null != index ) key = index + 1;
                }
              } else {
                if( 'dkna' == item ) {
                  key = 2;
                } else if ( 'refuse' == item ) {
                  key = ( question.dkna_allowed ? 1 : 0 ) + 2;
                } else {
                  key = 1;
                }
              }

              // if upper digits are activated then move down by 10
              if( this.upperDigitsActivated ) key -= 10;

              // change 10 to 0 and only allow numbers in [0,9]
              key = 10 == key ? 0 : 1 <= key && key <= 9 ? key : null;
            }

            // enclose with square brackets
            return null == key ? '' : ( '[' + key + ']' );
          },

          onReady: async function() {
            this.previewMode = 'respondent' != parentModel.getSubjectFromState();

            // we show hidden stuff when previewing (not a respondent) or when there is a state parameter asking for it
            this.showHidden = this.previewMode
                            ? true
                            : angular.isDefined( $state.params.show_hidden )
                            ? $state.params.show_hidden
                            : false;

            if( !this.previewMode ) {
              angular.extend( this, {
                responseStageList: null,
                consentList: null,
                deviationTypeList: null,
              } );

              // check for the respondent using the token
              var params = '?assert_response=1';
              if( this.showHidden ) params += '&&show_hidden=1';
              var response = await CnHttpFactory.instance( {
                path: 'respondent/token=' + $state.params.token + params,
                data: { select: { column: [
                  'token', 'participant_id', 'qnaire_id', 'start_datetime', 'end_datetime', 'introductions', 'conclusions', 'closes',
                  { table: 'participant', column: 'first_name' },
                  { table: 'participant', column: 'last_name' },
                  { table: 'qnaire', column: 'stages' },
                  { table: 'qnaire', column: 'closed' },
                  { table: 'qnaire', column: 'name', alias: 'qnaire_name' },
                  { table: 'response', column: 'id', alias: 'response_id' },
                  { table: 'response', column: 'checked_in' },
                  { table: 'response', column: 'page_id' },
                  { table: 'response', column: 'stage_selection' },
                  { table: 'response', column: 'submitted' },
                  { table: 'response', column: 'comments' },
                  { table: 'response_stage', column: 'id', alias: 'response_stage_id' },
                  { table: 'response_stage', column: 'stage_id' },
                  { table: 'participant', column: 'uid' },
                  { table: 'language', column: 'code', alias: 'base_language' }
                ] } },
                onError: function( error ) {
                  $state.go( 'error.' + error.status, error );
                }
              } ).get();

              this.data = response.data;

              // set the scanned token only if the token is non-generic
              this.data.scanned_token = null == this.data.token.match( /^[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/ )
                                      ? this.data.token
                                      : null;

              // get the stage list if there is one:
              //   not ready: nothing
              //   ready: launch, skip
              //   active: nothing (it will never show in the list)
              //   paused: resume, skip, reset
              //   skipped: reset
              //   completed: re-open, reset
              if( this.data.stages ) {
                var [responseStageResponse,
                     consentResponse,
                     deviationTypeResponse,
                     participantResponse,
                     addressResponse] = await Promise.all( [
                  CnHttpFactory.instance( {
                    path: ['response', this.data.response_id, 'response_stage'].join( '/' ),
                    data: {
                      select: { column: [
                        'id', 'status', 'start_datetime', 'end_datetime', 'deviation_type_id', 'deviation_comments', 'comments',
                        { table: 'stage', column: 'rank' },
                        { table: 'stage', column: 'name' },
                        { table: 'deviation_type', column: 'name', alias: 'deviation' }
                      ] },
                      modifier: { order: 'stage.rank' }
                    }
                  } ).query(),

                  CnHttpFactory.instance( {
                    path: ['respondent', 'token=' + $state.params.token, 'consent'].join( '/' ),
                    data: {
                      select: {
                        column: [
                          { table: 'consent', column: 'id', alias: 'consent_id' },
                          { table: 'consent', column: 'accept' },
                          { table: 'consent_type', column: 'id', alias: 'consent_type_id' },
                          { table: 'consent_type', column: 'name', alias: 'consent_type' }
                        ]
                      }
                    }
                  } ).query(),

                  CnHttpFactory.instance( {
                    path: ['qnaire', this.data.qnaire_id, 'deviation_type'].join( '/' )
                  } ).query(),

                  this.participantModel.viewModel.onView( true ),

                  this.addressModel.viewModel.onView( true )
                ] );

                this.responseStageList = responseStageResponse.data;
                if( 0 == this.responseStageList.length )
                  throw new Error( 'Questionnaire has not stages, unable to proceed.' );

                // set each response stage's possible operations
                this.responseStageList.forEach( function( responseStage ) {
                  responseStage.operations = [];

                  if( !['not ready', 'skipped' ].includes( responseStage.status ) ) {
                    responseStage.operations.push( {
                      name: 'launch',
                      title: 'completed' == responseStage.status ? 'Re-Open' :
                             'paused' == responseStage.status ? 'Resume' : 'Launch'
                    } );
                  }

                  if( ['paused', 'ready'].includes( responseStage.status ) ) {
                    responseStage.operations.push( { name: 'skip', title: 'Skip' } );
                  }

                  if( !['not ready', 'ready'].includes( responseStage.status ) ) {
                    responseStage.operations.push( { name: 'reset', title: 'Reset' } );
                  }
                } );

                this.consentList = consentResponse.data;

                this.deviationTypeList = deviationTypeResponse.data;
                if( 0 == this.deviationTypeList.length ) {
                  throw new Error( 'Questionnaire has no deviation types, unable to proceed.' );
                } else if( 0 == this.deviationTypeList.filter( deviationType => 'skip' == deviationType.type ) ) {
                  throw new Error( 'Questionnaire has no skip deviation types, unable to proceed.' );
                } else if( 0 == this.deviationTypeList.filter( deviationType => 'order' == deviationType.type ) ) {
                  throw new Error( 'Questionnaire has no order deviation types, unable to proceed.' );
                }

                // enum lists use value, so set the value to the deviation type's ID
                this.deviationTypeList.forEach( function( deviationType ) { deviationType.value = deviationType.id; } );

                this.participantInputList = [ {
                  key: 'honorific',
                  title: 'Honorific',
                  type: 'string',
                  help: 'English examples: Mr. Mrs. Miss Ms. Dr. Prof. Br. Sr. Fr. Rev. Pr. ' +
                        'French examples: M. Mme Dr Dre Prof. F. Sr P. Révérend Pasteur Pasteure Me'
                }, {
                  key: 'first_name',
                  title: 'First Name',
                  type: 'string'
                }, {
                  key: 'other_name',
                  title: 'Other/Nickname',
                  type: 'string'
                }, {
                  key: 'last_name',
                  title: 'Last Name',
                  type: 'string'
                }, {
                  key: 'date_of_birth',
                  title: 'Date of Birth',
                  type: 'dob',
                  isConstant: true,
                  max: 'now'
                }, {
                  key: 'sex',
                  title: 'Sex at Birth',
                  type: 'enum',
                  isConstant: true,
                  enumList: [{value:'male',name:'male'}, {value:'female',name:'female'}]
                }, {
                  key: 'current_sex',
                  title: 'Current Sex',
                  type: 'enum',
                  enumList: [{value:'male',name:'male'}, {value:'female',name:'female'}]
                }, {
                  key: 'email',
                  title: 'Email',
                  type: 'string',
                  format: 'email',
                  help: 'Must be in the format &quot;account@domain.name&quot;.'
                } ];

                this.addressInputList = [ {
                  key: 'address1',
                  title: 'Address Line 1',
                  type: 'string'
                }, {
                  key: 'address2',
                  title: 'Address Line 2',
                  type: 'string'
                }, {
                  key: 'city',
                  title: 'City',
                  type: 'string'
                }, {
                  key: 'region_id',
                  title: 'Region',
                  type: 'enum',
                  isConstant: true,
                  enumList: this.addressModel.metadata.columnList.region_id.enumList,
                  help: 'The region cannot be changed directly, instead it is automatically updated based on the postcode.'
                }, {
                  key: 'postcode',
                  title: 'Postcode',
                  type: 'string'
                } ];
              }

              angular.extend( this.data, {
                introductions: CnTranslationHelper.parseDescriptions( this.data.introductions, this.showHidden ),
                conclusions: CnTranslationHelper.parseDescriptions( this.data.conclusions, this.showHidden ),
                closes: CnTranslationHelper.parseDescriptions( this.data.closes, this.showHidden )
              } );
            }

            if( this.previewMode || null != this.data.page_id ) {
              await this.parentModel.viewModel.onView( true );

              angular.extend( this.data, {
                page_id: this.parentModel.viewModel.record.id,
                qnaire_id: this.parentModel.viewModel.record.qnaire_id,
                qnaire_name: this.parentModel.viewModel.record.qnaire_name,
                base_language: this.parentModel.viewModel.record.base_language,
                uid: this.parentModel.viewModel.record.uid
              } );

              this.progress = Math.round(
                100 * (
                  angular.isDefined( this.parentModel.viewModel.record.stage_pages )
                    ? this.parentModel.viewModel.record.stage_page / this.parentModel.viewModel.record.stage_pages
                    : this.parentModel.viewModel.record.qnaire_page / this.parentModel.viewModel.record.qnaire_pages
                )
              );

              this.reset();
              var response = await CnHttpFactory.instance( {
                path: this.parentModel.getServiceResourceBasePath() + '/question',
                data: {
                  select: { column: [
                    'id', 'rank', 'name', 'type', 'mandatory', 'dkna_allowed', 'refuse_allowed',
                    'minimum', 'maximum', 'precondition', 'prompts', 'popups'
                  ] },
                  modifier: { order: 'question.rank' }
                }
              } ).query();
              this.questionList = response.data;

              // set the current language to the first (visible) question's language
              if( 0 < this.questionList.length && angular.isDefined( this.questionList[0].language ) ) {
                this.questionList.some( function( question ) {
                  // questions which aren't visible will have a null language
                  if( null != question.language ) {
                    self.currentLanguage = question.language;
                    return true;
                  }
                } );

                cenozoApp.setLang( this.currentLanguage );
              }

              // if in debug mode then get a list of all modules before and after the current
              if( this.parentModel.viewModel.record.debug ) {
                var column = [ 'id', 'rank', 'name' ];
                var modifier = { order: 'module.rank' };
                if( this.data.stages ) modifier.where = { column: 'stage.id', operator: '=', value: this.data.stage_id };
                var response = await CnHttpFactory.instance( {
                  path: ['qnaire', this.parentModel.viewModel.record.qnaire_id , 'module'].join( '/' ),
                  data: {
                    select: { column: [ 'id', 'rank', 'name' ] },
                    modifier: modifier
                  }
                } ).query();

                var foundCurrentModule = false;
                response.data.forEach( function( module ) {
                  if( !foundCurrentModule && module.id == self.parentModel.viewModel.record.module_id ) {
                    foundCurrentModule = true
                  } else {
                    if( foundCurrentModule ) self.nextModuleList.push( module );
                    else self.prevModuleList.push( module );
                  }
                } );
              }

              var activeAttributeList = [];
              var promiseList = this.questionList.reduce( function( list, question, questionIndex ) {
                question.incomplete = false;
                question.prompts = CnTranslationHelper.parseDescriptions( question.prompts );
                question.popups = CnTranslationHelper.parseDescriptions( question.popups );
                question.value = angular.fromJson( question.value );
                question.backupValue = angular.copy( question.value );
                activeAttributeList = activeAttributeList.concat( getAttributeNames( question.precondition ) );

                // if the question is a list type then get the options
                if( 'list' == question.type ) {
                  var getOptionsFn = async function() {
                    var response = await CnHttpFactory.instance( {
                      path: ['question', question.id, 'question_option'].join( '/' ) + (
                        !self.previewMode ? '?token=' + $state.params.token : ''
                      ),
                      data: {
                        select: { column: [
                          'id', 'question_id', 'name', 'exclusive', 'extra', 'multiple_answers',
                          'minimum', 'maximum', 'precondition', 'prompts', 'popups'
                        ] },
                        modifier: { order: 'question_option.rank' }
                      }
                    } ).query();

                    question.optionList = response.data;
                    question.optionList.forEach( function( option ) {
                      activeAttributeList = activeAttributeList.concat( getAttributeNames( option.precondition ) );
                      option.prompts = CnTranslationHelper.parseDescriptions( option.prompts );
                      option.popups = CnTranslationHelper.parseDescriptions( option.popups );
                      self.optionListById[option.id] = option;
                    } );
                  }

                  list.push( getOptionsFn() );
                }

                return list;
              }, [] );

              await Promise.all( promiseList );

              this.questionList.forEach( question => self.convertValueToModel( question ) );

              // sort active attribute and make a unique list
              this.activeAttributeList = activeAttributeList
                .sort()
                .filter( ( attribute, index, array ) => index === array.indexOf( attribute ) )
                .map( attribute => ( { name: attribute, value: null } ) );
            } else if( !this.data.stage_selection && null == this.data.page_id ) {
              await this.reset();
            }

            var response = await CnHttpFactory.instance( {
              path: [ 'qnaire', this.data.qnaire_id, 'language' ].join( '/' ),
              data: { select: { column: [ 'id', 'code', 'name' ] } }
            } ).query();
            this.languageList = response.data;

            if( null == this.currentLanguage ) this.currentLanguage = this.data.base_language;

            // finally, now that we know the language set the title
            this.data.title = this.data.submitted ? 'Conclusion'
                            : null != this.data.page_id ? ''
                            : this.data.stage_selection ? (
                              ['Interview', $state.params.token, this.data.checked_in ? 'Stage Selection' : 'Check-In'].join( ' ' )
                            )
                            : 'Introduction';
          },

          // Used to maintain a semaphore of queries so that they are all executed in sequence without any bumping the queue
          runQuery: async function( fn ) {
            await Promise.all( this.writePromiseList );

            var response = null;
            var newIndex = self.promiseIndex++;
            try {
              response = fn();
              response.index = newIndex;
            } finally {
              // remove the promise from the write promise list
              var index = self.writePromiseList.findIndexByProperty( 'index', newIndex );
              if( null != index ) self.writePromiseList.splice( index, 1 );
            }

            await response;
          },

          convertValueToModel: function( question ) {
            // get the full variable name
            question.variable_name = question.name + (
              this.parentModel.viewModel.record.variable_suffix ?
                ( '_' + this.parentModel.viewModel.record.variable_suffix ) : ''
            );

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
            if( this.previewMode ) {
              this.activeAttributeList.forEach( function( attribute ) {
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
              } else if( null != fnName && 'extra(' == fnName.substr( 0, 6 ) ) {
                optionName = fnName.match( /extra\(([^)]+)\)/ )[1];
                fnName = 'extra()';
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
                  if( angular.isString( matchedQuestion.value ) )
                    compiled = "'" + matchedQuestion.value.replace( /'/g, "\\'" ) + "'";
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
                        compiled = 'extra()' == fnName ? 'null' : 'false';
                      } else {
                        if( matchedOption.multiple_answers ) {
                          // make sure at least one of the answers isn't null
                          compiled = answer.value.some( v => v != null ) ? 'true' : 'false';
                        } else if( 'extra()' == fnName ) {
                          compiled = 'number' == matchedOption.extra
                                   ? answer.value :
                                   ( '"' + answer.value.replace( '"', '\"' ) + '"' );
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
            function evaluateExpression( precondition ) { return Function( '"use strict"; return ' + precondition + ';' )(); }
            return evaluateExpression( expression );
          },

          setLanguage: async function() {
            cenozoApp.setLang( this.currentLanguage );
            if( !this.previewMode && null != this.currentLanguage ) {
              await this.runQuery( async function() {
                await CnHttpFactory.instance( {
                  path: self.parentModel.getServiceResourceBasePath().replace( 'page/', 'respondent/' ) +
                    '?action=set_language&code=' + self.currentLanguage
                } ).patch();
              } );
            }
          },

          setAnswer: async function( question, value, noCompleteCheck ) {
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

            await this.runQuery(
              tooSmall || tooLarge ?

              // When the number is out of bounds then alert the user
              async function() {
                await CnModalMessageFactory.instance( {
                  title: self.text( tooSmall ? 'misc.minimumTitle' : 'misc.maximumTitle' ),
                  message: self.text( 'misc.limitMessage' ) + ' ' + (
                    null == maximum ? self.text( 'misc.equalOrGreater' ) + ' ' + minimum + '.' :
                    null == minimum ? self.text( 'misc.equalOrLess' ) + ' ' + maximum + '.' :
                    [self.text( 'misc.between' ), minimum, self.text( 'misc.and' ), maximum + '.'].join( ' ' )
                  )
                } ).show();

                question.value = angular.copy( question.backupValue );
                self.convertValueToModel( question );
              } :

              // No out of bounds detected, so proceed with setting the value
              async function() {
                var proceed = true;

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
                    proceed = false;
                  }
                }

                if( proceed ) {
                  try {
                    self.working = true;
                    if( "" === value ) value = null;

                    if( !self.previewMode ) {
                      // first communicate with the server (if we're working with a respondent)
                      await CnHttpFactory.instance( {
                        path: 'answer/' + question.answer_id,
                        data: { value: angular.toJson( value ) },
                        onError: function( error ) {
                          question.value = angular.copy( question.backupValue );
                          self.convertValueToModel( question );
                        }
                      } ).patch();
                    }

                    question.value = value;
                    question.backupValue = angular.copy( question.value );
                    self.convertValueToModel( question );

                    // now blank out answers to questions which are no longer visible
                    // (this is done automatically on the server side)
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
                  } finally {
                    self.working = false;
                  }
                }
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
              value.sort( function(a,b) {
                return ( angular.isObject( a ) ? a.id : a ) - ( angular.isObject( b ) ? b.id : b );
              } );
            }

            return value;
          },

          addOption: async function( question, option ) {
            await this.setAnswer( question, this.getValueForNewOption( question, option ) );

            // if the option has extra data then focus its associated input
            if( null != option.extra ) {
              await focusElement( 'option' + option.id + 'value0' );
            }
          },

          removeOption: async function( question, option ) {
            // get the current value array and remove the option from it
            var value = angular.isArray( question.value ) ? question.value : [];
            var optionIndex = searchOptionList( value, option.id );
            if( null != optionIndex ) value.splice( optionIndex, 1 );
            if( 0 == value.length ) value = null;

            await this.setAnswer( question, value );
          },

          addAnswerValue: async function( question, option ) {
            var value = angular.isArray( question.value ) ? question.value : [];
            var optionIndex = searchOptionList( value, option.id );
            if( null == optionIndex ) {
              value = this.getValueForNewOption( question, option );
              optionIndex = searchOptionList( value, option.id );
            }

            var valueIndex = value[optionIndex].value.indexOf( null );
            if( -1 == valueIndex ) valueIndex = value[optionIndex].value.push( null ) - 1;
            await this.setAnswer( question, value, true );
            await focusElement( 'option' + option.id + 'value' + valueIndex );
          },

          removeAnswerValue: async function( question, option, valueIndex ) {
            var value = question.value;
            var optionIndex = searchOptionList( value, option.id );
            value[optionIndex].value.splice( valueIndex, 1 );
            if( 0 == value[optionIndex].value.length ) value.splice( optionIndex, 1 );
            if( 0 == value.length ) value = null;

            await this.setAnswer( question, value, true );
          },

          selectDateForOption: async function( question, option, valueIndex, answerValue ) {
            try {
              this.hotKeyDisabled = true;

              var response = await CnModalDatetimeFactory.instance( {
                locale: this.currentLanguage,
                date: answerValue,
                pickerType: 'date',
                minDate: getDate( this.evaluateLimit( option.minimum ) ),
                maxDate: getDate( this.evaluateLimit( option.maximum ) ),
                emptyAllowed: true
              } ).show();

              if( false !== response ) {
                await this.setAnswerValue(
                  question,
                  option,
                  valueIndex,
                  null == response ? null : response.replace( /T.*/, '' )
                );
              }
            } finally {
              this.hotKeyDisabled = false;
            }
          },

          selectDate: async function( question, value ) {
            try {
              this.hotKeyDisabled = true;

              var response = await CnModalDatetimeFactory.instance( {
                locale: this.currentLanguage,
                date: value,
                pickerType: 'date',
                minDate: getDate( this.evaluateLimit( question.minimum ) ),
                maxDate: getDate( this.evaluateLimit( question.maximum ) ),
                emptyAllowed: true
              } ).show();

              if( false !== response ) {
                await this.setAnswer(
                  question,
                  null == response ? null : response.replace( /T.*/, '' )
                );
              }
            } finally {
              this.hotKeyDisabled = false;
            }
          },

          setAnswerValue: async function( question, option, valueIndex, answerValue ) {
            // if the question option's extra type is a number then make sure it falls within the min/max values
            var minimum = this.evaluateLimit( option.minimum );
            var maximum = this.evaluateLimit( option.maximum );
            var tooSmall = 'number' == option.extra && null != answerValue && ( null != minimum && answerValue < minimum );
            var tooLarge = 'number' == option.extra && null != answerValue && ( null != maximum && answerValue > maximum );

            if( tooSmall || tooLarge ) {
              await this.runQuery( async function() {
                await CnModalMessageFactory.instance( {
                  title: self.text( tooSmall ? 'misc.minimumTitle' : 'misc.maximumTitle' ),
                  message: self.text( 'misc.limitMessage' ) + ' ' + (
                    null == maximum ? self.text( 'misc.equalOrGreater' ) + ' ' + minimum + '.' :
                    null == minimum ? self.text( 'misc.equalOrLess' ) + ' ' + maximum + '.' :
                    [self.text( 'misc.between' ), minimum, self.text( 'misc.and' ), maximum + '.'].join( ' ' )
                  )
                } ).show();

                // put the old value back
                var element = document.getElementById( 'option' + option.id + 'value' + valueIndex );
                element.value = question.answer.optionList[option.id].valueList[valueIndex];
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
                    question.answer.optionList[option.id].formattedValueList[valueIndex] =
                      formatDate( value[optionIndex].value );
                }
              }

              await this.setAnswer( question, value );
            }
          },

          viewPage: async function() {
            await $state.go(
              'page.view',
              { identifier: this.parentModel.viewModel.record.getIdentifier() },
              { reload: true }
            );
          },

          setResponseComments: async function() {
            try {
              this.working = true;
              await this.runQuery( async function() {
                await CnHttpFactory.instance( {
                  path: 'response/' + self.data.response_id,
                  data: { comments: self.data.comments }
                } ).patch();
              } );
            } finally {
              this.working = false;
            }
          },

          showStageComments: async function( responseStageId ) {
            // if no ID is provided then assume the currently active one
            if( !responseStageId ) responseStageId = this.data.response_stage_id;
            var responseStage = this.responseStageList.findByProperty( 'id', responseStageId );
            var response = await CnModalTextFactory.instance( {
              title: responseStage.name + ' Comments',
              message: 'Please provide any relevant comments about this stage:',
              text: responseStage.comments,
              size: 'lg'
            } ).show();

            if( false !== response ) {
              try {
                this.working = true;
                await this.runQuery( async function() {
                  await CnHttpFactory.instance( {
                    path: 'response_stage/' + responseStageId,
                    data: { comments: response }
                  } ).patch();
                  responseStage.comments = response;
                } );
              } finally {
                this.working = false;
              }
            }
          },

          runStageOperation: async function( responseStageId, operation ) {
            // if no ID is provided then assume the currently active one
            if( !responseStageId ) responseStageId = this.data.response_stage_id;

            if( !['launch', 'pause', 'skip', 'reset'].includes( operation ) )
              throw new Error( 'Tried to run invalid stage operation "' + operation + '"' );

            var patchData = null;
            var proceed = true;
            if( 'reset' == operation ) {
              var response = await CnModalConfirmFactory.instance( {
                message:
                  'Are you sure you wish to reset this stage?<br><br>' +
                  '<b class="text-danger">Note that by proceeding all data collected during the stage will be deleted.</b>',
                html: true
              } ).show();
              proceed = response;
            } else if( ['skip', 'launch'].includes( operation ) ) {
              // check if we have to ask for the reason for deviation
              proceed = false;
              var responseStage = this.responseStageList.findByProperty( 'id', responseStageId );
              var deviation = null;
              if( 'launch' == operation ) {
                if( this.responseStageList.filter( rs => rs.rank < responseStage.rank )
                                          .some( rs => ['ready', 'paused'].includes( rs.status ) ) ) {
                  deviation = 'order';
                }
              } else if( 'skip' == operation ) {
                deviation = 'skip';
              }

              // now show the pre-stage dialog
              var response = await CnModalPreStageFactory.instance( {
                title: responseStage.name + ': ' + operation.ucWords(),
                deviationTypeList: deviation ? this.deviationTypeList.filter( dt => deviation == dt.type ) : null,
                validToken: $state.params.token,
                deviationTypeId: responseStage.deviation_type_id,
                deviationComments: responseStage.deviation_comments,
                comments: responseStage.comments
              } ).show();

              if( null != response ) {
                patchData = response;
                proceed = true;
              }
            }

            if( proceed ) {
              try {
                this.working = true;
                await this.runQuery( async function() {
                  var httpObj = { path: 'response_stage/' + responseStageId + '?action=' + operation };
                  if( null != patchData ) {
                    httpObj.data = patchData;
                    // update the client with any changes
                    angular.extend( responseStage, patchData );
                  }

                  // update the server with any changes
                  await CnHttpFactory.instance( httpObj ).patch();
                  await self.parentModel.reloadState( true );
                } );
              } finally {
                this.working = false;
              }
            }
          },

          proceed: async function() {
            if( this.previewMode ) {
              await $state.go(
                'page.render',
                { identifier: this.parentModel.viewModel.record.next_id },
                { reload: true }
              );
            } else {
              try {
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
                  await this.runQuery( async function() {
                    await CnHttpFactory.instance( {
                      path: 'respondent/token=' + $state.params.token + '?action=proceed'
                    } ).patch();
                    await self.parentModel.reloadState( true );
                  } );
                }
              } finally {
                this.working = false;
              }
            }
          },

          backup: async function() {
            if( this.previewMode ) {
              await $state.go(
                'page.render',
                { identifier: this.parentModel.viewModel.record.previous_id },
                { reload: true }
              );
            } else {
              try {
                // back up to the respondent's previous page
                this.working = true;
                await this.runQuery( async function() {
                  await CnHttpFactory.instance( {
                    path: 'respondent/token=' + $state.params.token + '?action=backup'
                  } ).patch();
                  await self.parentModel.reloadState( true );
                } );
              } finally {
                this.working = false;
              }
            }
          },

          jump: async function( moduleId ) {
            try {
              // jump to the first page in the provided module
              this.working = true;
              await this.runQuery( async function() {
                await CnHttpFactory.instance( {
                  path: 'respondent/token=' + $state.params.token + '?action=jump&module_id=' + moduleId,
                } ).patch();
                await self.parentModel.reloadState( true );
              } );
            } finally {
              this.working = false;
            }
          },

          onDigitHotKey: async function( digit ) {
            var question = this.questionList.findByProperty( 'id', this.focusQuestionId );
            if( null == question ) return;

            var value = undefined;
            if( 'boolean' == question.type ) {
              if( 1 == digit ) {
                value = question.answer.yes ? null : true;
              } else if ( 2 == digit ) {
                value = question.answer.no ? null : false;
              } else if( 3 == digit ) {
                value = 'dkna_refuse_1';
              } else if( 4 == digit ) {
                value = 'dkna_refuse_2';
              }
            } else if( 'list' == question.type ) {
              var optionList = this.getVisibleOptionList( question );

              if( digit <= optionList.length ) {
                // add or remove the option, depending on whether it is currently selected
                value = optionList[digit-1];
              } else {
                if( digit == (optionList.length+1) ) {
                  value = 'dkna_refuse_1';
                } else if( digit == (optionList.length+2) ) {
                  value = 'dkna_refuse_2';
                }
              }
            } else {
              if( 1 == digit ) {
                if( 'date' == question.type ) {
                  // show the date selection modal
                  await this.selectDate( question, question.answer.value );
                } else {
                  // simply focus on the question's input box
                  await focusElement( 'question' + question.id );
                }

              } else if( 2 == digit ) {
                value = 'dkna_refuse_1';
              } else if( 3 == digit ) {
                value = 'dkna_refuse_2';
              }
            }

            // if value is set the update the answer
            if( angular.isDefined( value ) ) {
              var setAnswerTo = undefined;
              if( angular.isObject( value ) ) {
                // we've selected an option
                var option = value;
                if( option.multiple_answers ) {
                  await this.addAnswerValue( question, option );
                } else if( question.answer.optionList[option.id].selected ) {
                  await this.removeOption( question, option );
                } else {
                  await this.addOption( question, option );
                }
              } else if( angular.isString( value ) ) {
                // we've either selected the first or second dkna/refuse option
                var match = value.match( /^dkna_refuse_([0-9])/ );
                if( match ) {
                  if( 1 == match[1] ) {
                    // the first dkna/refuse option
                    if( question.dkna_allowed ) setAnswerTo = question.answer.dkna ? null : { dkna: true };
                    else if( question.refuse_allowed ) setAnswerTo = question.answer.refuse ? null : { refuse: true };
                  } else if( 2 == match[1] ) {
                    // the second dkna/refuse option
                    if( question.dkna_allowed && question.refuse_allowed )
                      setAnswerTo = question.answer.refuse ? null : { refuse: true };
                  }
                }
              } else {
                // we've set a boolean value
                setAnswerTo = value;
              }

              if( angular.isDefined( setAnswerTo ) ) await this.setAnswer( question, setAnswerTo );
            }
          },

          // set prev to true to focus on previous question, otherwise focus on the next one
          focusQuestion: function( prev ) {
            if( angular.isUndefined( prev ) ) prev = true;

            var requestedIndex = null;
            var questionList = this.getFocusableQuestionList();
            if( null == this.focusQuestionId ) {
              // focus on the last/first question if no question is currently selected
              if( 0 < questionList.length ) requestedIndex = prev ? questionList.length - 1 : 0;
            } else {
              // try to focus on the prev/next question, if there is one
              var index = questionList.findIndexByProperty( 'id', this.focusQuestionId );
              var requestedIndex = prev ? index - 1 : index + 1;
            }

            if( angular.isDefined( questionList[requestedIndex] ) ) {
              var question = questionList[requestedIndex];
              this.focusQuestionId = question.id;

              // scroll so that the bottom of the div is visible
              var element = document.getElementById( 'baseQuestion' + question.id );
              if( null != element ) {
                element.scrollTop += 100;
                element.scrollIntoView( false );
              }
            }
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  // extend the add factory created by calling initQnairePartModule()
  cenozo.providers.decorator( 'CnPageAddFactory', [
    '$delegate', 'CnSession',
    function( $delegate, CnSession ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel ) {
        var object = instance( parentModel );

        // see if the form has a record in the data-entry module
        object.baseOnNewFn = object.onNew;
        angular.extend( object, {
          onNew: async function( record ) {
            await this.baseOnNewFn( record );

            // set the default page max time
            if( angular.isUndefined( record.max_time ) ) record.max_time = CnSession.setting.defaultPageMaxTime;
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );

  // extend the view factory created by calling initQnairePartModule()
  cenozo.providers.decorator( 'CnPageViewFactory', [
    '$delegate', '$filter', 'CnTranslationHelper',
    function( $delegate, $filter, CnTranslationHelper ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        var object = instance( parentModel, root );

        // see if the form has a record in the data-entry module
        angular.extend( object, {
          onView: async function( force ) {
            await this.$$onView( force );
            this.record.average_time = $filter( 'cnSeconds' )( Math.round( this.record.average_time ) );
            this.record.prompts = CnTranslationHelper.parseDescriptions( this.record.prompts );
            this.record.popups = CnTranslationHelper.parseDescriptions( this.record.popups );
            this.record.module_prompts = CnTranslationHelper.parseDescriptions( this.record.module_prompts );
            this.record.module_popups = CnTranslationHelper.parseDescriptions( this.record.module_popups );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );

  // extend the base model factory created by calling initQnairePartModule()
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
