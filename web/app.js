'use strict';

var cenozo = angular.module( 'cenozo' );

cenozo.controller( 'HeaderCtrl', [
  '$scope', 'CnBaseHeader',
  function( $scope, CnBaseHeader ) {
    // copy all properties from the base header
    CnBaseHeader.construct( $scope );
  }
] );

/* ######################################################################################################## */
cenozoApp.initQnairePartModule = function( module, type ) {
  module.addInput( '', 'previous_id', { isExcluded: true } );
  module.addInput( '', 'next_id', { isExcluded: true } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPrevious(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNext(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_id; }
  } );
};

/* ######################################################################################################## */
cenozoApp.initDescriptionModule = function( module, type ) {
  angular.extend( module, {
    identifier: {
      parent: {
        subject: type,
        column: type + '.id'
      }
    },
    name: {
      singular: 'description',
      plural: 'descriptions',
      possessive: 'description\'s'
    },
    columnList: {
      language: {
        column: 'language.code',
        title: 'Langauge'
      },
      value: {
        title: 'Value',
        align: 'left'
      }
    },
    defaultOrder: {
      column: 'language.code',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    language: {
      column: 'language.code',
      type: 'string',
      isConstant: true
    },
    value: {
      title: 'Value',
      type: 'text'
    },

    previous_description_id: { isExcluded: true },
    next_description_id: { isExcluded: true }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPreviousDescription(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_description_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNextDescription(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_description_id; }
  } );
};

/* ######################################################################################################## */
cenozo.factory( 'CnBaseQnairePartViewFactory', [
  '$state',
  function( $state  ) {
    return {
      construct: function( object, type ) {
        angular.extend( object, {
          viewPrevious: function() {
            $state.go( type + '.view', { identifier: this.record.previous_id }, { reload: true } );
          },
          viewNext: function() {
            $state.go( type + '.view', { identifier: this.record.next_id }, { reload: true } );
          }
        } );
      }
    };
  }
] );

/* ######################################################################################################## */
cenozo.factory( 'CnBaseDescriptionViewFactory', [
  '$state',
  function( $state  ) {
    return {
      construct: function( object, type ) {
        angular.extend( object, {
          viewPreviousDescription: function() {
            $state.go(
              type + '_description.view',
              { identifier: this.record.previous_description_id },
              { reload: true }
            );
          },
          viewNextDescription: function() {
            $state.go(
              type + '_description.view',
              { identifier: this.record.next_description_id },
              { reload: true }
            );
          }
        } );
      }
    };
  }
] );

/* ######################################################################################################## */
cenozo.directive( 'cnQnaireNavigator', [
  'CnHttpFactory', '$state', '$q',
  function( CnHttpFactory, $state, $q ) {
    return {
      templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_navigator.tpl.html' ),
      restrict: 'E',
      controller: function( $scope ) {
        angular.extend( $scope, {
          loading: true,
          subject: $state.current.name.split( '.' )[0],
          currentQnaire: null,
          currentModule: null,
          currentPage: null,
          currentQuestion: null,
          moduleList: [],
          pageList: [],
          questionList: [],
          viewModule: function( id ) {
            var languageId = null;
            var promiseList = [];
            if( 'module_description.view' == $state.current.name ) {
              promiseList.push(
                CnHttpFactory.instance( {
                  path: 'module_description/' + $state.params.identifier,
                  data: { select: { column: 'language_id' }, modifier: { limit: 1000 } }
                } ).get().then( function( response ) {
                  languageId = response.data.language_id;
                } )
              );
            }

            return $q.all( promiseList ).then( function() {
              return $state.go(
                null != languageId ? 'module_description.view' : 'module.view',
                { identifier: null != languageId ? 'module_id=' + id + ';language_id=' + languageId : id },
                { reload: true }
              );
            } );
          },
          viewPage: function( id ) {
            var languageId = null;
            var promiseList = [];
            if( 'page_description.view' == $state.current.name ) {
              promiseList.push(
                CnHttpFactory.instance( {
                  path: 'page_description/' + $state.params.identifier,
                  data: { select: { column: 'language_id' }, modifier: { limit: 1000 } }
                } ).get().then( function( response ) {
                  languageId = response.data.language_id;
                } )
              );
            }

            return $q.all( promiseList ).then( function() {
              return $state.go(
                null != languageId ? 'page_description.view' : 'page.' + ( 'page.render' == $state.current.name ? 'render' : 'view' ),
                { identifier: null != languageId ? 'page_id=' + id + ';language_id=' + languageId : id },
                { reload: true }
              );
            } );
          },
          viewQuestion: function( id ) {
            var languageId = null;
            var promiseList = [];
            if( 'question_description.view' == $state.current.name ) {
              promiseList.push(
                CnHttpFactory.instance( {
                  path: 'question_description/' + $state.params.identifier,
                  data: { select: { column: 'language_id' }, modifier: { limit: 1000 } }
                } ).get().then( function( response ) {
                  languageId = response.data.language_id;
                } )
              );
            }

            return $q.all( promiseList ).then( function() {
              return $state.go(
                null != languageId ? 'question_description.view' : 'question.view',
                { identifier: null != languageId ? 'question_id=' + id + ';language_id=' + languageId : id },
                { reload: true }
              );
            } );
          }
        } );

        // fill in the qnaire, module, page and question data
        var columnList = [
          { table: 'qnaire', column: 'id', alias: 'qnaire_id' },
          { table: 'qnaire', column: 'name', alias: 'qnaire_name' }
        ];

        var moduleDetails = false;
        var pageDetails = false;
        var questionDetails = false;

        if ( ['question', 'question_description'].includes( $scope.subject ) ) {
          moduleDetails = true;
          pageDetails = true;
          questionDetails = true;
        } else if( ['page', 'page_description'].includes( $scope.subject ) ) {
          moduleDetails = true;
          pageDetails = true;
        } else if( ['module', 'module_description'].includes( $scope.subject ) ) {
          moduleDetails = true;
        }

        // if we're looking at a module, page or question then get the module's details
        if( moduleDetails ) {
          columnList.push(
            { table: 'module', column: 'id', alias: 'module_id' },
            { table: 'module', column: 'rank', alias: 'module_rank' },
            { table: 'module', column: 'name', alias: 'module_name' }
          );
        };

        // if we're looking at a page or question then get the page's details
        if( pageDetails ) {
          columnList.push(
            { table: 'page', column: 'id', alias: 'page_id' },
            { table: 'page', column: 'rank', alias: 'page_rank' },
            { table: 'page', column: 'name', alias: 'page_name' }
          );
        }

        // if we're looking at a question then get the question's details
        if( questionDetails ) {
          columnList.push(
            { table: 'question', column: 'id', alias: 'question_id' },
            { table: 'question', column: 'rank', alias: 'question_rank' },
            { table: 'question', column: 'name', alias: 'question_name' }
          );
        }

        CnHttpFactory.instance( {
          path: $scope.subject + '/' + $state.params.identifier,
          data: { select: { column: columnList }, modifier: { limit: 1000 } }
        } ).get().then( function( response ) {
          $scope.currentQnaire = {
            id: response.data.qnaire_id ? response.data.qnaire_id : response.data.id,
            name: response.data.qnaire_name ? response.data.qnaire_name : response.data.name
          };

          if( moduleDetails ) {
            $scope.currentModule = {
              id: response.data.module_id,
              rank: response.data.module_rank,
              name: response.data.module_name
            };
          }

          if( pageDetails ) {
            $scope.currentPage = {
              id: response.data.page_id,
              rank: response.data.page_rank,
              name: response.data.page_name
            };
          }

          if( questionDetails ) {
            $scope.currentQuestion = {
              id: response.data.question_id,
              rank: response.data.question_rank,
              name: response.data.question_name
            };
          }

          // get the list of modules, pages and questions (depending on what we're looking at)
          var promiseList = [
            CnHttpFactory.instance( {
              path: [ 'qnaire', $scope.currentQnaire.id, 'module' ].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: 'rank', limit: 1000 }
              }
            } ).query().then( function( response ) {
              $scope.moduleList = response.data;
            } )
          ];

          if( $scope.currentModule ) promiseList.push(
            CnHttpFactory.instance( {
              path: [ 'module', $scope.currentModule.id, 'page' ].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: 'rank', limit: 1000 }
              }
            } ).query().then( function( response ) {
              $scope.pageList = response.data;
            } )
          );

          if( $scope.currentPage ) promiseList.push(
            CnHttpFactory.instance( {
              path: [ 'page', $scope.currentPage.id, 'question' ].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: 'rank', limit: 1000 }
              }
            } ).query().then( function( response ) {
              $scope.questionList = response.data;
            } )
          );

          $q.all( promiseList );
        } );
      }
    };
  }
] );

cenozo.service( 'CnTranslationHelper', [
  function() {
    return {
      translate: function( address, language ) {
        var addressParts = address.split('.');

        function get( array, index ) {
          if( angular.isUndefined( index ) ) index = 0;
          var part = addressParts[index];
          return angular.isUndefined( array[part] )
               ? 'ERROR'
               : angular.isDefined( array[part][language] )
               ? array[part][language]
               : angular.isDefined( array[part].en )
               ? array[part].en
               : get( array[part], index+1 );
        }

        return get( this.lookupData );
      },
      lookupData: {
        misc: {
          yes: { en: 'Yes', fr: 'Oui' },
          no: { en: 'No', fr: 'Non' },
          dkna: { en: 'Don\'t Know / No Answer', fr: 'Ne sait pas / pas de réponse' },
          refuse: { en: 'Refuse', fr: 'Refus' },
          begin: { en: 'Begin', fr: 'Commencer' },
          next: { en: 'Next', fr: 'Suivant' },
          previous: { en: 'Previous', fr: 'Précédent' },
          submit: { en: 'Submit', fr: 'Envoyer' }
        }
      }
    };
  }
] );
