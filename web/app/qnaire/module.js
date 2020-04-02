define( function() {
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
      base_language_id: {
        title: 'Base Language',
        column: 'base_language.name'
      },
      debug: {
        title: 'Debug Mode',
        type: 'boolean'
      },
      readonly: {
        title: 'Read-Only',
        type: 'boolean'
      },
      repeat_detail: {
        title: 'Repeated',
        type: 'string'
      },
      max_responses: {
        title: 'Max Responses',
        type: 'string'
      },
      module_count: {
        title: 'Modules'
      },
      respondent_count: {
        title: 'Participants'
      },
      description: {
        title: 'Description',
        align: 'left'
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
    base_language_id: {
      title: 'Base Language',
      type: 'enum',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    average_time: {
      title: 'Average Time',
      type: 'string',
      format: 'seconds',
      isConstant: true,
      isExcluded: 'add'
    },
    debug: {
      title: 'Debug Mode',
      type: 'boolean',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    readonly: {
      title: 'Read Only',
      type: 'boolean'
    },
    repeated: {
      title: 'Repeated',
      type: 'enum',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    repeat_offset: {
      title: 'Repeat Offset',
      type: 'string',
      format: 'integer',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) { return !model.viewModel.record.repeated; }
    },
    max_responses: {
      title: 'Maximum Number of Responses',
      type: 'string',
      format: 'integer',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) { return !model.viewModel.record.repeated; },
      help: 'If set to 0 then there will be no maximum number of responses'
    },
    email_invitation: {
      title: 'Send Invitation Email',
      type: 'boolean',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    email_reminder: {
      title: 'Send Reminder Email',
      type: 'enum',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    email_reminder_offset: {
      title: 'Reminder Email Offset',
      type: 'string',
      format: 'integer',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) { return !model.viewModel.record.email_reminder; }
    },
    email_from_name: {
      title: 'Email From Name',
      type: 'string',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) {
        return !model.viewModel.record.email_invitation && !model.viewModel.record.email_reminder;
      }
    },
    email_from_address: {
      title: 'Email From Address',
      type: 'string',
      format: 'email',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; },
      isExcluded: function( $state, model ) {
        return !model.viewModel.record.email_invitation && !model.viewModel.record.email_reminder;
      }
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

  module.addExtraOperation( 'view', {
    title: 'Preview',
    isDisabled: function( $state, model ) { return !model.viewModel.record.first_page_id; },
    operation: function( $state, model ) {
      $state.go(
        'page.render',
        { identifier: model.viewModel.record.first_page_id },
        { reload: true }
      );
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Clone',
    operation: function( $state, model ) {
      $state.go( 'qnaire.clone', { identifier: model.viewModel.record.getIdentifier() } );
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Mass Respondent',
    operation: function( $state, model ) {
      $state.go( 'qnaire.mass_respondent', { identifier: model.viewModel.record.getIdentifier() } );
    }
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

          // a special function to define whether to show certain inputs based on the email_reminder property
          function defineEmailReminderExcludes() {
            var mainInputGroup = $scope.model.module.inputGroupList.findByProperty( 'title', '' );

            mainInputGroup.inputList.email_reminder_offset.isExcluded = function( $state, model ) {
              return !(
                'add' == model.getActionFromState() ?
                cnRecordAddScope.record.email_reminder :
                model.viewModel.record.email_reminder
              );
            };

            mainInputGroup.inputList.email_from_name.isExcluded = function( $state, model ) {
              return !(
                'add' == model.getActionFromState() ?
                cnRecordAddScope.record.email_invitation || cnRecordAddScope.record.email_reminder :
                model.viewModel.record.email_invitation || model.viewModel.record.email_reminder
              );
            };

            mainInputGroup.inputList.email_from_address.isExcluded = function( $state, model ) {
              return !(
                'add' == model.getActionFromState() ?
                cnRecordAddScope.record.email_invitation || cnRecordAddScope.record.email_reminder :
                model.viewModel.record.email_invitation || model.viewModel.record.email_reminder
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
              else if( 'email_reminder' == property ) defineEmailReminderExcludes();
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
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireCloneFactory.instance();

          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Questionnaires',
              go: function() { return $state.go( 'qnaire.list' ); }
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'qnaire.view', { identifier: $scope.model.parentQnaireId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
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
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireMassRespondentFactory.instance();

          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Questionnaires',
              go: function() { return $state.go( 'qnaire.list' ); }
            }, {
              title: $scope.model.qnaireName,
              go: function() { return $state.go( 'qnaire.view', { identifier: $scope.model.qnaireId } ); }
            }, {
              title: 'Mass Respondent'
            } ] );
          } );
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
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;
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
        var self = this;
        angular.extend( this, {
          parentQnaireId: $state.params.identifier,
          sourceName: null,
          working: false,
          name: null,
          nameConflict: false,

          onLoad: function() {
            // reset data
            this.name = null;
            this.nameConflict = false;
            return CnHttpFactory.instance( {
              path: 'qnaire/' + this.parentQnaireId,
              data: { select: { column: 'name' } }
            } ).get().then( function( response ) {
              self.sourceName = response.data.name;
            } );
          },
          isComplete: function() { return !this.working && !this.nameConflict && null != this.name; },
          cancel: function() { $state.go( 'qnaire.view', { identifier: this.parentQnaireId } ); },

          save: function() {
            this.working = true;

            return CnHttpFactory.instance( {
              path: 'qnaire?clone=' + this.parentQnaireId,
              data: { name: this.name },
              onError: function( response ) {
                if( 409 == response.status ) self.nameConflict = true;
                else CnModalMessageFactory.httpError( response );
              }
            } ).post().then( function( response ) {
              $state.go( 'qnaire.view', { identifier: response.data } );
            } ).finally( function() {
              self.working = false;
            } );
          }
        } );
      }
      return { instance: function() { return new object(); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireMassRespondentFactory', [
    'CnSession', 'CnHttpFactory', 'CnModalMessageFactory', '$state',
    function( CnSession, CnHttpFactory, CnModalMessageFactory, $state ) {
      var object = function() {
        var self = this;
        angular.extend( this, {
          qnaireId: $state.params.identifier,
          qnaireName: null,
          confirmInProgress: false,
          confirmedCount: null,
          uidListString: '',
          uidList: [],

          onLoad: function() {
            // reset data
            return CnHttpFactory.instance( {
              path: 'qnaire/' + this.qnaireId,
              data: { select: { column: 'name' } }
            } ).get().then( function( response ) {
              self.qnaireName = response.data.name;
              self.confirmInProgress = false;
              self.confirmedCount = null;
              self.uidListString = '';
              self.uidList = [];
            } );
          },

          uidListStringChanged: function() {
            this.confirmedCount = null;
          },

          confirm: function() {
            this.confirmInProgress = true;
            this.confirmedCount = null;
            var uidRegex = new RegExp( CnSession.application.uidRegex );

            // clean up the uid list
            this.uidList =
              this.uidListString.toUpperCase() // convert to uppercase
                          .replace( /[\s,;|\/]/g, ' ' ) // replace whitespace and separation chars with a space
                          .replace( /[^a-zA-Z0-9 ]/g, '' ) // remove anything that isn't a letter, number of space
                          .split( ' ' ) // delimite string by spaces and create array from result
                          .filter( function( uid ) { // match UIDs (eg: A123456)
                            return null != uid.match( uidRegex );
                          } )
                          .filter( function( uid, index, array ) { // make array unique
                            return index <= array.indexOf( uid );
                          } )
                          .sort(); // sort the array

            // now confirm UID list with server
            if( 0 == this.uidList.length ) {
              this.uidListString = '';
              this.confirmInProgress = false;
            } else {
              CnHttpFactory.instance( {
                path: ['qnaire', this.qnaireId, 'participant'].join( '/' ),
                data: { mode: 'confirm', uid_list: this.uidList }
              } ).post().then( function( response ) {
                self.confirmedCount = response.data.length;
                self.uidListString = response.data.join( ' ' );
                self.confirmInProgress = false;
              } );
            }
          },

          proceed: function() {
            if( !this.confirmInProgress && 0 < this.confirmedCount ) {
              CnHttpFactory.instance( {
                path: ['qnaire', this.qnaireId, 'participant'].join( '/' ),
                data: { mode: 'release', uid_list: this.uidList }
              } ).post().then( function( response ) {
                CnModalMessageFactory.instance( {
                  title: 'Recipients Created',
                  message: 'You have successfully created ' + self.confirmedCount + ' new recipients for the "' +
                           self.qnaireName + '" questionnaire.'
                } ).show().then( function() { self.onLoad(); } );
              } );
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
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );

        this.deferred.promise.then( function() {
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
        } );

        this.onPatch = function( data ) {
          return this.$$onPatch( data ).then( function() {
            if( angular.isDefined( data.repeated ) && data.repeated ) self.onView();
          } );
        };
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireModelFactory', [
    'CnBaseModelFactory', 'CnQnaireAddFactory', 'CnQnaireListFactory', 'CnQnaireViewFactory', 'CnHttpFactory',
    function( CnBaseModelFactory, CnQnaireAddFactory, CnQnaireListFactory, CnQnaireViewFactory, CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQnaireAddFactory.instance( this );
        this.listModel = CnQnaireListFactory.instance( this );
        this.viewModel = CnQnaireViewFactory.instance( this, root );

        this.getBreadcrumbTitle = function() { return this.viewModel.record.name; };

        // extend getMetadata
        this.getMetadata = function() {
          return this.$$getMetadata().then( function() {
            return CnHttpFactory.instance( {
              path: 'language',
              data: {
                select: { column: [ 'id', 'name', 'code' ] },
                modifier: {
                  where: { column: 'active', operator: '=', value: true },
                  order: 'name'
                }
              }
            } ).query().then( function success( response ) {
              self.metadata.columnList.base_language_id.enumList = [];
              response.data.forEach( function( item ) {
                self.metadata.columnList.base_language_id.enumList.push( {
                  value: item.id,
                  name: item.name,
                  code: item.code // code is needed by the withdraw action
                } );
              } );
            } );
          } );
        };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
