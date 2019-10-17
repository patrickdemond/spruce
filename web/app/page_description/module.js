define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'page_description', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initDescriptionPage( module, 'page' );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageDescriptionList', [
    'CnPageDescriptionModelFactory',
    function( CnPageDescriptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageDescriptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageDescriptionView', [
    'CnPageDescriptionModelFactory',
    function( CnPageDescriptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageDescriptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageDescriptionListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageDescriptionViewFactory', [
    'CnBaseViewFactory', 'CnBaseDescriptionViewFactory',
    function( CnBaseViewFactory, CnBaseDescriptionViewFactory ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseDescriptionViewFactory.construct( this, 'page' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageDescriptionModelFactory', [
    'CnBaseModelFactory', 'CnPageDescriptionListFactory', 'CnPageDescriptionViewFactory',
    function( CnBaseModelFactory, CnPageDescriptionListFactory, CnPageDescriptionViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnPageDescriptionListFactory.instance( this );
        this.viewModel = CnPageDescriptionViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
