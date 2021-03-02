define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question_option', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question_option' );

  module.identifier.parent = {
    subject: 'question',
    column: 'question.id'
  };

  angular.extend( module.columnList, {
    exclusive: { title: 'Exclusive', type: 'boolean' },
    extra: { title: 'Extra', type: 'string' },
    multiple_answers: { title: 'Multiple Answers', type: 'boolean' }
  } );

  module.addInput( '', 'exclusive', { title: 'Exclusive', type: 'boolean' } );
  module.addInput( '', 'extra', { title: 'Extra', type: 'enum' } );
  module.addInput( '', 'multiple_answers', {
    title: 'Multiple Answers',
    type: 'boolean',
    isExcluded: function( $state, model ) { return !model.viewModel.record.extra ? true : 'add'; }
  } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.extra ) ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.extra ) ? true : 'add'; }
  } );
  module.addInput( '', 'parent_name', { column: 'question.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question_option' );

          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page',
              go: function() { return $state.go( 'question.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Question Options'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question_option.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );

  // extend the view factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnQuestionOptionViewFactory', [
    '$delegate', '$filter',
    function( $delegate, $filter ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        var object = instance( parentModel, root );

        // when changing the extra value the multiple-answers, min and max columns are automatically updated by the DB
        angular.extend( object, {
          onPatch: function( data ) {
            var self = this;
            return this.$$onPatch( data ).then( function() {
              if( angular.isDefined( data.extra ) ) {
                if( !data.extra ) self.record.multiple_answers = false;
                if( !['date', 'number'].includes( data.extra ) ) {
                  self.record.minimum = '';
                  self.record.maximum = '';
                }
              }
            } );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );
} );
