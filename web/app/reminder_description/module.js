cenozoApp.defineModule( { name: 'reminder_description', models: ['list', 'view'], create: module => {

  cenozoApp.initDescriptionModule( module, 'reminder' );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnReminderDescriptionViewFactory', [
    'CnBaseViewFactory', 'CnBaseDescriptionViewFactory',
    function( CnBaseViewFactory, CnBaseDescriptionViewFactory ) {
      var object = function( parentModel, root ) {
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseDescriptionViewFactory.construct( this, 'reminder' );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnReminderDescriptionModelFactory', [
    'CnBaseModelFactory', 'CnReminderDescriptionListFactory', 'CnReminderDescriptionViewFactory',
    function( CnBaseModelFactory, CnReminderDescriptionListFactory, CnReminderDescriptionViewFactory ) {
      var object = function( root ) {
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnReminderDescriptionListFactory.instance( this );
        this.viewModel = CnReminderDescriptionViewFactory.instance( this, root );
        this.getEditEnabled = function() { return !this.viewModel.record.readonly && this.$$getEditEnabled(); };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} } );
