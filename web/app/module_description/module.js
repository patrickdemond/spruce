cenozoApp.defineModule( { name: 'module_description', models: ['list', 'view'], create: module => {

  cenozoApp.initDescriptionModule( module, 'module' );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleDescriptionViewFactory', [
    'CnBaseViewFactory', 'CnBaseDescriptionViewFactory',
    function( CnBaseViewFactory, CnBaseDescriptionViewFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseDescriptionViewFactory.construct( this, 'module' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnModuleDescriptionModelFactory', [
    'CnBaseModelFactory', 'CnModuleDescriptionListFactory', 'CnModuleDescriptionViewFactory',
    function( CnBaseModelFactory, CnModuleDescriptionListFactory, CnModuleDescriptionViewFactory ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnModuleDescriptionListFactory.instance( this );
        this.viewModel = CnModuleDescriptionViewFactory.instance( this, root );
        this.getEditEnabled = function() { return !this.viewModel.record.readonly && this.$$getEditEnabled(); };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} } );
