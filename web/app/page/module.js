define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'page', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'module',
        column: 'module.id'
      }
    },
    name: {
      singular: 'page',
      plural: 'pages',
      possessive: 'page\'s'
    },
    columnList: {
      rank: {
        title: 'Rank',
        type: 'rank'
      },
      name: {
        title: 'Name'
      },
      description: {
        title: 'Description',
        align: 'left'
      }
    },
    defaultOrder: {
      column: 'rank',
      reverse: false
    }
  } );

  module.addInputGroup( '', {
    rank: {
      title: 'Rank',
      type: 'rank'
    },
    name: {
      title: 'Name',
      type: 'string'
    },
    description: {
      title: 'Description',
      type: 'text'
    },
    note: {
      title: 'Note',
      type: 'text'
    },

    module_id: { exclude: true },
    previous_page_id: { exclude: true },
    next_page_id: { exclude: true }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPreviousPage(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.previous_page_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNextPage(); },
    isDisabled: function( $state, model ) { return null == model.viewModel.record.next_page_id; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Preview',
    operation: function( $state, model ) {
      $state.go(
        'page.render',
        { identifier: model.viewModel.record.getIdentifier() },
        { reload: true }
      );
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageAdd', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageList', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageRender', [
    'CnPageModelFactory', '$q', '$document', '$transitions',
    function( CnPageModelFactory, $q, $document, $transitions ) {
      return {
        templateUrl: module.getFileUrl( 'render.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          $scope.isComplete = false;
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;

          // bind keypresses (first unbind to prevent duplicates)
          $document.unbind( 'keydown.render' );
          $document.bind( 'keydown.render', function( event ) {
            var renderModel = $scope.model.renderModel;
            if( 'render' == $scope.model.getActionFromState() &&
                0 < renderModel.keyQuestionList.length &&
                angular.isUndefined( event.target.type ) &&
                96 <= event.which && event.which <= 105 ) {
              var rank = event.which - 96;
              var question = renderModel.keyQuestionList[renderModel.keyQuestionIndex];

              if( 'boolean' == question.type ) {
                // 1 is yes, 2 is no, 3 is dkna and 4 is refuse
                var answer = 1 == rank ? true
                           : 2 == rank ? false
                           : 3 == rank ? 'dkna'
                           : 4 == rank ? 'refuse'
                           : null;
                
                if( null != answer ) {
                  var property = angular.isString( answer ) ? answer : answer ? 'yes' : 'no';
                  renderModel.data[question.id][property] = !renderModel.data[question.id][property];
                  renderModel.setAnswer( question, answer );
                }
              } else {
                // check if the key's rank is within the option list or the 2 dkna/refuse options
                if( rank <= question.optionList.length ) {
                  var answer = question.optionList[rank-1];
                  renderModel.data[question.id][answer.id] = !renderModel.data[question.id][answer.id];
                  renderModel.setAnswer( question, answer );
                } else if( rank == question.optionList.length + 1 ) {
                  renderModel.data[question.id].dkna = !renderModel.data[question.id].dkna;
                  renderModel.setAnswer( question, 'dkna' );
                } else if( rank == question.optionList.length + 2 ) {
                  renderModel.data[question.id].refuse = !renderModel.data[question.id].refuse;
                  renderModel.setAnswer( question, 'refuse' );
                }
              }
              $scope.$apply();

              // advance to the next question, looping back to the first when we're at the end of the list
              renderModel.keyQuestionIndex++;
              if( renderModel.keyQuestionIndex >= renderModel.keyQuestionList.length ) renderModel.keyQuestionIndex = 0;
            }
          } );

          $q.all( [
            $scope.model.viewModel.onView(),
            $scope.model.renderModel.onLoad()
          ] ).then( function() {
            $scope.isComplete = true;
          } );
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnPageView', [
    'CnPageModelFactory',
    function( CnPageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnPageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageAddFactory', [
    'CnBaseAddFactory',
    function( CnBaseAddFactory ) {
      var object = function( parentModel ) { CnBaseAddFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageRenderFactory', [
    'CnHttpFactory', '$state', '$document', '$transitions',
    function( CnHttpFactory, $state, $document, $transitions ) {
      var object = function( parentModel ) {
        var self = this;

        function setExclusiveAnswer( questionId, value ) {
          // unselect all other values
          for( var property in self.data[questionId] ) {
            if( self.data[questionId].hasOwnProperty( property ) ) {
              if( 'other' != property && value != property ) self.data[questionId][property] = false;
            }
          }

          // unset the other value
          if( angular.isDefined( self.data[questionId].other ) ) self.data[questionId].other = null;
        }

        angular.extend( this, {
          parentModel: parentModel,
          questionList: [],
          data: {},
          keyQuestionList: null,
          keyQuestionIndex: null,
          onLoad: function() {
            this.keyQuestionList = [];
            this.keyQuestionIndex = 0;
            return CnHttpFactory.instance( {
              path: this.parentModel.getServiceResourcePath() + '/question'
            } ).query().then( function( response ) {
              self.questionList = response.data;
              self.questionList.forEach( function( question ) {
                // all questions may have no answer
                self.data[question.id] = { dkna: false, refuse: false };

                if( 'boolean' == question.type ) {
                  self.keyQuestionList.push( question );
                  angular.extend( self.data[question.id], { yes: false, no: false } );
                } else if( 'list' == question.type ) {
                  self.keyQuestionList.push( question );
                  CnHttpFactory.instance( {
                    path: ['question', question.id, 'question_option' ].join( '/' ),
                    data: {
                      select: { column: [ 'name', 'value', 'exclusive' ] },
                      modifier: { order: 'question_option.rank' }
                    }
                  } ).query().then( function( response ) {
                    question.optionList = response.data;
                    question.optionList.forEach( function( option ) {
                      self.data[question.id][option.id] = false;
                      if( 'OTHER' == option.value ) self.data[question.id].other = null;
                    } );
                  } );
                }
              } );
            } );
          },
          setAnswer: function( question, value ) {
            if( 'dkna' == value || 'refuse' == value ) {
              if( self.data[question.id][value] ) setExclusiveAnswer( question.id, value );
            } else {
              // handle each question type
              if( 'boolean' == question.type ) {
                // unselect all other values
                for( var property in self.data[question.id] ) {
                  if( self.data[question.id].hasOwnProperty( property ) ) {
                    if( ( value ? 'yes' : 'no' ) != property ) self.data[question.id][property] = false;
                  }
                }
              } else if( 'list' == question.type ) {
                // unselect certain values if we're checking this option
                if( self.data[question.id][value.id] ) {
                  if( value.exclusive ) {
                    setExclusiveAnswer( question.id, value.id );
                  } else {
                    // unselect all no-answer and exclusive values
                    self.data[question.id].dkna = false;
                    self.data[question.id].refuse = false;
                    question.optionList.filter( option => option.exclusive ).forEach( function( option ) {
                      self.data[question.id][option.id] = false;
                    } );
                  }
                }

                // handle the special circumstance when clicking the other option
                if( 'OTHER' == value.value ) {
                  if( self.data[question.id][value.id] ) document.getElementById( 'other' + value.id ).focus();
                  else self.data[question.id].other = null;
                }
              }
            }
          },
          viewPage: function() {
            $state.go(
              'page.view',
              { identifier: this.parentModel.viewModel.record.getIdentifier() },
              { reload: true }
            );
          },
          renderPreviousPage: function() {
            $state.go(
              'page.render',
              { identifier: this.parentModel.viewModel.record.previous_page_id },
              { reload: true }
            );
          },
          renderNextPage: function() {
            $state.go(
              'page.render',
              { identifier: this.parentModel.viewModel.record.next_page_id },
              { reload: true }
            );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageViewFactory', [
    'CnBaseViewFactory', 'CnHttpFactory', '$state',
    function( CnBaseViewFactory, CnHttpFactory, $state ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );

        angular.extend( this, {
          viewPreviousPage: function() {
            $state.go(
              'page.view',
              { identifier: this.record.previous_page_id },
              { reload: true }
            );
          },
          viewNextPage: function() {
            $state.go(
              'page.view',
              { identifier: this.record.next_page_id },
              { reload: true }
            );
          }
        } );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnPageModelFactory', [
    'CnBaseModelFactory', 'CnPageAddFactory', 'CnPageListFactory', 'CnPageRenderFactory', 'CnPageViewFactory',
    function( CnBaseModelFactory, CnPageAddFactory, CnPageListFactory, CnPageRenderFactory, CnPageViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnPageAddFactory.instance( this );
        this.listModel = CnPageListFactory.instance( this );
        this.renderModel = CnPageRenderFactory.instance( this );
        this.viewModel = CnPageViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
