define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question' );

  module.identifier.parent = {
    subject: 'page',
    column: 'page.id'
  };

  module.columnList.type = { title: 'Type' };
  module.addInput( '', 'type', { title: 'Type', type: 'enum' } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'default_answer', {
    title: 'Default Answer',
    type: 'string'
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'parent_name', { column: 'page.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question' );
          
          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page', 
              go: function() { return $state.go( 'page.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'page.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Questions'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );
} );
