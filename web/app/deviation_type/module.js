cenozoApp.defineModule( { name: 'deviation_type', models: ['add', 'list', 'view'], create: module => {

  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'deviation type',
      plural: 'deviation types',
      possessive: 'deviation type\'s'
    },
    columnList: {
      type: {
        title: 'Type',
        column: 'deviation_type.type'
      },
      name: {
        title: 'Name',
        column: 'deviation_type.name'
      }
    },
    defaultOrder: {
      column: 'deviation_type.type',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    type: {
      title: 'Type',
      type: 'enum'
    },
    name: {
      title: 'Name',
      type: 'string'
    }
  } );

} } );
