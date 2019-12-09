define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'question_option', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question_option' );

  module.identifier.parent = {
    subject: 'question',
    column: 'question.id'
  };

  angular.extend( module.columnList, {
    exclusive: { title: 'Exclusive', type: 'boolean' },
    extra: { title: 'Extra', type: 'string' },
    multiple_answers: { title: 'Multiple Answers', type: 'boolean' }
  } );

  module.addInput( '', 'exclusive', { title: 'Exclusive', type: 'boolean' } );
  module.addInput( '', 'extra', { title: 'Extra', type: 'enum' } );
  module.addInput( '', 'multiple_answers', { title: 'Multiple Answers', type: 'boolean' } );
} );
