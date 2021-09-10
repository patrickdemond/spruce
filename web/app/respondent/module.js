define( [ 'page' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'respondent', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'respondent',
      plural: 'respondents',
      possessive: 'respondent\'s'
    },
    columnList: {
      uid: {
        column: 'participant.uid',
        title: 'Participant'
      },
      token: {
        title: 'Token'
      },
      language: {
        column: 'language.name',
        title: 'Language',
        type: 'string',
        isIncluded: function( $state, model ) { return false; } // this is changed by the qnaire module
      },
      response_count: {
        title: 'Responses',
        isIncluded: function( $state, model ) { return false; } // this is changed by the qnaire module
      },
      status: {
        title: 'Status',
        type: 'string'
      },
      start_datetime: {
        title: 'Start Date',
        type: 'datetime'
      },
      end_datetime: {
        title: 'End Date',
        type: 'datetime'
      }
    },
    defaultOrder: {
      column: 'participant.uid',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    participant_id: {
      column: 'respondent.participant_id',
      title: 'Participant',
      type: 'lookup-typeahead',
      typeahead: {
        table: 'participant',
        select: 'CONCAT( participant.first_name, " ", participant.last_name, " (", uid, ")" )',
        where: [ 'participant.first_name', 'participant.last_name', 'uid' ]
      },
      isConstant: 'view'
    },
    token: {
      title: 'Token (Interview ID)',
      type: 'string',
      isExcluded: 'add'
    },
    exported: {
      title: 'Exported',
      type: 'boolean',
      isConstant: function( $state, model ) { return null == model.viewModel.record.end_datetime; },
      isExcluded: function( $state, model ) { return 'view' != model.getActionFromState() || !model.isDetached(); }
    },
    start_datetime: {
      title: 'Start Date & Time',
      type: 'datetime',
      isConstant: true,
      isExcluded: 'add'
    },
    end_datetime: {
      title: 'End Date & Time',
      type: 'datetime',
      isConstant: true,
      isExcluded: 'add'
    },
    sends_mail: {
      type: 'boolean',
      isExcluded: true
    },
    repeated: {
      column: 'qnaire.repeated',
      type: 'string',
      isExcluded: true
    },
    stages: {
      column: 'qnaire.stages',
      type: 'boolean',
      isExcluded: true
    },
    current_response_id: {
      column: 'response.id',
      type: 'string',
      isExcluded: true
    }
  } );

  module.addInputGroup( 'Response', {
    qnaire_version: {
      column: 'response.qnaire_version',
      title: 'Questionnaire Version',
      type: 'string',
      isConstant: true,
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
    language_id: {
      column: 'response.language_id',
      title: 'Language',
      type: 'enum',
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
    checked_in: {
      column: 'response.checked_in',
      title: 'Checked In',
      type: 'boolean',
      isConstant: true,
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
    module: {
      column: 'module.name',
      title: 'Module',
      type: 'string',
      isConstant: true,
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
    page: {
      column: 'page.name',
      title: 'Page',
      type: 'string',
      isConstant: true,
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
    comments: {
      column: 'response.comments',
      title: 'Comments',
      type: 'text',
      isExcluded: function( $state, model ) {
        return 'view' != model.getActionFromState() ||
               null != model.viewModel.record.repeated ||
               null == model.viewModel.record.current_response_id;
      }
    },
  }, true );

  module.addExtraOperation( 'list', {
    title: 'Get Respondents',
    operation: function( $state, model ) {
      model.getRespondents();
    },
    isIncluded: function( $state, model ) { return model.isDetached() },
    isDisabled: function( $state, model ) { return model.workInProgress; }
  } );

  module.addExtraOperation( 'list', {
    title: 'Export Completed',
    operation: async function( $state, model ) {
      await model.export();
    },
    isIncluded: function( $state, model ) { return model.isDetached() },
    isDisabled: function( $state, model ) { return model.workInProgress; }
  } );

  module.addExtraOperation( 'list', {
    title: 'Mass Respondent',
    operation: async function( $state, model ) {
      await $state.go( 'qnaire.mass_respondent', { identifier: $state.params.identifier } );
    },
    isIncluded: function( $state, model ) { return !model.isDetached(); }
  } );

  module.addExtraOperation( 'view', {
    title: 'Export',
    operation: async function( $state, model ) {
      await model.export( model.getIdentifierFromRecord( model.viewModel.record ) );
    },
    isIncluded: function( $state, model ) {
      return model.isDetached() && null != model.viewModel.record.end_datetime && !model.viewModel.record.exported;
    },
    isDisabled: function( $state, model ) { return model.workInProgress; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Display',
    operation: async function( $state, model ) {
      await $state.go( 'response.display', { identifier: model.viewModel.record.current_response_id } );
    },
    isIncluded: function( $state, model ) { return null == model.viewModel.record.repeated; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Reopen',
    operation: async function( $state, model ) { await model.viewModel.reopen(); },
    isIncluded: function( $state, model ) { return null != model.viewModel.record.end_datetime; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Launch',
    operation: async function( $state, model ) {
      await $state.go( 'respondent.run', { token: model.viewModel.record.token } );
    },
    isIncluded: function( $state, model ) { return null == model.viewModel.record.end_datetime; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Re-schedule Email',
    operation: async function( $state, model ) {
      try {
        model.viewModel.resendMail();
      } finally {
        if( angular.isDefined( model.viewModel.respondentMailModel ) )
          await model.viewModel.respondentMailModel.listModel.onList( true );
      }
    },
    isIncluded: function( $state, model ) { return model.viewModel.record.sends_mail; },
    help: 'This will re-schedule all mail for this respondent. ' + 
      'This is useful if mail was never sent or if email settings have changed since email was last scheduled.'
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRespondentAdd', [
    'CnRespondentModelFactory',
    function( CnRespondentModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRespondentModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRespondentList', [
    'CnRespondentModelFactory',
    function( CnRespondentModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRespondentModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRespondentRun', [
    'CnRespondentModelFactory',
    function( CnRespondentModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'run.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRespondentModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRespondentView', [
    'CnRespondentModelFactory',
    function( CnRespondentModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRespondentModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRespondentAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) {
        CnBaseAddFactory.construct( this, parentModel );

        // transition to viewing the new record instead of the default functionality
        this.transitionOnSave = function( record ) { parentModel.transitionToViewState( record ); };
      };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRespondentListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRespondentViewFactory', [
    'CnBaseViewFactory', 'CnHttpFactory',
    function( CnBaseViewFactory, CnHttpFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );

        angular.extend( this, {
          onView: async function( force ) {
            this.defaultTab = null;
            await this.$$onView( force );
            this.defaultTab = this.record.repeated ? 'response'
                            : this.record.stages ? 'response_stage'
                            : this.record.sends_mail ? 'respondent_mail'
                            : 'response_attribute';
            if( !this.tab ) this.tab = this.defaultTab;
          },

          getChildList: function() {
            var self = this;
            var list = this.$$getChildList().filter(
              child => (
                // show the response list if the qnaire is answered more than once
                'response' == child.subject.snake && null != self.record.repeated
              ) || (
                // show mail list if the qnaire sends mail
                'respondent_mail' == child.subject.snake && self.record.sends_mail
              ) || (
                // show stage list if the qnaire has stages and the qnaire is only answered once
                'response_stage' == child.subject.snake && self.record.stages && null == self.record.repeated
              ) || (
                // show attribute list if the qnaire is only answered once
                'response_attribute' == child.subject.snake && null == self.record.repeated
              )
            );
            return list;
          },

          reopen: async function() {
            await CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '?action=reopen'
            } ).patch();
            this.parentModel.reloadState( true );
          },

          resendMail: async function() {
            await CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '?action=resend_mail'
            } ).patch();
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRespondentModelFactory', [
    'CnBaseModelFactory', 'CnRespondentAddFactory', 'CnRespondentListFactory', 'CnRespondentViewFactory',
    'CnModalMessageFactory', 'CnSession', 'CnHttpFactory', '$state',
    function( CnBaseModelFactory, CnRespondentAddFactory, CnRespondentListFactory, CnRespondentViewFactory,
              CnModalMessageFactory, CnSession, CnHttpFactory, $state ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnRespondentAddFactory.instance( this );
        this.listModel = CnRespondentListFactory.instance( this );
        this.viewModel = CnRespondentViewFactory.instance( this, root );

        angular.extend( this, {
          workInProgress: false,

          isDetached: function() { return CnSession.setting.detached; },

          getMetadata: async function() {
            var self = this;
            await this.$$getMetadata();

            var response = await CnHttpFactory.instance( {
              path: 'language',
              data: {
                select: { column: [ 'id', 'name' ] },
                modifier: {
                  where: { column: 'active', operator: '=', value: true },
                  order: 'name',
                  limit: 1000
                }
              }
            } ).query();
            this.metadata.columnList.language_id = { enumList: [] };
            response.data.forEach( function( item ) {
              self.metadata.columnList.language_id.enumList.push( {
                value: item.id, name: item.name
              } );
            } );
          },

          getRespondents: async function() {
            var modal = CnModalMessageFactory.instance( {
              title: 'Communicating with Remote Server',
              message: 'Please wait while the respondent list is retrieved.',
              block: true
            } );
            modal.show();

            try {
              this.workInProgress = true;
              await CnHttpFactory.instance( {
                path: 'qnaire/' + $state.params.identifier + '/respondent?action=get_respondents'
              } ).post();
              await this.listModel.onList( true );
            } finally {
              modal.close();
              this.workInProgress = false;
            }
          },

          export: async function( respondentId ) {
            var modal = CnModalMessageFactory.instance( {
              title: 'Communicating with Remote Server',
              message: 'Please wait while the questionnaire data is exported.',
              block: true
            } );
            modal.show();

            try {
              this.workInProgress = true;
              var http = CnHttpFactory.instance( {
                path: angular.isDefined( respondentId )
                  ? 'respondent/' + respondentId + '?action=export'
                  : 'qnaire/' + $state.params.identifier + '/respondent?action=export'
              } );
              var response = angular.isDefined( respondentId ) ? await http.patch() : await http.post();

              CnModalMessageFactory.instance( {
                title: 'Export Complete',
                message: angular.isDefined( respondentId )
                  ? 'The respondent has been exported.'
                  : 0 < response.data.length
                  ? 'The following respondents have been exported:\n\n' + response.data.join( ', ' )
                  : 'No respondents have been exported.'
              } ).show();

              if( angular.isDefined( respondentId ) ) {
                await this.viewModel.onView( true );
              } else {
                await this.listModel.onList( true );
              }
            } finally {
              modal.close();
              this.workInProgress = false;
            }
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
