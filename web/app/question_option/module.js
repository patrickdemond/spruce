define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question_option', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module );

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'question',
        column: 'question.id'
      }
    },
    name: {
      singular: 'question option',
      plural: 'question options',
      possessive: 'question option\'s'
    },
    columnList: {
      rank: {
        title: 'Rank',
        type: 'rank'
      },
      precondition: {
        title: 'Precondition'
      },
      name: {
        title: 'Name'
      },
      exclusive: {
        title: 'Exclusive',
        type: 'boolean'
      },
      extra: {
        title: 'Extra',
        type: 'string'
      },
      multiple_answers: {
        title: 'Multiple Answers',
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
    name: {
      title: 'Name',
      type: 'string'
    },
    precondition: {
      title: 'Precondition',
      type: 'text',
      help: 'A special expression which restricts whether or not to show this question option.'
    },
    exclusive: {
      title: 'Exclusive',
      type: 'boolean'
    },
    extra: {
      title: 'Extra',
      type: 'enum'
    },
    multiple_answers: {
      title: 'Multiple Answers',
      type: 'boolean'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionAdd', [
    'CnQuestionOptionModelFactory',
    function( CnQuestionOptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionOptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionList', [
    'CnQuestionOptionModelFactory',
    function( CnQuestionOptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionOptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionView', [
    'CnQuestionOptionModelFactory',
    function( CnQuestionOptionModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQuestionOptionModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionOptionAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionOptionListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionOptionViewFactory', [
    'CnBaseViewFactory', 'CnBaseQnairePartViewFactory',
    function( CnBaseViewFactory, CnBaseQnairePartViewFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseQnairePartViewFactory.construct( this, 'question_option' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionOptionModelFactory', [
    'CnBaseModelFactory', 'CnQuestionOptionAddFactory', 'CnQuestionOptionListFactory', 'CnQuestionOptionViewFactory',
    function( CnBaseModelFactory, CnQuestionOptionAddFactory, CnQuestionOptionListFactory, CnQuestionOptionViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQuestionOptionAddFactory.instance( this );
        this.listModel = CnQuestionOptionListFactory.instance( this );
        this.viewModel = CnQuestionOptionViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
