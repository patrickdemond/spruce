define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'page', true ); } catch( err ) { console.warn( err ); return; }
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
      name: {
        title: 'Name'
      },
      description: {
        title: 'Description',
        align: 'left'
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
    description: {
      title: 'Description',
      type: 'text'
    },
    note: {
      title: 'Note',
      type: 'text'
    },
    previous_page_id: {
      type: 'string',
      exclude: true
    },
    next_page_id: {
      type: 'string',
      exclude: true
    },
    last_page: {
      type: 'boolean',
      exclude: true
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Preview',
    operation: function( $state, model ) { $state.go( 'page.render', { identifier: model.viewModel.record.getIdentifier() } ); }
  } );

  module.addExtraOperation( 'view', {
    title: 'Previous Page',
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_page_id; },
    operation: function( $state, model ) {
      model.transitionToViewState( { getIdentifier: function() { return model.viewModel.record.previous_page_id; } } );
    }
  } );

  module.addExtraOperation( 'view', {
    title: 'Next Page',
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_page_id; },
    operation: function( $state, model ) {
      model.transitionToViewState( { getIdentifier: function() { return model.viewModel.record.next_page_id; } } );
    }
  } );

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
    'CnPageModelFactory', '$q',
    function( CnPageModelFactory, $q ) {
      return {
        templateUrl: module.getFileUrl( 'render.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          $scope.isComplete = false;
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
          $q.all( [
            $scope.model.viewModel.onView(),
            $scope.model.renderModel.onLoad()
          ] ).then( function() {
            $scope.isComplete = true;
          } );
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
    'CnHttpFactory', '$state',
    function( CnHttpFactory, $state ) {
      var object = function( parentModel ) {
        var self = this;

        angular.extend( this, {
          parentModel: parentModel,
          questionList: [],
          onLoad: function() {
            return CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '/question'
            } ).query().then( function( response ) {
              self.questionList = response.data;
            } );
          },
          back: function() {
            $state.go( 'page.render', { identifier: this.parentModel.viewModel.record.previous_page_id } );
          },
          next: function() {
            $state.go( 'page.render', { identifier: this.parentModel.viewModel.record.next_page_id } );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageModelFactory', [
    'CnBaseModelFactory', 'CnPageAddFactory', 'CnPageListFactory', 'CnPageRenderFactory', 'CnPageViewFactory',
    function( CnBaseModelFactory, CnPageAddFactory, CnPageListFactory, CnPageRenderFactory, CnPageViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnPageAddFactory.instance( this );
        this.listModel = CnPageListFactory.instance( this );
        this.renderModel = CnPageRenderFactory.instance( this );
        this.viewModel = CnPageViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
