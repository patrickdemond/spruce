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
  module.addInput( '', 'minimum', { title: 'Minimum', type: 'string', format: 'float' } );
  module.addInput( '', 'maximum', { title: 'Maximum', type: 'string', format: 'float' } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'page_name', { column: 'page.name', isExcluded: true } );

  // extend the base model factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnQuestionModelFactory', [
    '$delegate',
    function( $delegate ) {
      function extendModelObject( object ) {
        object.getBreadcrumbParentTitle = function() {
          return this.viewModel.record.page_name;
        };
        return object;
      }

      var instance = $delegate.instance;
      $delegate.root = extendModelObject( $delegate.root );
      $delegate.instance = function( parentModel, root ) { return extendModelObject( instance( root ) ); };

      return $delegate;
    }
  ] );
} );
