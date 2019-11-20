define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'module', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.name'
      }
    },
    name: {
      singular: 'module',
      plural: 'modules',
      possessive: 'module\'s'
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
      help: 'A special expression which restricts whether or not to show this module.'
    },
    note: {
      title: 'Note',
      type: 'text'
    },

    previous_module_id: { isExcluded: true },
    next_module_id: { isExcluded: true },
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
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPreviousModule(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_module_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNextModule(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_module_id; }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnModuleAdd', [
    'CnModuleModelFactory',
    function( CnModuleModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModuleModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnModuleList', [
    'CnModuleModelFactory',
    function( CnModuleModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModuleModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnModuleView', [
    'CnModuleModelFactory',
    function( CnModuleModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModuleModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleViewFactory', [
    'CnBaseViewFactory', '$state',
    function( CnBaseViewFactory, $state ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );

        angular.extend( this, {
          viewPreviousModule: function() {
            $state.go(
              'module.view',
              { identifier: this.record.previous_module_id },
              { reload: true }
            );
          },
          viewNextModule: function() {
            $state.go(
              'module.view',
              { identifier: this.record.next_module_id },
              { reload: true }
            );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleModelFactory', [
    'CnBaseModelFactory', 'CnModuleAddFactory', 'CnModuleListFactory', 'CnModuleViewFactory',
    function( CnBaseModelFactory, CnModuleAddFactory, CnModuleListFactory, CnModuleViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnModuleAddFactory.instance( this );
        this.listModel = CnModuleListFactory.instance( this );
        this.viewModel = CnModuleViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
