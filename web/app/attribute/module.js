cenozoApp.defineModule( { name: 'attribute', models: ['add', 'list', 'view'], create: module => {

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'attribute',
      plural: 'attributes',
      possessive: 'attribute\'s'
    },
    columnList: {
      name: {
        title: 'Name',
        column: 'attribute.name'
      },
      code: {
        title: 'Code'
      }
    },
    defaultOrder: {
      column: 'attribute.name',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    name: {
      title: 'Name',
      type: 'string'
    },
    code: {
      title: 'Code',
      type: 'string'
    },
    note: {
      title: 'Note',
      type: 'text'
    }
  } );

} } );
