// extend the framework's module
define( [ cenozoApp.module( 'address' ).getFileUrl( 'module.js' ) ], function() {
  'use strict';

  var module = cenozoApp.module( 'address' );

  // extend the list factory
  cenozo.providers.decorator( 'CnAddressModelFactory', [
    '$delegate', 'CnSession',
    function( $delegate, CnSession ) {
      $delegate.root.baseGetMetadata = $delegate.root.getMetadata;

      // get the address details based on a response token
      angular.extend( $delegate.root, {
        getServiceResourcePath: function( resource ) {
          var token = $delegate.root.getQueryParameter( 'token', true );
          return 'respondent' == $delegate.root.getSubjectFromState() && 'run' == $delegate.root.getActionFromState() ?
            'participant/token=' + token + '/address/type=primary' : $delegate.root.$$getServiceResourcePath( resource );
        },
        
        getMetadata: async function() {
          // the rank column requires additional data, so since we don't need it we'll just remove it
          await this.baseGetMetadata();
          delete this.metadata.columnList.rank;
        }
      } );

      return $delegate;
    }
  ] );

} );
