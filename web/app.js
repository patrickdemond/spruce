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
  var columnList = {
    rank: { title: 'Rank', type: 'rank' },
    name: { title: 'Name' }
  };

  if( 'module' == type ) columnList.page_count = { title: 'Pages' };
  else if( 'page' == type ) columnList.question_count = { title: 'Questions' };
  else if( 'question' == type ) columnList.question_option_count = { title: 'Question Options' };
  columnList.precondition = { title: 'Precondition' };

  angular.extend( module, {
    identifier: {},
    name: {
      singular: type.replace( /_/g, ' ' ),
      plural: type.replace( /_/g, ' ' ) + '',
      possessive: type.replace( / /g, ' ' ) + '\'s'
    },
    columnList: columnList,
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
      type: 'string',
      regex: '^[a-zA-Z_][a-zA-Z0-9_]*$'
    },
    precondition: {
      title: 'Precondition',
      type: 'text',
      help: 'A special expression which restricts whether or not to show this ' + type.replace( /_/g, ' ' ) + '.'
    },
    description: {
      title: 'Description',
      type: 'text',
      help: 'The description in the questionnaire\'s language.',
      isExcluded: 'view'
    },
    readonly: { column: 'qnaire.readonly', type: 'hidden' }
  } );

  module.addInput( '', 'previous_id', { isExcluded: true } );
  module.addInput( '', 'next_id', { isExcluded: true } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPrevious(); },
    isDisabled: function( $state, model ) { return model.viewModel.navigating || null == model.viewModel.record.previous_id; }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNext(); },
    isDisabled: function( $state, model ) { return model.viewModel.navigating || null == model.viewModel.record.next_id; }
  } );

  module.addExtraOperation( 'view', {
    title: 'Move/Copy',
    operation: function( $state, model ) {
      $state.go( type + '.clone', { identifier: model.viewModel.record.getIdentifier() } );
    }
  } );

  var typeCamel = type.snakeToCamel().ucWords();

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cn'+typeCamel+'Add', [
    'Cn'+typeCamel+'ModelFactory',
    function( CnModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'add.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cn'+typeCamel+'List', [
    'Cn'+typeCamel+'ModelFactory',
    function( CnModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cn'+typeCamel+'View', [
    'Cn'+typeCamel+'ModelFactory', '$document', '$transitions',
    function( CnModelFactory, $document, $transitions ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnModelFactory.root;

          // bind keyup (first unbind to prevent duplicates)
          $document.unbind( 'keyup' );
          $document.bind( 'keyup', function( event ) {
            // don't process hotkeys when we're focussed on input-based UI elements
            if( !['input','select','textarea'].includes( event.target.localName ) ) {
              event.stopPropagation();
              if( 37 == event.which ) {
                if( null != $scope.model.viewModel.record.previous_id ) $scope.model.viewModel.viewPrevious();
              } else if( 39 == event.which ) {
                if( null != $scope.model.viewModel.record.next_id ) $scope.model.viewModel.viewNext();
              }
            }
          } );
          $transitions.onExit( {}, function( transition ) {
            $document.unbind( 'keyup' );
          }, { invokeLimit: 1 } )
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'Cn'+typeCamel+'AddFactory', [
    'CnBaseAddFactory', 'CnHttpFactory',
    function( CnBaseAddFactory, CnHttpFactory ) {
      var object = function( parentModel ) {
        CnBaseAddFactory.construct( this, parentModel );

        // transition to viewing the new record instead of the default functionality
        this.transitionOnSave = function( record ) { parentModel.transitionToViewState( record ); };

        // get the parent's name for the breadcrumb trail
        angular.extend( this, {
          onNew: function( record ) {
            var self = this;
            return this.$$onNew( record ).then( function() {
              // get the parent page's name
              self.parentName = null;
              var parentIdentifier = parentModel.getParentIdentifier();
              if( angular.isDefined( parentIdentifier.subject ) ) {
                return CnHttpFactory.instance( {
                  path: parentIdentifier.subject + '/' + parentIdentifier.identifier,
                  data: { select: { column: 'name' } }
                } ).get().then( function( response ) {
                  self.parentName = response.data.name;
                } );
              }
            } );
          }
        } );
      };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'Cn'+typeCamel+'ListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'Cn'+typeCamel+'ViewFactory', [
    'CnBaseViewFactory', 'CnBaseQnairePartViewFactory',
    function( CnBaseViewFactory, CnBaseQnairePartViewFactory ) {
      var object = function( parentModel, root ) {
        var self = this;
        CnBaseViewFactory.construct( this, parentModel, root );
        CnBaseQnairePartViewFactory.construct( this, type );
      }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'Cn'+typeCamel+'ModelFactory', [
    'CnBaseModelFactory', 'Cn'+typeCamel+'AddFactory', 'Cn'+typeCamel+'ListFactory', 'Cn'+typeCamel+'ViewFactory', 'CnHttpFactory',
    function( CnBaseModelFactory, CnAddFactory, CnListFactory, CnViewFactory, CnHttpFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnAddFactory.instance( this );
        this.listModel = CnListFactory.instance( this );
        this.viewModel = CnViewFactory.instance( this, root );

        this.getBreadcrumbParentTitle = function() {
          return 'view' == this.getActionFromState() ? this.viewModel.record.parent_name : this.addModel.parentName;
        };

        // extend getMetadata
        this.getMetadata = function() {
          return this.$$getMetadata().then( function() {
            // setup non-record description input
            return CnHttpFactory.instance( {
              path: type + '_description'
            } ).head().then( function( response ) {
              var columnList = angular.fromJson( response.headers( 'Columns' ) );
              columnList.value.required = '1' == columnList.value.required;
              if( angular.isUndefined( self.metadata.columnList.description ) )
                self.metadata.columnList.description = {};
              angular.extend( self.metadata.columnList.description, columnList.value );
            } );
          } );
        };

        // extend getEditEnabled
        this.getEditEnabled = function() { return !this.viewModel.record.readonly && this.$$getEditEnabled(); };
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );
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
      type: {
        title: 'Type'
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
    type: {
      title: 'Type',
      type: 'enum'
    },
    value: {
      title: 'Value',
      type: 'text'
    },

    previous_description_id: { isExcluded: true },
    next_description_id: { isExcluded: true },
    readonly: { column: 'qnaire.readonly', type: 'hidden' }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewPreviousDescription(); },
    isDisabled: function( $state, model ) {
      return model.viewModel.navigating || null == model.viewModel.record.previous_description_id;
    }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: function( $state, model ) { model.viewModel.viewNextDescription(); },
    isDisabled: function( $state, model ) {
      return model.viewModel.navigating || null == model.viewModel.record.next_description_id;
    }
  } );
};

/* ######################################################################################################## */
cenozo.factory( 'CnBaseQnairePartViewFactory', [
  '$state',
  function( $state  ) {
    return {
      construct: function( object, type ) {
        angular.extend( object, {
          navigating: false,
          viewPrevious: function() {
            if( !this.navigating && this.record.previous_id ) {
              this.navigating = true;
              $state.go( type + '.view', { identifier: this.record.previous_id }, { reload: true } ).finally( function() {
                object.navigating = false;
              } );
            }
          },
          viewNext: function() {
            if( !this.navigating && this.record.next_id ) {
              this.navigating = true;
              $state.go( type + '.view', { identifier: this.record.next_id }, { reload: true } ).finally( function() {
                object.navigating = false;
              } );
            }
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
          navigating: false,
          viewPreviousDescription: function() {
            if( !this.navigating && this.record.previous_description_id ) {
              this.navigating = true;
              $state.go(
                type + '_description.view',
                { identifier: this.record.previous_description_id },
                { reload: true }
              ).finally( function() {
                object.navigating = false;
              } );
            }
          },
          viewNextDescription: function() {
            if( !this.navigating && this.record.next_description_id ) {
              this.navigating = true;
              $state.go(
                type + '_description.view',
                { identifier: this.record.next_description_id },
                { reload: true }
              ).finally( function() {
                object.navigating = false;
              } );
            }
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
          qnaireList: [],
          moduleList: [],
          pageList: [],
          questionList: [],

          viewQnaire: function( id ) {
            var languageId = null;
            var promiseList = [];
            if( 'qnaire_description.view' == $state.current.name ) {
              promiseList.push(
                CnHttpFactory.instance( {
                  path: 'qnaire_description/' + $state.params.identifier,
                  data: { select: { column: 'language_id' }, modifier: { limit: 1000 } }
                } ).get().then( function( response ) {
                  languageId = response.data.language_id;
                } )
              );
            }

            return $q.all( promiseList ).then( function() {
              return $state.go(
                null != languageId ? 'qnaire_description.view' : 'qnaire.view',
                { identifier: null != languageId ? 'qnaire_id=' + id + ';language_id=' + languageId : id },
                { reload: true }
              );
            } );
          },

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
        } else if( ['qnaire', 'qnaire_description'].includes( $scope.subject ) ) {
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

          // get the list of qnaires, modules, pages and questions (depending on what we're looking at)
          var promiseList = [
            CnHttpFactory.instance( {
              path: 'qnaire',
              data: {
                select: { column: [ 'id', 'name' ] },
                modifier: { order: 'name', limit: 1000 }
              }
            } ).query().then( function( response ) {
              $scope.qnaireList = response.data;
            } ),

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

/* ######################################################################################################## */
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

/* ######################################################################################################## */
cenozo.factory( 'CnQnairePartCloneFactory', [
  'CnHttpFactory', 'CnModalMessageFactory', 'CnModalConfirmFactory', '$q', '$filter', '$state',
  function( CnHttpFactory, CnModalMessageFactory, CnModalConfirmFactory, $q, $filter, $state ) {
    var object = function( type ) {
      var self = this;
      var parentType = 'module' == type ? 'qnaire' : 'page' == type ? 'module' : 'question' == type ? 'page' : 'question';

      angular.extend( this, {
        type: type,
        parentType: parentType,
        parentIdName: parentType.replace( ' ', '_' ).snakeToCamel() + 'Id',
        typeName: type.replace( /_/g, ' ' ).ucWords(),
        parentTypeName: 'qnaire' == parentType ? 'questionnaire' : parentType.replace( /_/g, ' ' ).ucWords(),
        sourceId: $state.params.identifier,
        sourceName: null,
        sourceParentId: null,
        working: false,
        operation: 'move',
        data: {
          qnaireId: null,
          moduleId: null,
          pageId: null,
          questionId: null,
          rank: null,
          name: null
        },
        qnaireList: [],
        moduleList: [],
        pageList: [],
        questionList: [],
        rankList: [],
        formatError: false,
        nameConflict: false,

        resetData: function( subject ) {
          // reset data
          if( angular.isUndefined( subject ) ) self.data.qnaireId = null;
          if( [ undefined, 'qnaire' ].includes( subject ) ) self.data.moduleId = null;
          if( [ undefined, 'qnaire', 'module' ].includes( subject ) ) self.data.pageId = null;
          if( [ undefined, 'qnaire', 'module', 'page' ].includes( subject ) ) self.data.questionId = null;
          self.data.rank = null;
          if( angular.isUndefined( subject ) ) self.data.name = null;
          self.formatError = false;
          self.nameConflict = false;

          // reset lists
          if( [ undefined, 'qnaire' ].includes( subject ) ) self.moduleList = [];
          if( [ undefined, 'qnaire', 'module' ].includes( subject ) ) self.pageList = [];
          if( [ undefined, 'qnaire', 'module', 'page' ].includes( subject ) ) self.questionList = [];
          if( [ undefined, 'qnaire', 'module', 'page', 'question' ].includes( subject ) ) self.rankList = [];
        },

        onLoad: function() {
          this.resetData();

          var columnList = [
            'name',
            { table: 'module', column: 'qnaire_id' },
            { table: this.parentType, column: 'name', alias: 'parentName' }
          ];
          if( [ 'page', 'question', 'question_option' ].includes( this.type ) )
            columnList.push( { table: 'page', column: 'module_id' } );
          if( [ 'question', 'question_option' ].includes( this.type ) )
            columnList.push( { table: 'question', column: 'page_id' } );
          if( 'question_option' == this.type )
            columnList.push( { table: 'question_option', column: 'question_id' } );

          return CnHttpFactory.instance( {
            path: [this.type, this.sourceId].join( '/' ),
            data: { select: { column: columnList } }
          } ).get().then( function( response ) {
            self.data.name = response.data.name;
            self.sourceName = response.data.name;
            self.parentSourceName = response.data.parentName;
            self.sourceParentId = response.data[self.parentType + '_id'];
            angular.extend( self.data, {
              qnaireId: 'qnaire' == self.parentType ? null : response.data.qnaire_id,
              moduleId: 'module' == self.parentType ? null : response.data.module_id,
              pageId: 'page' == self.parentType ? null : response.data.page_id,
              questionId: 'question' == self.parentTYpe ? null : response.data.question_id
            } );
          } ).then( function() {
            return $q.all( [
              self.resetQnaireList(),
              self.setQnaire( true ),
              self.setModule( true ),
              self.setPage( true ),
              self.setQuestion( true )
            ] );
          } );
        },

        setOperation: function() {
          // update the parent list when the operation type changes
          if( 'qnaire' == this.parentType ) {
            return this.resetQnaireList();
          } else if( 'module' == this.parentType ) {
            return this.setQnaire( true );
          } else if( 'page' == this.parentType ) {
            return this.setModule( true );
          } else if( 'question' == this.parentType ) {
            return this.setPage( true );
          }
        },

        resetQnaireList: function() {
          return CnHttpFactory.instance( {
            path: 'qnaire',
            data: {
              select: { column: [ 'id', 'name' ] },
              modifier: { order: { name: false } }
            },
          } ).query().then( function( response ) {
            self.qnaireList = response.data
              .filter( item => 'move' != self.operation || 'qnaire' != self.parentType || self.sourceParentId != item.id )
              .map( item => ({ value: item.id, name: item.name }) );
            self.qnaireList.unshift( { value: null, name: '(choose target questionnaire)' } );
          } );
        },

        setQnaire: function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) self.resetData( 'qnaire' );

          // either update the rank list or the module list depending on the type
          if( 'module' == this.type ) {
            return this.updateRankList();
          } else if( null == self.data.qnaireId ) {
            self.moduleList = [];
          } else {
            return CnHttpFactory.instance( {
              path: ['qnaire', self.data.qnaireId, 'module'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: { rank: false } }
              },
            } ).query().then( function( response ) {
              self.moduleList = response.data
                .filter( item => 'move' != self.operation || 'module' != self.parentType || self.sourceParentId != item.id )
                .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
              self.moduleList.unshift( { value: null, name: '(choose target module)' } );
            } );
          }
        },

        setModule: function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) self.resetData( 'module' );

          // either update the rank list or the page list depending on the type
          if( 'page' == this.type ) {
            return this.updateRankList();
          } else if( null == self.data.moduleId ) {
            self.pageList = [];
          } else {
            return CnHttpFactory.instance( {
              path: ['module', self.data.moduleId, 'page'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: { rank: false } }
              },
            } ).query().then( function( response ) {
              self.pageList = response.data
                .filter( item => 'move' != self.operation || 'page' != self.parentType || self.sourceParentId != item.id )
                .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
              self.pageList.unshift( { value: null, name: '(choose target page)' } );
            } );
          }
        },

        setPage: function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) self.resetData( 'page' );

          // either update the rank list or the question list depending on the type
          if( 'question' == this.type ) {
            return this.updateRankList();
          } else if( null == self.data.pageId ) {
            self.questionList = [];
          } else {
            return CnHttpFactory.instance( {
              path: ['page', self.data.pageId, 'question'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { where: { column: 'question.type', operator: '=', value: 'list' }, order: { rank: false } }
              },
            } ).query().then( function( response ) {
              self.questionList = response.data
                .filter( item => 'move' != self.operation || 'question' != self.parentType || self.sourceParentId != item.id )
                .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
              self.questionList.unshift( {
                value: null,
                name: 0 == self.questionList.length ?
                  '(the selected page has no list type questions)' : '(choose target list question)'
              } );
            } );
          }
        },

        setQuestion: function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) self.resetData( 'question' );
          return this.updateRankList();
        },

        updateRankList: function() {
          // if the parent hasn't been selected then the rank list should be empty
          if( null == self.data[this.parentIdName] ) {
            self.rankList = [];
          } else {
            return CnHttpFactory.instance( {
              path: [this.parentType, this.data[self.parentIdName], this.type].join( '/' ),
              data: {
                select: { column: { column: 'MAX( ' + this.type + '.rank )', alias: 'max', table_prefix: false } }
              },
            } ).query().then( function( response ) {
              var maxRank = null == response.data[0].max ? 1 : parseInt( response.data[0].max ) + 1;
              self.rankList = [];
              for( var rank = 1; rank <= maxRank; rank++ ) {
                self.rankList.push( { value: rank, name: $filter( 'cnOrdinal' )( rank ) } );
              }
              self.rankList.unshift( { value: null, name: '(choose target rank)' } );
            } );
          }
        },

        isComplete: function() {
          return (
            !this.working &&
            null != this.data.rank &&
            null != this.data.qnaireId && (
              'page' != this.type ||
              null != this.data.moduleId
            ) && (
              'question' != this.type || (
                null != this.data.moduleId &&
                null != this.data.pageId
              )
            ) && (
              'question_option' != this.type || (
                null != this.data.moduleId &&
                null != this.data.pageId &&
                null != this.data.questionId
              )
            ) && (
              'move' == this.operation || (
                !this.nameConflict &&
                !this.formatError &&
                null != this.data.name
              )
            )
          );
        },

        cancel: function() {
          $state.go( this.type + '.view', { identifier: self.sourceId } );
        },

        save: function() {
          this.working = true;

          if( 'move' == this.operation ) {
            // a private function that moves the record (used below)
            function move() {
              var data = { rank: self.data.rank };
              data[self.parentType + '_id'] = self.data[self.parentIdName];

              return CnHttpFactory.instance( {
                path: self.type + '/' + self.sourceId,
                data: data
              } ).patch().then( function() {
                $state.go( self.type + '.view', { identifier: self.sourceId } );
              } ).finally( function() {
                self.working = false;
              } );
            }

            // see if we'll be leaving the parent without any children
            CnHttpFactory.instance( {
              path: this.type,
              data: { modifier: { where: { column: this.parentType + '_id', operator: '=', value: this.sourceParentId } } }
            } ).count().then( function( response ) {
              if( 1 == parseInt( response.headers( 'Total' ) ) ) {
                CnModalConfirmFactory.instance( {
                  message:
                    'This is the only ' + self.typeName.toLowerCase() + ' belonging to its parent ' +
                    self.parentTypeName.toLowerCase() + '.  Do you wish to delete the ' + self.parentTypeName.toLowerCase() +
                    ' after the ' + self.typeName.toLowerCase() + ' is moved?'
                } ).show().then( function( response ) {
                  // first move the record
                  move();

                  // now remove the parent if requested to
                  if( response ) CnHttpFactory.instance( { path: self.parentType + '/' + self.sourceParentId } ).delete();
                } );
              } else move();
            } );
          } else { // clone
            // make sure the name is valid
            if( null == this.data.name.match( /^[a-zA-Z_][a-zA-Z0-9_]*$/ ) ) {
              this.formatError = true;
            } else {
              var data = { rank: this.data.rank, name: this.data.name };
              data[this.parentType + '_id'] = this.data[this.parentIdName];

              return CnHttpFactory.instance( {
                path: this.type + '?clone=' + this.sourceId,
                data: data,
                onError: function( response ) {
                  if( 409 == response.status ) self.nameConflict = true;
                  else CnModalMessageFactory.httpError( response );
                }
              } ).post().then( function( response ) {
                $state.go( self.type + '.view', { identifier: response.data } );
              } ).finally( function() {
                self.working = false;
              } );
            }
          }
        }
      } );
    }
    return { instance: function( type ) { return new object( type ); } };
  }
] );
