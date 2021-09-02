define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire_consent_type_confirm', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.name'
      }
    },
    name: {
      singular: 'consent confirm',
      plural: 'consent confirms',
      possessive: 'consent confirm\'s'
    },
    columnList: {
      consent_type: {
        title: 'Consent Type',
        column: 'consent_type.name'
      }
    },
    defaultOrder: {
      column: 'consent_type.name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    consent_type_id: {
      title: 'Consent Type',
      type: 'enum'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeConfirmAdd', [
    'CnQnaireConsentTypeConfirmModelFactory',
    function( CnQnaireConsentTypeConfirmModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeConfirmModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeConfirmList', [
    'CnQnaireConsentTypeConfirmModelFactory',
    function( CnQnaireConsentTypeConfirmModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeConfirmModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeConfirmView', [
    'CnQnaireConsentTypeConfirmModelFactory',
    function( CnQnaireConsentTypeConfirmModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeConfirmModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeConfirmAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeConfirmListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeConfirmViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeConfirmModelFactory', [
    'CnBaseModelFactory',
    'CnQnaireConsentTypeConfirmAddFactory', 'CnQnaireConsentTypeConfirmListFactory', 'CnQnaireConsentTypeConfirmViewFactory',
    'CnHttpFactory',
    function( CnBaseModelFactory,
              CnQnaireConsentTypeConfirmAddFactory, CnQnaireConsentTypeConfirmListFactory, CnQnaireConsentTypeConfirmViewFactory,
              CnHttpFactory ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQnaireConsentTypeConfirmAddFactory.instance( this );
        this.listModel = CnQnaireConsentTypeConfirmListFactory.instance( this );
        this.viewModel = CnQnaireConsentTypeConfirmViewFactory.instance( this, root );

        // extend getMetadata
        this.getMetadata = async function() {
          await this.$$getMetadata();
          
          var response = await CnHttpFactory.instance( {
            path: 'consent_type',
            data: {
              select: { column: [ 'id', 'name' ] },
              modifier: { order: 'name', limit: 1000 }
            }
          } ).query();

          this.metadata.columnList.consent_type_id.enumList = [];
          var self = this;
          response.data.forEach( function( item ) {
            self.metadata.columnList.consent_type_id.enumList.push( { value: item.id, name: item.name } );
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
