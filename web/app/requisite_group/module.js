define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'requisite_group', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: [ {
      parent: {
        subject: 'module',
        column: 'module.id'
      }
    }, {
      parent: {
        subject: 'page',
        column: 'page.id'
      }
    }, {
      parent: {
        subject: 'question',
        column: 'question.id'
      }
    }, {
      parent: {
        subject: 'requisite_group',
        column: 'requisite_group.id'
      }
    } ],
    name: {
      singular: 'requisite group',
      plural: 'requisite groups',
      possessive: 'requisite group\'s'
    },
    columnList: {
      rank: {
        title: 'Rank',
        type: 'rank'
      },
      logic: {
        title: 'Logic'
      },
      negative: {
        title: 'Negative',
        type: 'boolean'
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
    logic: {
      title: 'Logic',
      type: 'enum'
    },
    negative: {
      title: 'Negative',
      type: 'boolean' 
    },
    note: {
      title: 'Note',
      type: 'text'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRequisiteGroupAdd', [
    'CnRequisiteGroupModelFactory',
    function( CnRequisiteGroupModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRequisiteGroupModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRequisiteGroupList', [
    'CnRequisiteGroupModelFactory',
    function( CnRequisiteGroupModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRequisiteGroupModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnRequisiteGroupView', [
    'CnRequisiteGroupModelFactory',
    function( CnRequisiteGroupModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnRequisiteGroupModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRequisiteGroupAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRequisiteGroupListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRequisiteGroupViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnRequisiteGroupModelFactory', [
    'CnBaseModelFactory', 'CnRequisiteGroupAddFactory', 'CnRequisiteGroupListFactory', 'CnRequisiteGroupViewFactory',
    function( CnBaseModelFactory, CnRequisiteGroupAddFactory, CnRequisiteGroupListFactory, CnRequisiteGroupViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnRequisiteGroupAddFactory.instance( this );
        this.listModel = CnRequisiteGroupListFactory.instance( this );
        this.viewModel = CnRequisiteGroupViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
