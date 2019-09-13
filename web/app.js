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
cenozo.directive( 'cnQnaireNavigator', [
  'CnHttpFactory', '$state', '$q',
  function( CnHttpFactory, $state, $q ) {
    return {
      templateUrl: cenozoApp.getFileUrl( 'linden', 'qnaire_navigator.tpl.html' ),
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
            $state.go(
              'module.view',
              { identifier: id },
              { reload: true }
            );
          },
          viewPage: function( id ) {
            var action = 'page.render' == $state.current.name ? 'render' : 'view';
            $state.go(
              'page.' + action,
              { identifier: id },
              { reload: true }
            );
          },
          viewQuestion: function( id ) {
            $state.go(
              'question.view',
              { identifier: id },
              { reload: true }
            );
          }
        } );

        // fill in the qnaire, module, page and question data
        var columnList = [
          { table: 'qnaire', column: 'id', alias: 'qnaire_id' },
          { table: 'qnaire', column: 'name', alias: 'qnaire_name' }
        ];

        // if we're looking at a module, page or question then get the module's details
        if( ['module', 'page', 'question'].includes( $scope.subject ) ) {
          columnList.push(
            { table: 'module', column: 'id', alias: 'module_id' },
            { table: 'module', column: 'rank', alias: 'module_rank' },
            { table: 'module', column: 'name', alias: 'module_name' }
          );
        };

        // if we're looking at a page or question then get the page's details
        if( ['page', 'question'].includes( $scope.subject ) ) {
          columnList.push(
            { table: 'page', column: 'id', alias: 'page_id' },
            { table: 'page', column: 'rank', alias: 'page_rank' },
            { table: 'page', column: 'name', alias: 'page_name' }
          );
        }
        
        // if we're looking at a question then get the question's details
        if ( 'question' == $scope.subject ) {
          columnList.push(
            { table: 'question', column: 'id', alias: 'question_id' },
            { table: 'question', column: 'rank', alias: 'question_rank' },
            { table: 'question', column: 'name', alias: 'question_name' }
          );
        }

        CnHttpFactory.instance( {
          path: $scope.subject + '/' + $state.params.identifier,
          data: { select: { column: columnList } }
        } ).get().then( function( response ) {
          $scope.currentQnaire = {
            id: response.data.qnaire_id ? response.data.qnaire_id : response.data.id,
            name: response.data.qnaire_name ? response.data.qnaire_name : response.data.name
          };

          if( ['module', 'page', 'question'].includes( $scope.subject ) ) {
            $scope.currentModule = {
              id: response.data.module_id,
              rank: response.data.module_rank,
              name: response.data.module_name
            };
          }

          if( ['page', 'question'].includes( $scope.subject ) ) {
            $scope.currentPage = {
              id: response.data.page_id,
              rank: response.data.page_rank,
              name: response.data.page_name
            };
          }

          if ( 'question' == $scope.subject ) {
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
                modifier: { order: 'rank' }
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
                modifier: { order: 'rank' }
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
                modifier: { order: 'rank' }
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
