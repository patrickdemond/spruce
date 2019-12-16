define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'module', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'module' );

  module.identifier.parent = {
    subject: 'qnaire',
    column: 'qnaire.name'
  };

  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'first_page_id', { isExcluded: true } );
  module.addInput( '', 'parent_name', { column: 'qnaire.name', isExcluded: true } );

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
  cenozo.providers.directive( 'cnModuleClone', [
    'CnQnairePartCloneFactory', 'CnHttpFactory',
    function( CnQnairePartCloneFactory, CnHttpFactory ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'module' );
          $scope.model.onLoad();
        }
      };
    }
  ] );
} );
