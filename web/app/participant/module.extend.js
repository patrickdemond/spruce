// extend the framework's module
define( [ cenozoApp.module( 'participant' ).getFileUrl( 'module.js' ) ], function() {
  'use strict';

  var module = cenozoApp.module( 'participant' );

  // extend the list factory
  cenozo.providers.decorator( 'CnParticipantModelFactory', [
    '$delegate', 'CnSession',
    function( $delegate, CnSession ) {
      // get the participant details based on a response token
      angular.extend( $delegate.root, {
        getServiceResourcePath: function( resource ) {
          return 'respondent' == $delegate.root.getSubjectFromState() && 'run' == $delegate.root.getActionFromState() ?
            'participant/uid=A123881' : $delegate.root.$$getServiceResourcePath( resource );
        },

        getServiceData: function( type, columnRestrictLists ) {
          return
            'view' == type && 'respondent' == $delegate.root.getSubjectFromState() && 'run' == $delegate.root.getActionFromState() ?
              { select: { column: ['honorific', 'first_name', 'other_name', 'last_name', 'date_of_birth', 'sex', 'email' ] } } :
              $delegate.root.$$getServiceData( type, columnRestrictLists );
        },

        getMetadata: async function() { await this.$$getMetadata(); }
      } );

      $delegate.root.viewModel.baseOnView = $delegate.root.viewModel.onView;
      $delegate.root.viewModel.onView = async function( force ) {
        await this.baseOnView( force );

        // force exclusion to be Yes (otherwise we can't edit the participant's details)
        this.record.exclusion = 'Yes';
      }

      return $delegate;
    }
  ] );

} );
