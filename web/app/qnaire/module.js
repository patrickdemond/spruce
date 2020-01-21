define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {},
    name: {
      singular: 'qnaire',
      plural: 'qnaires',
      possessive: 'qnaire\'s'
    },
    columnList: {
      name: {
        title: 'Name'
      },
      base_language_id: {
        title: 'Base Language',
        column: 'base_language.name'
      },
      debug: {
        title: 'Debug Mode',
        type: 'boolean'
      },
      module_count: {
        title: 'Modules'
      },
      response_count: {
        title: 'Responses'
      },
      description: {
        title: 'Description',
        align: 'left'
      }
    },
    defaultOrder: {
      column: 'name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      title: 'Name',
      type: 'string'
    },
    base_language_id: {
      title: 'Base Language',
      type: 'enum'
    },
    average_time: {
      title: 'Average Time',
      type: 'string',
      format: 'seconds',
      isConstant: true,
      isExcluded: 'add'
    },
    debug: {
      title: 'Debug Mode',
      type: 'boolean'
    },
    description: {
      title: 'Description',
      type: 'text'
    },
    note: {
      title: 'Note',
      type: 'text'
    },
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

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireAdd', [
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireList', [
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQnaireView', [
    'CnQnaireModelFactory',
    function( CnQnaireModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQnaireModelFactory', [
    'CnBaseModelFactory', 'CnQnaireAddFactory', 'CnQnaireListFactory', 'CnQnaireViewFactory', 'CnHttpFactory',
    function( CnBaseModelFactory, CnQnaireAddFactory, CnQnaireListFactory, CnQnaireViewFactory, CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQnaireAddFactory.instance( this );
        this.listModel = CnQnaireListFactory.instance( this );
        this.viewModel = CnQnaireViewFactory.instance( this, root );

        this.getBreadcrumbTitle = function() { return this.viewModel.record.name; };

        // extend getMetadata
        this.getMetadata = function() {
          return this.$$getMetadata().then( function() {
            return CnHttpFactory.instance( {
              path: 'language',
              data: {
                select: { column: [ 'id', 'name', 'code' ] },
                modifier: {
                  where: { column: 'active', operator: '=', value: true },
                  order: 'name'
                }
              }
            } ).query().then( function success( response ) {
              self.metadata.columnList.base_language_id.enumList = [];
              response.data.forEach( function( item ) {
                self.metadata.columnList.base_language_id.enumList.push( {
                  value: item.id,
                  name: item.name,
                  code: item.code // code is needed by the withdraw action
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
