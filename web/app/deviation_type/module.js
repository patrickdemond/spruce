define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'deviation_type', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'deviation type',
      plural: 'deviation types',
      possessive: 'deviation type\'s'
    },
    columnList: {
      type: {
        title: 'Type',
        column: 'deviation_type.type'
      },
      name: {
        title: 'Name',
        column: 'deviation_type.name'
      }
    },
    defaultOrder: {
      column: 'deviation_type.type',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    type: {
      title: 'Type',
      type: 'enum'
    },
    name: {
      title: 'Name',
      type: 'string'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviationTypeAdd', [
    'CnDeviationTypeModelFactory',
    function( CnDeviationTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviationTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviationTypeList', [
    'CnDeviationTypeModelFactory',
    function( CnDeviationTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviationTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnDeviationTypeView', [
    'CnDeviationTypeModelFactory',
    function( CnDeviationTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnDeviationTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviationTypeAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviationTypeListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviationTypeViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnDeviationTypeModelFactory', [
    'CnBaseModelFactory', 'CnDeviationTypeAddFactory', 'CnDeviationTypeListFactory', 'CnDeviationTypeViewFactory',
    function( CnBaseModelFactory, CnDeviationTypeAddFactory, CnDeviationTypeListFactory, CnDeviationTypeViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnDeviationTypeAddFactory.instance( this );
        this.listModel = CnDeviationTypeListFactory.instance( this );
        this.viewModel = CnDeviationTypeViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

}  );
