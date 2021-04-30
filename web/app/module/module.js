define( [ 'page' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'module', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'module' );

  module.identifier.parent = {
    subject: 'qnaire',
    column: 'qnaire.name'
  };

  module.addInput( '', 'average_time', { title: 'Average Time (seconds)', type: 'string', isConstant: true, isExcluded: 'add' } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'first_page_id', { isExcluded: true } );
  module.addInput( '', 'parent_name', { column: 'qnaire.name', isExcluded: true } );
  cenozo.insertPropertyAfter( module.columnList, 'page_count', 'average_time', {
    title: 'Average Time',
    type: 'seconds'
  } );

  module.addExtraOperation( 'view', {
    title: 'Preview',
    isDisabled: function( $state, model ) { return !model.viewModel.record.first_page_id; },
    operation: async function( $state, model ) {
      await $state.go(
        'page.render',
        { identifier: model.viewModel.record.first_page_id },
        { reload: true }
      );
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnModuleClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: async function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'module' );
          
          await $scope.model.onLoad();

          CnSession.setBreadcrumbTrail( [ {
            title: 'Questionnaire', 
            go: async function() { await $state.go( 'qnaire.list' ); }
          }, {
            title: $scope.model.parentSourceName,
            go: async function() { await $state.go( 'qnaire.view', { identifier: $scope.model.sourceParentId } ); }
          }, {
            title: 'Modules'
          }, {
            title: $scope.model.sourceName,
            go: async function() { await $state.go( 'module.view', { identifier: $scope.model.sourceId } ); }
          }, {
            title: 'move/copy'
          } ] );
        }
      };
    }
  ] );

  // extend the view factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnModuleViewFactory', [
    '$delegate', '$filter',
    function( $delegate, $filter ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        var object = instance( parentModel, root );

        // see if the form has a record in the data-entry module
        angular.extend( object, {
          onView: async function( force ) {
            await this.$$onView( force );
            this.record.average_time = $filter( 'cnSeconds' )( Math.round( this.record.average_time ) );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );

} );
