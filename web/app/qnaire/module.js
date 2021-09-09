define( [ 'module' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {},
    name: {
      singular: 'questionnaire',
      plural: 'questionnaires',
      possessive: 'questionnaire\'s'
    },
    columnList: {
      name: {
        title: 'Name'
      },
      version: {
        title: 'Version'
      },
      base_language_id: {
        title: 'Base Language',
        column: 'base_language.name',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      closed: {
        title: 'Closed',
        type: 'boolean',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      debug: {
        title: 'Debug Mode',
        type: 'boolean',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      readonly: {
        title: 'Read-Only',
        type: 'boolean',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      stages: {
        title: 'Use Stages',
        type: 'boolean',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      repeat_detail: {
        title: 'Repeated',
        type: 'string',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      max_responses: {
        title: 'Max Responses',
        type: 'string',
        isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ); }
      },
      module_count: {
        title: 'Modules'
      },
      respondent_count: {
        title: 'Participants'
      }
    },
    defaultOrder: {
      column: 'name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      title: 'Name',
      type: 'string',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    version: {
      title: 'Version',
      type: 'string',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    variable_suffix: {
      title: 'Variable Suffix',
      type: 'string',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    base_language_id: {
      title: 'Base Language',
      type: 'enum',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    average_time: {
      title: 'Average Time',
      type: 'string',
      isConstant: true,
      isExcluded: 'add'
    },
    debug: {
      title: 'Debug Mode',
      type: 'boolean',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    readonly: {
      title: 'Read Only',
      type: 'boolean',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) ? true : 'add'; }
    },
    stages: {
      title: 'Stages',
      type: 'boolean',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) ? true : 'add'; }
    },
    closed: {
      title: 'Closed',
      type: 'boolean',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) ? true : 'add'; }
    },
    repeated: {
      title: 'Repeated',
      type: 'enum',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    repeat_offset: {
      title: 'Repeat Offset',
      type: 'string',
      format: 'integer',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) ? true : !model.viewModel.record.repeated; }
    },
    max_responses: {
      title: 'Maximum Number of Responses',
      type: 'string',
      format: 'integer',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) ? true : !model.viewModel.record.repeated; },
      help: 'If set to 0 then there will be no maximum number of responses'
    },
    email_invitation: {
      title: 'Send Invitation Email',
      type: 'boolean',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    email_from_name: {
      title: 'Email From Name',
      type: 'string',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    email_from_address: {
      title: 'Email From Address',
      type: 'string',
      format: 'email',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ); },
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    beartooth_url: {
      title: 'Beartooth URL',
      type: 'string',
      help: 'The URL used to connect to Beartooth. ' +
            'This information is never included in the import/export process.',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) || !model.isDetached(); }
    },
    beartooth_username: {
      title: 'Beartooth Username',
      type: 'string',
      help: 'The interviewing instance\'s username used to connect to Beartooth ' +
            'This information is never included in the import/export process.',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) || !model.isDetached(); }
    },
    beartooth_password: {
      title: 'Beartooth Password',
      type: 'string',
      help: 'The interviewing instance\'s password used to connect to Beartooth ' +
            'This information is never included in the import/export process.',
      isExcluded: function( $state, model ) { return model.isRole( 'interviewer' ) || !model.isDetached(); }
    },
    description: {
      title: 'Description',
      type: 'text',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    note: {
      title: 'Note',
      type: 'text',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    first_page_id: { isExcluded: true }
  } );

  module.addExtraOperation( 'list', {
    title: 'Import',
    operation: async function( $state, model ) { await $state.go( 'qnaire.import' ); },
    isIncluded: function( $state, model ) { return model.getEditEnabled(); }
  } );

  module.addExtraOperation( 'view', {
    title: 'Preview',
    isDisabled: function( $state, model ) { return !model.viewModel.record.first_page_id; },
    operation: async function( $state, model ) {
      await $state.go(
        'page.render',
        { identifier: model.viewModel.record.first_page_id },
        { reload: true }
      );
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Export',
    operation: async function( $state, model ) {
      await $state.go( 'qnaire.clone', { identifier: model.viewModel.record.getIdentifier() } );
    },
    isIncluded: function( $state, model ) { return model.getEditEnabled(); }
  } );

  module.addExtraOperation( 'view', {
    title: 'Patch',
    operation: async function( $state, model ) {
      await $state.go( 'qnaire.patch', { identifier: model.viewModel.record.getIdentifier() } );
    },
    isIncluded: function( $state, model ) { return model.getEditEnabled(); }
  } );

  module.addExtraOperation( 'view', {
    title: 'Test Connection',
    isIncluded: function( $state, model ) { return !model.isRole( 'interviewer' ) && model.isDetached(); },
    operation: function( $state, model ) { model.viewModel.testConnection(); },
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireAdd', [
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;

          // a special function to define whether to show certain inputs based on the repeated property
          function defineRepeatedExcludes() {
            var mainInputGroup = $scope.model.module.inputGroupList.findByProperty( 'title', '' );

            mainInputGroup.inputList.repeat_offset.isExcluded = function( $state, model ) {
              return !(
                'add' == model.getActionFromState() ?
                cnRecordAddScope.record.repeated :
                model.viewModel.record.repeated
              );
            };

            mainInputGroup.inputList.max_responses.isExcluded = function( $state, model ) {
              return !(
                'add' == model.getActionFromState() ?
                cnRecordAddScope.record.repeated :
                model.viewModel.record.repeated
              );
            };
          }

          var cnRecordAddScope = null;
          $scope.$on( 'cnRecordAdd ready', function( event, data ) {
            cnRecordAddScope = data;

            // add/remove inputs based on whether repeated is set to true or false
            var checkFunction = cnRecordAddScope.check;
            cnRecordAddScope.check = function( property ) {
              // run the original check function first
              checkFunction( property );
              if( 'repeated' == property ) defineRepeatedExcludes();
            };

            defineRepeatedExcludes();
          }, 500 );

        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireClone', [
    'CnQnaireCloneFactory', 'CnSession', '$state',
    function( CnQnaireCloneFactory, CnSession, $state ) {
      return {
        templateUrl: module.getFileUrl( 'clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireCloneFactory.instance();

          await $scope.model.onLoad();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Questionnaires',
            go: async function() { await $state.go( 'qnaire.list' ); }
          }, {
            title: $scope.model.sourceName,
            go: async function() { await $state.go( 'qnaire.view', { identifier: $scope.model.parentQnaireId } ); }
          }, {
            title: 'Export'
          } ] );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireImport', [
    'CnQnaireImportFactory', 'CnSession', '$state',
    function( CnQnaireImportFactory, CnSession, $state ) {
      return {
        templateUrl: module.getFileUrl( 'import.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireImportFactory.instance();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Questionnaires',
            go: async function() { await $state.go( 'qnaire.list' ); }
          }, {
            title: 'Import'
          } ] );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireMassRespondent', [
    'CnQnaireMassRespondentFactory', 'CnSession', '$state',
    function( CnQnaireMassRespondentFactory, CnSession, $state ) {
      return {
        templateUrl: module.getFileUrl( 'mass_respondent.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireMassRespondentFactory.instance();

          await $scope.model.onLoad();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Questionnaires',
            go: async function() { await $state.go( 'qnaire.list' ); }
          }, {
            title: $scope.model.qnaireName,
            go: async function() { await $state.go( 'qnaire.view', { identifier: $scope.model.qnaireId } ); }
          }, {
            title: 'Mass Respondent'
          } ] );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnairePatch', [
    'CnQnaireModelFactory', 'CnSession', '$state',
    function( CnQnaireModelFactory, CnSession, $state ) {
      return {
        templateUrl: module.getFileUrl( 'patch.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;

          await $scope.model.viewModel.onView();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Questionnaires',
            go: async function() { await $state.go( 'qnaire.list' ); }
          }, {
            title: $scope.model.viewModel.record.name,
            go: async function() { await $state.go( 'qnaire.view', { identifier: $scope.model.viewModel.record.getIdentifier() } ); }
          }, {
            title: 'Patch'
          } ] );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireList', [
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireView', [
    'CnQnaireModelFactory', 'CnModalConfirmFactory',
    function( CnQnaireModelFactory, CnModalConfirmFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;

          $scope.$on( 'cnRecordView ready', function( event, data ) {
            var cnRecordViewScope = data;
            cnRecordViewScope.basePatchFn = cnRecordViewScope.patch;

            cnRecordViewScope.patch = async function( property ) {
              var proceed = true;

              // warn that stages/deveiation-types will be deleted when switching to non-stages mode
              if( 'stages' == property && !$scope.model.viewModel.record.stages ) {
                var response = await CnModalConfirmFactory.instance( {
                  message:
                    'Turning off stages mode will automatically delete all stages and deviation types. ' +
                    'Are you sure you wish to proceed?'
                } ).show();
                if( !response ) {
                  // undo the change
                  $scope.model.viewModel.record.stages = true;
                  proceed = false;
                }
              }

              if( proceed ) await cnRecordViewScope.basePatchFn( property );
            }
          } );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireCloneFactory', [
    'CnHttpFactory', 'CnModalMessageFactory', '$state',
    function( CnHttpFactory, CnModalMessageFactory, $state ) {
      var object = function() {
        angular.extend( this, {
          parentQnaireId: $state.params.identifier,
          sourceName: null,
          working: false,
          operation: 'clone',
          name: null,
          nameConflict: false,

          onLoad: async function() {
            // reset data
            this.name = null;
            this.nameConflict = false;
            var response = await CnHttpFactory.instance( {
              path: 'qnaire/' + this.parentQnaireId,
              data: { select: { column: 'name' } }
            } ).get();
            this.sourceName = response.data.name;
          },
          isComplete: function() { return !this.working && !this.nameConflict && ( null != this.name || 'clone' != this.operation ); },
          cancel: async function() { await $state.go( 'qnaire.view', { identifier: this.parentQnaireId } ); },

          save: async function() {
            var self = this;
            var httpObj = {
              onError: function( error ) {
                if( 409 == error.status ) self.nameConflict = true;
                else CnModalMessageFactory.httpError( error );
              }
            };

            if( 'clone' == this.operation ) {
              httpObj.path = 'qnaire?clone=' + this.parentQnaireId;
              httpObj.data = { name: this.name };
            } else if( 'export' == this.operation ) {
              httpObj.path = 'qnaire/' + this.parentQnaireId + '?output=export'
              httpObj.format = 'txt';
            } else if( 'print' == this.operation ) {
              httpObj.path = 'qnaire/' + this.parentQnaireId + '?output=print'
              httpObj.format = 'txt';
            }

            try {
              this.working = true;
              var http = await CnHttpFactory.instance( httpObj );
              var response = await( 'clone' == this.operation ? http.post() : http.file() );
              if( 'clone' == this.operation ) await $state.go( 'qnaire.view', { identifier: response.data } );
            } finally {
              this.working = false;
            }
          }
        } );
      }
      return { instance: function() { return new object(); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireImportFactory', [
    'CnHttpFactory', '$state',
    function( CnHttpFactory, $state ) {
      var object = function() {
        angular.extend( this, {
          working: false,
          file: null,

          cancel: async function() { await $state.go( 'qnaire.list' ); },

          import: async function() {
            var data = new FormData();
            data.append( 'file', this.file );
            var fileDetails = data.get( 'file' );

            try {
              this.working = true;
              var response = await CnHttpFactory.instance( {
                path: 'qnaire?import=1',
                data: this.file
              } ).post();
              await $state.go( 'qnaire.view', { identifier: response.data } );
            } finally {
              this.working = false;
            }
          }
        } );
      }
      return { instance: function() { return new object(); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireMassRespondentFactory', [
    'CnSession', 'CnHttpFactory', 'CnModalMessageFactory', 'CnParticipantSelectionFactory', '$state',
    function( CnSession, CnHttpFactory, CnModalMessageFactory, CnParticipantSelectionFactory, $state ) {
      var object = function() {
        angular.extend( this, {
          working: false,
          participantSelection: CnParticipantSelectionFactory.instance( {
            path: ['qnaire', $state.params.identifier, 'participant'].join( '/' ),
            data: { mode: 'confirm' }
          } ),
          qnaireId: $state.params.identifier,
          qnaireName: null,

          onLoad: async function() {
            // reset data
            var response = await CnHttpFactory.instance( {
              path: 'qnaire/' + this.qnaireId,
              data: { select: { column: 'name' } }
            } ).get();

            this.qnaireName = response.data.name;
            this.participantSelection.reset();
          },

          proceed: async function() {
            if( !this.participantSelection.confirmInProgress && 0 < this.participantSelection.confirmedCount ) {
              try {
                this.working = true;
                var self = this;
                var response = await CnHttpFactory.instance( {
                  path: ['qnaire', this.qnaireId, 'participant'].join( '/' ),
                  data: {
                    mode: 'create',
                    identifier_id: this.participantSelection.identifierId,
                    identifier_list: this.participantSelection.getIdentifierList()
                  },
                  onError: async function( error ) {
                    await CnModalMessageFactory.httpError( error );
                    self.onLoad();
                  }
                } ).post();

                await CnModalMessageFactory.instance( {
                  title: 'Respondents Created',
                  message: 'You have successfully created ' + this.participantSelection.confirmedCount + ' new recipients for the "' +
                           this.qnaireName + '" questionnaire.'
                } ).show();
                await this.onLoad();
              } finally {
                this.working = false;
              }
            }
          }

        } );
      }
      return { instance: function() { return new object(); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireViewFactory', [
    'CnBaseViewFactory', 'CnHttpFactory', 'CnModalMessageFactory', '$filter', '$state', '$rootScope',
    function( CnBaseViewFactory, CnHttpFactory, CnModalMessageFactory, $filter, $state, $rootScope ) {
      var object = function( parentModel, root ) {
        // the respondent only has one list (respondent list) so the default tab for them is null
        CnBaseViewFactory.construct( this, parentModel, root, parentModel.isRole( 'interviewer' ) ? null : 'respondent' );

        angular.extend( this, {
          uploadReadReady: false,
          working: false,
          file: null,
          difference: null,
          differenceIsEmpty: false,

          // only show stage and deviation types in qnaires with stages and only show the respondent list to respondents
          getChildList: function() {
            return this.$$getChildList().filter( child =>
              ( this.record.stages || !['stage', 'deviation_type', 'qnaire_consent_type_confirm' ].includes( child.subject.snake ) ) &&
              ( !this.parentModel.isRole( 'interviewer' ) || 'respondent' == child.subject.snake )
            );
          },

          onView: async function( force ) {
            await this.$$onView( force );
            this.record.average_time = $filter( 'cnSeconds' )( Math.round( this.record.average_time ) );
            this.working = false;
            this.file = null;
            this.difference = null;
            this.differenceIsEmpty = false;

            // make some columns dependent on the parent qnaire
            var respondentModule = cenozoApp.module( 'respondent' );
            respondentModule.columnList.language.isIncluded = function( $state, model ) {
              return !self.record.repeated;
            };
            respondentModule.columnList.response_count.isIncluded = function( $state, model ) {
              return self.record.repeated;
            };
          },

          onPatch: async function( data ) {
            await this.$$onPatch( data );
            if( angular.isDefined( data.repeated ) && data.repeated ) await this.onView();
          },

          cancel: async function() { await $state.go( 'qnaire.view', { identifier: this.record.getIdentifier() } ); },

          checkPatch: function() {
            if( !this.uploadReadReady ) {
              var self = this;
              // need to wait for cnUplod to do its thing
              $rootScope.$on( 'cnUpload read', async function() {
                try {
                  self.working = true;
                  self.uploadReadReady = true;

                  var data = new FormData();
                  data.append( 'file', self.file );

                  // check the patch file
                  var response = await CnHttpFactory.instance( {
                    path: self.parentModel.getServiceResourcePath() + '?patch=check',
                    data: self.file
                  } ).patch();

                  self.difference = response.data;
                  self.differenceIsEmpty = 0 == Object.keys( self.difference ).length;
                } finally {
                  self.working = false;
                }
              } );
            }
          },

          applyPatch: async function() {
            try {
              // apply the patch file
              this.working = true;
              await CnHttpFactory.instance( {
                path: this.parentModel.getServiceResourcePath() + '?patch=apply',
                data: this.file
              } ).patch();
              await $state.go( 'qnaire.view', { identifier: this.record.getIdentifier() } );
            } finally {
              this.working = false;
            }
          },

          testConnection: async function() {
            var response = await CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '?test_connection=1'
            } ).get();

            await CnModalMessageFactory.instance( {
              title: 'Test Connection',
              message: response.data
            } ).show();
          }
        } );

        var self = this;
        async function init() {
          await self.deferred.promise;

          if( angular.isDefined( self.moduleModel ) ) {
            self.moduleModel.getAddEnabled = function() {
              return !self.record.readonly && self.moduleModel.$$getAddEnabled();
            }
            self.moduleModel.getDeleteEnabled = function() {
              return !self.record.readonly && self.moduleModel.$$getDeleteEnabled();
            }
          }

          if( angular.isDefined( self.attributeModel ) ) {
            self.attributeModel.getAddEnabled = function() {
              return !self.record.readonly && self.attributeModel.$$getAddEnabled();
            }
            self.attributeModel.getDeleteEnabled = function() {
              return !self.record.readonly && self.attributeModel.$$getDeleteEnabled();
            }
          }
        }

        init();
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireModelFactory', [
    'CnBaseModelFactory', 'CnQnaireAddFactory', 'CnQnaireListFactory', 'CnQnaireViewFactory', 'CnHttpFactory', 'CnSession',
    function( CnBaseModelFactory, CnQnaireAddFactory, CnQnaireListFactory, CnQnaireViewFactory, CnHttpFactory, CnSession ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQnaireAddFactory.instance( this );
        this.listModel = CnQnaireListFactory.instance( this );
        this.viewModel = CnQnaireViewFactory.instance( this, root );

        angular.extend( this, {
          getBreadcrumbTitle: function() { return this.viewModel.record.name; },

          isDetached: function() { return CnSession.setting.detached; },

          // override the service collection path so that respondents can view the qnaire list from the home screen
          getServiceCollectionPath: function() {
            // ignore the parent if it is root
            return this.$$getServiceCollectionPath( 'root' == this.getSubjectFromState() );
          },

          // extend getMetadata
          getMetadata: async function() {
            await this.$$getMetadata();

            var response = await CnHttpFactory.instance( {
              path: 'language',
              data: {
                select: { column: [ 'id', 'name', 'code' ] },
                modifier: {
                  where: { column: 'active', operator: '=', value: true },
                  order: 'name',
                  limit: 1000
                }
              }
            } ).query();

            this.metadata.columnList.base_language_id.enumList = [];
            var self = this;
            response.data.forEach( function( item ) {
              self.metadata.columnList.base_language_id.enumList.push( {
                value: item.id,
                name: item.name,
                code: item.code // code is needed by the withdraw action
              } );
            } );
          }
        } );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
