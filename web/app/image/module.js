cenozoApp.defineModule( { name: 'image', models: ['add', 'list', 'view'], create: module => {

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'image',
      plural: 'images',
      possessive: 'image\'s'
    },
    columnList: {
      name: {
        title: 'Name',
        column: 'image.name'
      },
      mime_type: {
        title: 'Mime Type'
      },
      size: {
        title: 'Size',
        type: 'size'
      },
      width: {
        title: 'Width',
        type: 'string'
      },
      height: {
        title: 'Height',
        type: 'string'
      }
    },
    defaultOrder: {
      column: 'image.name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      title: 'Name',
      type: 'string'
    },
    mime_type: {
      title: 'Mime Type',
      type: 'string',
      isExcluded: 'add',
      isConstant: true
    },
    size: {
      title: 'File Size',
      type: 'size',
      isExcluded: 'add',
      isConstant: true
    },
    width: {
      title: 'Width',
      type: 'string',
      isExcluded: 'add',
      isConstant: true
    },
    height: {
      title: 'Height',
      type: 'string',
      isExcluded: 'add',
      isConstant: true
    },
    data: {
      title: 'Image',
      type: 'base64_image'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnImageAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) { 
      var object = function( parentModel ) { 
        CnBaseAddFactory.construct( this, parentModel );
        this.configureFileInput( 'data' );
      };  
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }   
  ] );

} } );
