define( [ 'page' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'response', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'response',
      plural: 'responses',
      possessive: 'response\'s'
    },
    columnList: {
      uid: {
        column: 'participant.uid',
        title: 'Participant'
      },
      language: {
        column: 'language.code',
        title: 'Language'
      },
      token: {
        title: 'Token'
      },
      submitted: {
        title: 'Submitted',
        type: 'boolean'
      },
      module: {
        column: 'module.name',
        title: 'Module'
      },
      page: {
        column: 'page.name',
        title: 'Page'
      },
      start_datetime: {
        title: 'Start',
        type: 'datetime'
      },
      last_datetime: {
        title: 'Last',
        type: 'datetime'
      }
    },
    defaultOrder: {
      column: 'start_datetime',
      reverse: true
    }
  } );

  module.addInputGroup( '', {
    participant_id: {
      column: 'response.participant_id',
      title: 'Participant',
      type: 'lookup-typeahead',
      typeahead: {
        table: 'participant',
        select: 'CONCAT( participant.first_name, " ", participant.last_name, " (", uid, ")" )',
        where: [ 'participant.first_name', 'participant.last_name', 'uid' ]
      },
      isConstant: 'view'
    },
    language_id: {
      column: 'response.language_id',
      title: 'Language',
      type: 'enum',
      isExcluded: 'add'
    },
    token: {
      title: 'Token',
      type: 'string',
      isExcluded: 'add'
    },
    submitted: {
      title: 'Submitted',
      type: 'boolean',
      isConstant: true,
      isExcluded: 'add'
    },
    module: {
      column: 'module.name',
      title: 'Module',
      type: 'string',
      isConstant: true,
      isExcluded: 'add'
    },
    page: {
      column: 'page.name',
      title: 'Page',
      type: 'string',
      isConstant: true,
      isExcluded: 'add'
    },
    start_datetime: {
      title: 'Start Date & Time',
      type: 'datetime',
      isExcluded: 'add'
    },
    last_datetime: {
      title: 'Last Date & Time',
      type: 'datetime',
      isExcluded: 'add'
    },
    page_id: { isExcluded: true }
  } );

  module.addExtraOperation( 'view', {
    title: 'Launch',
    operation: function( $state, model ) {
      $state.go( 'response.run', { token: model.viewModel.record.token } );
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseAdd', [
    'CnResponseModelFactory',
    function( CnResponseModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseList', [
    'CnResponseModelFactory',
    function( CnResponseModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseRun', [
    'CnResponseModelFactory',
    function( CnResponseModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'run.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseView', [
    'CnResponseModelFactory',
    function( CnResponseModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseModelFactory', [
    'CnBaseModelFactory', 'CnResponseAddFactory', 'CnResponseListFactory', 'CnResponseViewFactory', 'CnHttpFactory',
    function( CnBaseModelFactory, CnResponseAddFactory, CnResponseListFactory, CnResponseViewFactory, CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnResponseAddFactory.instance( this );
        this.listModel = CnResponseListFactory.instance( this );
        this.viewModel = CnResponseViewFactory.instance( this, root );

        this.getMetadata = function() {
          return this.$$getMetadata().then( function() {
            return CnHttpFactory.instance( {
              path: 'language',
              data: {
                select: { column: [ 'id', 'name' ] }, 
                modifier: {
                  where: { column: 'active', operator: '=', value: true },
                  order: 'name'
                }
              }
            } ).query().then( function success( response ) {
              self.metadata.columnList.language_id.enumList = [];
              response.data.forEach( function( item ) {
                self.metadata.columnList.language_id.enumList.push( {
                  value: item.id,
                  name: item.name
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
