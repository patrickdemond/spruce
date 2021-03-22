define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'response_attribute', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'response',
        column: 'response.id'
      }
    },
    name: {
      singular: 'attribute',
      plural: 'attributes',
      possessive: 'attribute\'s'
    },
    columnList: {
      name: {
        column: 'attribute.name',
        title: 'Name'
      },
      value: {
        title: 'Value'
      }
    },
    defaultOrder: {
      column: 'attribute.name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      column: 'attribute.name',
      title: 'Name',
      type: 'string',
      isConstant: true
    },
    value: {
      title: 'Value',
      type: 'string'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseAttributeList', [
    'CnResponseAttributeModelFactory',
    function( CnResponseAttributeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseAttributeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseAttributeView', [
    'CnResponseAttributeModelFactory',
    function( CnResponseAttributeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseAttributeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseAttributeListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseAttributeViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseAttributeModelFactory', [
    'CnBaseModelFactory', 'CnResponseAttributeListFactory', 'CnResponseAttributeViewFactory',
    function( CnBaseModelFactory, CnResponseAttributeListFactory, CnResponseAttributeViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnResponseAttributeListFactory.instance( this );
        this.viewModel = CnResponseAttributeViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
