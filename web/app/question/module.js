define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module );

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'page',
        column: 'page.id'
      }
    },
    name: {
      singular: 'question',
      plural: 'questions',
      possessive: 'question\'s'
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
      },
      type: {
        title: 'Type'
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
      help: 'A special expression which restricts whether or not to show this question.'
    },
    type: {
      title: 'Type',
      type: 'enum'
    },
    minimum: {
      title: 'Minimum',
      type: 'string',
      format: 'float'
    },
    maximum: {
      title: 'Maximum',
      type: 'string',
      format: 'float'
    },
    note: {
      title: 'Note',
      type: 'text'
    },

    page_name: { column: 'page.name', isExcluded: true }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionAdd', [
    'CnQuestionModelFactory',
    function( CnQuestionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionList', [
    'CnQuestionModelFactory',
    function( CnQuestionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionView', [
    'CnQuestionModelFactory',
    function( CnQuestionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionViewFactory', [
    'CnBaseViewFactory', 'CnBaseQnairePartViewFactory',
    function( CnBaseViewFactory, CnBaseQnairePartViewFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseQnairePartViewFactory.construct( this, 'question' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionModelFactory', [
    'CnBaseModelFactory', 'CnQuestionAddFactory', 'CnQuestionListFactory', 'CnQuestionViewFactory',
    function( CnBaseModelFactory, CnQuestionAddFactory, CnQuestionListFactory, CnQuestionViewFactory ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQuestionAddFactory.instance( this );
        this.listModel = CnQuestionListFactory.instance( this );
        this.viewModel = CnQuestionViewFactory.instance( this, root );

        this.getBreadcrumbParentTitle = function() {
          return this.viewModel.record.page_name;
        };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
