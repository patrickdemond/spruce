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
    format: 'float',
    isExcluded: function( $state, model ) { return 'number' != model.viewModel.record.type ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    format: 'float',
    isExcluded: function( $state, model ) { return 'number' != model.viewModel.record.type ? true : 'add'; }
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'parent_name', { column: 'page.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionClone', [
    'CnQnairePartCloneFactory', 'CnHttpFactory',
    function( CnQnairePartCloneFactory, CnHttpFactory ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question' );
          $scope.model.onLoad();
        }
      };
    }
  ] );
} );
