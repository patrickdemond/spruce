define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question', true ); } catch( err ) { console.warn( err ); return; }
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
      },
      description: {
        title: 'Description',
        align: 'left'
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
    precondition: {
      title: 'Precondition',
      type: 'string',
      help: 'A special expression which restricts whether or not to show this question.'
    },
    name: {
      title: 'Name',
      type: 'string'
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
    description: {
      title: 'Description',
      type: 'text'
    },
    note: {
      title: 'Note',
      type: 'text'
    },

    previous_question_id: { exclude: true },
    next_question_id: { exclude: true }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPreviousQuestion(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_question_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNextQuestion(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_question_id; }
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
    'CnBaseViewFactory', 'CnHttpFactory', '$state',
    function( CnBaseViewFactory, CnHttpFactory, $state ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );

        angular.extend( this, {
          viewPreviousQuestion: function() {
            $state.go(
              'question.view',
              { identifier: this.record.previous_question_id },
              { reload: true }
            );
          },
          viewNextQuestion: function() {
            $state.go(
              'question.view',
              { identifier: this.record.next_question_id },
              { reload: true }
            );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnQuestionModelFactory', [
    'CnBaseModelFactory', 'CnQuestionAddFactory', 'CnQuestionListFactory', 'CnQuestionViewFactory',
    function( CnBaseModelFactory, CnQuestionAddFactory, CnQuestionListFactory, CnQuestionViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnQuestionAddFactory.instance( this );
        this.listModel = CnQuestionListFactory.instance( this );
        this.viewModel = CnQuestionViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
