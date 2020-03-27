define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'qnaire', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {},
    name: {
      singular: 'questionnaire',
      plural: 'questionnaires',
      possessive: 'questionnaire\'s'
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
      readonly: {
        title: 'Read-Only',
        type: 'boolean'
      },
      repeated: {
        title: 'Repeated',
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
      type: 'string',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    base_language_id: {
      title: 'Base Language',
      type: 'enum',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
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
      type: 'boolean',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    repeated: {
      title: 'Repeated',
      type: 'boolean'
    },
    description: {
      title: 'Description',
      type: 'text',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
    },
    note: {
      title: 'Note',
      type: 'text',
      isConstant: function( $state, model ) { return model.viewModel.record.readonly; }
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

  module.addExtraOperation( 'view', {
    title: 'Clone',
    operation: function( $state, model ) {
      $state.go( 'qnaire.clone', { identifier: model.viewModel.record.getIdentifier() } );
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
  cenozo.providers.directive( 'cnQnaireClone', [
    'CnQnaireCloneFactory', 'CnSession', '$state',
    function( CnQnaireCloneFactory, CnSession, $state ) {
      return {
        templateUrl: module.getFileUrl( 'clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnaireCloneFactory.instance();
          
          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Questionnaires', 
              go: function() { return $state.go( 'qnaire.list' ); }
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'qnaire.view', { identifier: $scope.model.parentQnaireId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
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
  cenozo.providers.factory( 'CnQnaireCloneFactory', [
    'CnHttpFactory', 'CnModalMessageFactory', '$state',
    function( CnHttpFactory, CnModalMessageFactory, $state ) {
      var object = function() {
        var self = this;
        angular.extend( this, {
          parentQnaireId: $state.params.identifier,
          sourceName: null,
          working: false,
          name: null,
          nameConflict: false,

          onLoad: function() {
            // reset data
            this.name = null;
            this.nameConflict = false;
            return CnHttpFactory.instance( {
              path: 'qnaire/' + this.parentQnaireId,
              data: { select: { column: 'name' } }
            } ).get().then( function( response ) {
              self.sourceName = response.data.name;
            } );
          },
          isComplete: function() { return !this.working && !this.nameConflict && null != this.name; },
          cancel: function() { $state.go( 'qnaire.view', { identifier: this.parentQnaireId } ); },

          save: function() {
            this.working = true;

            return CnHttpFactory.instance( {
              path: 'qnaire?clone=' + this.parentQnaireId,
              data: { name: this.name },
              onError: function( response ) {
                if( 409 == response.status ) self.nameConflict = true;
                else CnModalMessageFactory.httpError( response );
              }
            } ).post().then( function( response ) {
              $state.go( 'qnaire.view', { identifier: response.data } );
            } ).finally( function() {
              self.working = false;
            } );
          }
        } );
      }
      return { instance: function() { return new object(); } };
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
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );

        this.deferred.promise.then( function() {
          if( angular.isDefined( self.moduleModel ) ) {
            self.moduleModel.getAddEnabled = function() {
              return !self.record.readonly && self.moduleModel.$$getAddEnabled();
            }
            self.moduleModel.getDeleteEnabled = function() {
              return !self.record.readonly && self.moduleModel.$$getDeleteEnabled();
            }
          }
        } );

        this.deferred.promise.then( function() {
          if( angular.isDefined( self.attributeModel ) ) {
            self.attributeModel.getAddEnabled = function() {
              return !self.record.readonly && self.attributeModel.$$getAddEnabled();
            }
            self.attributeModel.getDeleteEnabled = function() {
              return !self.record.readonly && self.attributeModel.$$getDeleteEnabled();
            }
          }
        } );
      }
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
