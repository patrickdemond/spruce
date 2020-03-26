define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire_description', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initDescriptionModule( module, 'qnaire' );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireDescriptionList', [
    'CnQnaireDescriptionModelFactory',
    function( CnQnaireDescriptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireDescriptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireDescriptionView', [
    'CnQnaireDescriptionModelFactory',
    function( CnQnaireDescriptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireDescriptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireDescriptionListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireDescriptionViewFactory', [
    'CnBaseViewFactory', 'CnBaseDescriptionViewFactory',
    function( CnBaseViewFactory, CnBaseDescriptionViewFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseDescriptionViewFactory.construct( this, 'qnaire' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireDescriptionModelFactory', [
    'CnBaseModelFactory', 'CnQnaireDescriptionListFactory', 'CnQnaireDescriptionViewFactory',
    function( CnBaseModelFactory, CnQnaireDescriptionListFactory, CnQnaireDescriptionViewFactory ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnQnaireDescriptionListFactory.instance( this );
        this.viewModel = CnQnaireDescriptionViewFactory.instance( this, root );
        this.getEditEnabled = function() { return !this.viewModel.record.readonly && this.$$getEditEnabled(); };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
