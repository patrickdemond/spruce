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
      response_count: {
        title: 'Respondents'
      },
      invitation: {
        title: 'Invitation'
      },
      reminder: {
        title: 'Reminder'
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
      title: 'Token',
      type: 'string',
      isExcluded: 'add'
    },
    invitation: {
      title: 'Invitation',
      type: 'string',
      isConstant: true,
      isExcluded: 'add'
    },
    reminder: {
      title: 'Reminder',
      type: 'string',
      isConstant: true,
      isExcluded: 'add'
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Launch',
    operation: function( $state, model ) {
      $state.go( 'respondent.run', { token: model.viewModel.record.token } );
    }
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
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRespondentModelFactory', [
    'CnBaseModelFactory', 'CnRespondentAddFactory', 'CnRespondentListFactory', 'CnRespondentViewFactory',
    'CnHttpFactory',
    function( CnBaseModelFactory, CnRespondentAddFactory, CnRespondentListFactory, CnRespondentViewFactory,
              CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnRespondentAddFactory.instance( this );
        this.listModel = CnRespondentListFactory.instance( this );
        this.viewModel = CnRespondentViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
