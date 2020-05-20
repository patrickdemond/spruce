define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire_consent_type', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {},
    name: {
      singular: 'consent trigger',
      plural: 'consent triggers',
      possessive: 'consent trigger\'s'
    },
    columnList: {
      consent_type: {
        title: 'Consent Type',
        column: 'consent_type.name'
      },
      question: {
        title: 'Question',
        column: 'question.name'
      },
      answer_value: {
        title: 'Required Answer'
      },
      accept: {
        title: 'Consent Accept',
        type: 'boolean'
      }
    },
    defaultOrder: {
      column: 'question.rank',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    consent_type_id: {
      title: 'Consent Type',
      type: 'enum'
    },
    question_id: {
      title: 'Question',
      type: 'lookup-typeahead',
      typeahead: {
        table: 'question',
        select: 'CONCAT( question.name, " (", question.type, ")" )',
        where: 'question.name'
      }
    },
    answer_value: {
      title: 'Required Answer',
      type: 'string'
    },
    accept: {
      title: 'Consent Accept',
      type: 'boolean'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeAdd', [
    'CnQnaireConsentTypeModelFactory',
    function( CnQnaireConsentTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeList', [
    'CnQnaireConsentTypeModelFactory',
    function( CnQnaireConsentTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireConsentTypeView', [
    'CnQnaireConsentTypeModelFactory',
    function( CnQnaireConsentTypeModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireConsentTypeModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireConsentTypeModelFactory', [
    'CnBaseModelFactory', 'CnQnaireConsentTypeAddFactory', 'CnQnaireConsentTypeListFactory', 'CnQnaireConsentTypeViewFactory',
    'CnHttpFactory',
    function( CnBaseModelFactory, CnQnaireConsentTypeAddFactory, CnQnaireConsentTypeListFactory, CnQnaireConsentTypeViewFactory,
              CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQnaireConsentTypeAddFactory.instance( this );
        this.listModel = CnQnaireConsentTypeListFactory.instance( this );
        this.viewModel = CnQnaireConsentTypeViewFactory.instance( this, root );

        // extend getMetadata
        this.getMetadata = function() {
          return this.$$getMetadata().then( function() {
            return CnHttpFactory.instance( {
              path: 'consent_type',
              data: {
                select: { column: [ 'id', 'name' ] },
                modifier: { order: 'name' }
              }
            } ).query().then( function( response ) {
              self.metadata.columnList.consent_type_id.enumList = [];
              response.data.forEach( function( item ) {
                self.metadata.columnList.consent_type_id.enumList.push( { value: item.id, name: item.name } );
              } );
            } );
          } )
        };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );