define( [ 'question_option' ].reduce( function( list, name ) {
  return list.concat( cenozoApp.module( name ).getRequiredFiles() );
}, [] ), function() {
  'use strict';

  try { var module = cenozoApp.module( 'question', true ); } catch( err ) { console.warn( err ); return; }

  cenozoApp.initQnairePartModule( module, 'question' );

  module.identifier.parent = {
    subject: 'page',
    column: 'page.id'
  };

  // The column list is different when looking at a qnaire's list of questions
  angular.extend( module.columnList, {
    module_name: {
      column: 'module.name',
      title: 'Module',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    page_name: {
      column: 'page.name',
      title: 'Page',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    question_name: {
      column: 'question.name',
      title: 'Question',
      isIncluded: function( $state, model ) { return 'qnaire' == model.getSubjectFromState(); }
    },
    type: { title: 'Type' }
  } );

  module.columnList.rank.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.name.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.question_option_count.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };
  module.columnList.precondition.isIncluded = function( $state, model ) { return 'qnaire' != model.getSubjectFromState(); };

  module.addInput( '', 'type', { title: 'Type', type: 'enum' } );
  module.addInput( '', 'dkna_allowed', { title: 'Allow DKNA', type: 'boolean' } );
  module.addInput( '', 'refuse_allowed', { title: 'Allow Refuse', type: 'boolean' } );
  module.addInput( '', 'minimum', {
    title: 'Minimum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'maximum', {
    title: 'Maximum',
    type: 'string',
    isExcluded: function( $state, model ) { return !['date', 'number'].includes( model.viewModel.record.type ) ? true : 'add'; }
  } );
  module.addInput( '', 'default_answer', {
    title: 'Default Answer',
    type: 'string'
  } );
  module.addInput( '', 'note', { title: 'Note', type: 'text' } );
  module.addInput( '', 'parent_name', { column: 'page.name', isExcluded: true } );
  module.addInput( '', 'question_option_count', { isExcluded: true } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnQuestionClone', [
    'CnQnairePartCloneFactory', 'CnSession', '$state',
    function( CnQnairePartCloneFactory, CnSession, $state ) {
      return {
        templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_clone.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnQnairePartCloneFactory.instance( 'question' );
          
          $scope.model.onLoad().then( function() {
            CnSession.setBreadcrumbTrail( [ {
              title: 'Page', 
              go: function() { return $state.go( 'page.list' ); }
            }, {
              title: $scope.model.parentSourceName,
              go: function() { return $state.go( 'page.view', { identifier: $scope.model.sourceParentId } ); }
            }, {
              title: 'Questions'
            }, {
              title: $scope.model.sourceName,
              go: function() { return $state.go( 'question.view', { identifier: $scope.model.sourceId } ); }
            }, {
              title: 'move/copy'
            } ] );
          } );
        }
      };
    }
  ] );

  // extend the model factory
  cenozo.providers.decorator( 'CnQuestionListFactory', [
    '$delegate',
    function( $delegate ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel ) {
        // if we are looking at the list of questions in a qnaire then we must change the default column order
        var object = instance( parentModel );
        if( 'qnaire' == parentModel.getSubjectFromState() ) object.order.column = 'module.rank';
        return object;
      };
      return $delegate;
    }
  ] );

  // extend the model factory
  cenozo.providers.decorator( 'CnQuestionViewFactory', [
    '$delegate', 'CnHttpFactory', 'CnModalConfirmFactory', '$q',
    function( $delegate, CnHttpFactory, CnModalConfirmFactory, $q ) {
      var instance = $delegate.instance;
      $delegate.instance = function( parentModel, root ) {
        // if we are looking at the list of questions in a qnaire then we must change the default column order
        var object = instance( parentModel, root );
        angular.extend( object, {
          getChildList: function() {
            return object.$$getChildList().filter( child => 'list' == object.record.type || 'question_option' != child.subject.snake );
          },

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
                    'The following parts of the questionnaire refer to this question in their precondition and will ' +
                    'automatically be updated to refer to the question\'s new name:\n';
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
            } else if( angular.isDefined( data.type ) && 'list' != object.record.type && 0 < object.record.question_option_count ) {
              // warn if changing from a list question which has options
              promiseList.push( CnModalConfirmFactory.instance( {
                message: 'By changing this question\'s type to "' + object.record.type + '" ' + object.record.question_option_count +
                         ' question option' + ( 1 == object.record.question_option_count ? '' : 's' ) + ' will be deleted. ' +
                         'Are you sure you wish to proceed?'
              } ).show() );
            }

            return $q.all( promiseList ).then( function( response ) {
              if( angular.isDefined( data.name ) ) {
                if( false === response[0] ) {
                  // put the old value back
                  object.record.name = object.backupRecord.name;
                } else { 
                  return object.$$onPatch( data );
                }
              } else if( angular.isDefined( data.type ) && 'list' != object.record.type && 0 < object.record.question_option_count ) {
                if( 0 < response.length && !response[0] ) {
                  // put the old value back
                  object.record.type = object.backupRecord.type;
                } else {
                  return object.$$onPatch( data ).then( function() {
                    // update the question option list since we may have deleted them
                    if( 0 < response.length && angular.isDefined( object.questionOptionModel ) )
                      object.questionOptionModel.listModel.onList( true );
                  } );
                }
              } else {
                return object.$$onPatch( data );
              }

            } );
          }
        } );
        return object;
      };

      return $delegate;
    }
  ] );

  // extend the base model factory created by caling initQnairePartModule()
  cenozo.providers.decorator( 'CnQuestionModelFactory', [
    '$delegate',
    function( $delegate ) {
      function extendModelObject( object ) {
        angular.extend( object, {
          getAddEnabled: function() {
            // don't allow the add button while viewing the qnaire
            return 'qnaire' != object.getSubjectFromState() && object.$$getAddEnabled();
          },
          getDeleteEnabled: function() {
            // don't allow the add button while viewing the qnaire
            return !object.viewModel.record.readonly && 'qnaire' != object.getSubjectFromState() && object.$$getDeleteEnabled();
          }
        } );
        return object;
      }

      var instance = $delegate.instance;
      $delegate.root = extendModelObject( $delegate.root );
      $delegate.instance = function( parentModel, root ) { return extendModelObject( instance( root ) ); };

      return $delegate;
    }
  ] );
} );
