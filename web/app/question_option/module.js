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
  module.addInput( '', 'multiple_answers', {
    title: 'Multiple Answers',
    type: 'boolean',
    isExcluded: function( $state, model ) { return !model.viewModel.record.extra ? true : 'add'; }
  } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.extra ) ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.extra ) ? true : 'add'; }
  } );
  module.addInput( '', 'parent_name', { column: 'question.name', isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionOptionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question_option' );

          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page',
              go: function() { return $state.go( 'question.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Question Options'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question_option.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );

  // extend the view factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnQuestionOptionViewFactory', [
    '$delegate', 'CnHttpFactory', 'CnModalConfirmFactory', '$q',
    function( $delegate, CnHttpFactory, CnModalConfirmFactory, $q ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        var object = instance( parentModel, root );

        // when changing the extra value the multiple-answers, min and max columns are automatically updated by the DB
        angular.extend( object, {
          onPatch: function( data ) {
            var promiseList = [];

            // warn if changing name will cause automatic change to preconditions
            if( angular.isDefined( data.name ) ) {
              promiseList.push( CnHttpFactory.instance( {
                path: object.parentModel.getServiceResourcePath(),
                data: { select: { column: [
                  'module_precondition_dependencies',
                  'page_precondition_dependencies',
                  'question_precondition_dependencies',
                  'question_option_precondition_dependencies'
                ] } }
              } ).query().then( function( response ) {
                if( null != response.data.module_precondition_dependencies ||
                    null != response.data.page_precondition_dependencies ||
                    null != response.data.question_precondition_dependencies ||
                    null != response.data.question_option_precondition_dependencies ) { 
                  var message =
                    'The following parts of the questionnaire refer to this question-option in their precondition and will ' +
                    'automatically be updated to refer to the question option\'s new name:\n';
                  if( null != response.data.module_precondition_dependencies )
                    message += '\nModule(s): ' + response.data.module_precondition_dependencies
                  if( null != response.data.page_precondition_dependencies )
                    message += '\nPage(s): ' + response.data.page_precondition_dependencies
                  if( null != response.data.question_precondition_dependencies )
                    message += '\nQuestion(s): ' + response.data.question_precondition_dependencies
                  if( null != response.data.question_option_precondition_dependencies )
                    message += '\nQuestion Option(s): ' + response.data.question_option_precondition_dependencies
                  message += '\n\nAre you sure you wish to proceed?';
                  return CnModalConfirmFactory.instance( { message: message } ).show();
                }
              } ) );
            }

            return $q.all( promiseList ).then( function( response ) {
              if( angular.isDefined( data.name ) ) {
                if( false === response[0] ) {
                  // put the old value back
                  object.record.name = object.backupRecord.name;
                } else {
                  return object.$$onPatch( data );
                }
              } else {
                return object.$$onPatch( data ).then( function() {
                  if( angular.isDefined( data.extra ) ) {
                    if( !data.extra ) object.record.multiple_answers = false;
                    if( !['date', 'number'].includes( data.extra ) ) {
                      object.record.minimum = '';
                      object.record.maximum = '';
                    }
                  }
                } );
              }
            } );
          }
        } );

        return object;
      };

      return $delegate;
    }
  ] );
} );
