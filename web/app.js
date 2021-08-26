'use strict';

var cenozo = angular.module( 'cenozo' );

cenozo.controller( 'HeaderCtrl', [
  '$scope', 'CnBaseHeader',
  async function( $scope, CnBaseHeader ) {
    // copy all properties from the base header
    await CnBaseHeader.construct( $scope );
  }
] );

/* ######################################################################################################## */
cenozo.directive( 'cnDescriptionHelp', [
  function() {
    return {
      templateUrl: cenozoApp.getFileUrl( 'pine', 'description_help.tpl.html' ),
      restrict: 'E',
      scope: { model: '=' }
    };
  }
] );

/* ######################################################################################################## */
cenozo.directive( 'cnDescriptionPatch', [
  function() {
    return {
      templateUrl: cenozoApp.getFileUrl( 'pine', 'description_patch.tpl.html' ),
      restrict: 'E',
      scope: { model: '=' }
    };
  }
] );

/* ######################################################################################################## */
cenozo.directive( 'cnQnairePartPatch', [
  function() {
    return {
      templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_part_patch.tpl.html' ),
      restrict: 'E',
      scope: {
        model: '=',
        subject: '@'
      },
      controller: function( $scope ) {
        if( 'module' == $scope.subject ) $scope.childSubject = 'page';
        else if( 'page' == $scope.subject ) $scope.childSubject = 'question';
        else if( 'question' == $scope.subject ) $scope.childSubject = 'question_option';
        else $scope.childSubject = null;
      }
    };
  }
] );

/* ######################################################################################################## */
cenozoApp.initQnairePartModule = function( module, type ) {
  var columnList = {
    rank: { title: 'Rank', type: 'rank' },
    name: { title: 'Name' }
  };

  var childType = null;
  if( 'module' == type ) {
    childType = 'page';
    columnList.page_count = { title: 'Pages' };
  } else if( 'page' == type ) {
    childType = 'question';
    columnList.question_count = { title: 'Questions' };
  } else if( 'question' == type ) {
    childType = 'question_option';
    columnList.question_option_count = { title: 'Question Options' };
  }
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
      regex: 'question_option' == type ? '^[a-zA-Z0-9_]*$' : '^[a-zA-Z_][a-zA-Z0-9_]*$'
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
    operation: async function( $state, model ) {
      await $state.go( type + '.clone', { identifier: model.viewModel.record.getIdentifier() } );
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

        // get the parent's name for the breadcrumb trail
        angular.extend( this, {
          // transition to viewing the new record instead of the default functionality
          transitionOnSave: function( record ) { parentModel.transitionToViewState( record ); },

          onNew: async function( record ) {
            await this.$$onNew( record );

            // get the parent page's name
            this.parentName = null;
            var parentIdentifier = parentModel.getParentIdentifier();
            if( angular.isDefined( parentIdentifier.subject ) ) {
              var response = await CnHttpFactory.instance( {
                path: parentIdentifier.subject + '/' + parentIdentifier.identifier,
                data: { select: { column: 'name' } }
              } ).get();

              this.parentName = response.data.name;
            }
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
        CnBaseViewFactory.construct( this, parentModel, root, childType );
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
        CnBaseModelFactory.construct( this, module );
        this.addModel = CnAddFactory.instance( this );
        this.listModel = CnListFactory.instance( this );
        this.viewModel = CnViewFactory.instance( this, root );

        this.getBreadcrumbParentTitle = function() {
          return 'view' == this.getActionFromState() ? this.viewModel.record.parent_name : this.addModel.parentName;
        };

        // extend getMetadata
        this.getMetadata = async function() {
          await this.$$getMetadata();

          // setup non-record description input
          var response = await CnHttpFactory.instance( {
            path: type + '_description'
          } ).head();

          var columnList = angular.fromJson( response.headers( 'Columns' ) );
          columnList.value.required = '1' == columnList.value.required;
          if( angular.isUndefined( this.metadata.columnList.description ) )
            this.metadata.columnList.description = {};
          angular.extend( this.metadata.columnList.description, columnList.value );
        };

        // extend getEditEnabled and getDeleteEnabled based on the parent qnaire readonly column
        this.getEditEnabled = function() { return !this.viewModel.record.readonly && this.$$getEditEnabled(); };
        this.getDeleteEnabled = function() { return !this.viewModel.record.readonly && this.$$getDeleteEnabled(); };
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
        title: 'Language'
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
      title: 'Language',
      column: 'language.code',
      type: 'string',
      isConstant: true
    },
    type: {
      title: 'Type',
      type: 'enum',
      isConstant: true
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
    operation: async function( $state, model ) { await model.viewModel.viewPreviousDescription(); },
    isDisabled: function( $state, model ) {
      return model.viewModel.navigating || null == model.viewModel.record.previous_description_id;
    }
  } );

  module.addExtraOperation( 'view', {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: 'btn-info',
    operation: async function( $state, model ) { await model.viewModel.viewNextDescription(); },
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
          viewPrevious: async function() {
            if( !this.navigating && this.record.previous_id ) {
              try {
                this.navigating = true;
                await $state.go( type + '.view', { identifier: this.record.previous_id }, { reload: true } );
              } finally {
                object.navigating = false;
              }
            }
          },
          viewNext: async function() {
            if( !this.navigating && this.record.next_id ) {
              try {
                this.navigating = true;
                await $state.go( type + '.view', { identifier: this.record.next_id }, { reload: true } );
              } finally {
                object.navigating = false;
              }
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
          viewPreviousDescription: async function() {
            if( !this.navigating && this.record.previous_description_id ) {
              try {
                this.navigating = true;
                await $state.go(
                  type + '_description.view',
                  { identifier: this.record.previous_description_id },
                  { reload: true }
                );
              } finally {
                this.navigating = false;
              }
            }
          },
          viewNextDescription: async function() {
            if( !this.navigating && this.record.next_description_id ) {
              try {
                this.navigating = true;
                await $state.go(
                  type + '_description.view',
                  { identifier: this.record.next_description_id },
                  { reload: true }
                );
              } finally {
                this.navigating = false;
              }
            }
          }
        } );
      }
    };
  }
] );

/* ######################################################################################################## */
cenozo.directive( 'cnQnaireNavigator', [
  'CnHttpFactory', '$state',
  function( CnHttpFactory, $state ) {
    return {
      templateUrl: cenozoApp.getFileUrl( 'pine', 'qnaire_navigator.tpl.html' ),
      restrict: 'E',
      controller: async function( $scope ) {
        // used to navigate to another qnaire part (either root or description)
        async function viewQnairePart( subject, id ) {
          var keys = null;
          if( subject + '_description.view' == $state.current.name ) {
            var languageMatch = $state.params.identifier.match( /language_id=([0-9]+)/ );
            var typeMatch = $state.params.identifier.match( /type=([a-z]+)/ );
            if( null == languageMatch || null == typeMatch ) {
              var response = await CnHttpFactory.instance( {
                path: subject + '_description/' + $state.params.identifier,
                data: { select: { column: [ 'language_id', 'type' ] } }
              } ).get();

              keys = response.data;
            } else {
              keys = {
                language_id: languageMatch[1],
                type: typeMatch[1]
              };
            }
          }

          // if we are returned description keys then use them to navigate to the sister description
          var identifier = null != keys
                         ? [ subject + '_id='+id, 'language_id='+keys.language_id, 'type='+keys.type ].join( ';' )
                         : id;
          await $state.go(
            ( null != keys ? subject + '_description' : subject ) + '.view',
            { identifier: identifier },
            { reload: true }
          );
        }
        
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

          viewQnaire: async function( id ) { await $state.go( 'qnaire.view', { identifier: id }, { reload: true } ); },
          viewModule: async function( id ) { await viewQnairePart( 'module', id ); },
          viewPage: async function( id ) { await viewQnairePart( 'page', id ); },
          viewQuestion: async function( id ) { await viewQnairePart( 'question', id ); }
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

        var response = await CnHttpFactory.instance( {
          path: $scope.subject + '/' + $state.params.identifier,
          data: { select: { column: columnList } }
        } ).get();

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
        var response = await CnHttpFactory.instance( {
          path: 'qnaire',
          data: {
            select: { column: [ 'id', 'name' ] },
            modifier: { order: 'name', limit: 1000 }
          }
        } ).query();
        $scope.qnaireList = response.data;

        var response = await CnHttpFactory.instance( {
          path: [ 'qnaire', $scope.currentQnaire.id, 'module' ].join( '/' ),
          data: {
            select: { column: [ 'id', 'rank', 'name' ] },
            modifier: { order: 'rank', limit: 1000 }
          }
        } ).query();
        $scope.moduleList = response.data;

        if( $scope.currentModule ) {
          var response = await CnHttpFactory.instance( {
            path: [ 'module', $scope.currentModule.id, 'page' ].join( '/' ),
            data: {
              select: { column: [ 'id', 'rank', 'name' ] },
              modifier: { order: 'rank', limit: 1000 }
            }
          } ).query();
          $scope.pageList = response.data;
        }

        if( $scope.currentPage ) {
          var response = await CnHttpFactory.instance( {
            path: [ 'page', $scope.currentPage.id, 'question' ].join( '/' ),
            data: {
              select: { column: [ 'id', 'rank', 'name' ] },
              modifier: { order: 'rank', limit: 1000 }
            }
          } ).query();
          $scope.questionList = response.data;
        }
      }
    };
  }
] );

/* ######################################################################################################## */
cenozo.service( 'CnTranslationHelper', [
  '$filter', '$sce',
  function( $filter, $sce ) {
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
          dkna: { en: 'Don\'t Know / No Answer', fr: 'Ne sais pas / pas de réponse' },
          refuse: { en: 'Refused', fr: 'Refus' },
          preferNotToAnswer: { en: 'Prefer not to answer', fr: 'Préfère ne pas répondre' },
          begin: { en: 'Begin', fr: 'Commencer' },
          next: { en: 'Next', fr: 'Suivant' },
          previous: { en: 'Previous', fr: 'Précédent' },
          submit: { en: 'Submit', fr: 'Envoyer' },
          minimumTitle: { en: 'Value is too small', fr: 'La valeur est trop petite' },
          maximumTitle: { en: 'Value is too large', fr: 'La valeur est trop grande' },
          limitMessage: { en: 'Please provide an answer that is', fr: 'Veuillez fournir une réponse' },
          equalOrGreater: { en: 'equal to or greater than', fr: 'égale ou supérieure à' },
          equalOrLess: { en: 'equal to or less than', fr: 'égale ou inférieure à' },
          between: { en: 'between', fr: 'comprise entre' },
          and: { en: 'and', fr: 'et' },
          qnaireClosed: { en: 'Questionnaire Closed', fr: 'Période de réponse terminée' }
        }
      },
      // used by services below to convert a list of descriptions into an object
      parseDescriptions: function( descriptionList, showHidden ) {
        var code = null;
        if( !angular.isString( descriptionList ) ) descriptionList = '';
        return descriptionList.split( '`' ).reduce( function( list, part ) {
          if( angular.isDefined( showHidden ) ) {
            // replace hidden and reverse-hidden codes
            part = showHidden
                 ? part.replace( /{{!.*!}}/g, '' ).replace( /{{/g, '' ).replace( /}}/g, '' )
                 : part.replace( /{{!/g, '' ).replace( /!}}/g, '' ).replace( /{{.*}}/g, '' );
          }

          if( null == code ) {
            code = part;
          } else {
            list[code] = $sce.trustAsHtml( null == part.match( /<[a-zA-Z]+>/ ) ? $filter( 'cnNewlines' )( part ) : part );
            code = null;
          }
          return list;
        }, {} );
      }
    };
  }
] );

/* ######################################################################################################## */
cenozo.factory( 'CnQnairePartCloneFactory', [
  'CnHttpFactory', 'CnModalMessageFactory', '$filter', '$state',
  function( CnHttpFactory, CnModalMessageFactory, $filter, $state ) {
    var object = function( type ) {
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
          if( angular.isUndefined( subject ) ) this.data.qnaireId = null;
          if( [ undefined, 'qnaire' ].includes( subject ) ) this.data.moduleId = null;
          if( [ undefined, 'qnaire', 'module' ].includes( subject ) ) this.data.pageId = null;
          if( [ undefined, 'qnaire', 'module', 'page' ].includes( subject ) ) this.data.questionId = null;
          this.data.rank = null;
          if( angular.isUndefined( subject ) ) this.data.name = null;
          this.formatError = false;
          this.nameConflict = false;

          // reset lists
          if( [ undefined, 'qnaire' ].includes( subject ) ) this.moduleList = [];
          if( [ undefined, 'qnaire', 'module' ].includes( subject ) ) this.pageList = [];
          if( [ undefined, 'qnaire', 'module', 'page' ].includes( subject ) ) this.questionList = [];
          if( [ undefined, 'qnaire', 'module', 'page', 'question' ].includes( subject ) ) this.rankList = [];
        },

        onLoad: async function() {
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

          var response = await CnHttpFactory.instance( {
            path: [this.type, this.sourceId].join( '/' ),
            data: { select: { column: columnList } }
          } ).get();

          this.data.name = response.data.name;
          this.sourceName = response.data.name;
          this.parentSourceName = response.data.parentName;
          this.sourceParentId = response.data[this.parentType + '_id'];
          angular.extend( this.data, {
            qnaireId: 'qnaire' == this.parentType ? null : response.data.qnaire_id,
            moduleId: 'module' == this.parentType ? null : response.data.module_id,
            pageId: 'page' == this.parentType ? null : response.data.page_id,
            questionId: 'question' == this.parentType ? null : response.data.question_id
          } );

          await Promise.all( [
            this.resetQnaireList(),
            this.setQnaire( true ),
            this.setModule( true ),
            this.setPage( true ),
            this.setQuestion( true )
          ] );
        },

        setOperation: async function() {
          // update the parent list when the operation type changes
          if( 'qnaire' == this.parentType ) {
            await this.resetQnaireList();
          } else if( 'module' == this.parentType ) {
            await this.setQnaire( true );
          } else if( 'page' == this.parentType ) {
            await this.setModule( true );
          } else if( 'question' == this.parentType ) {
            await this.setPage( true );
          }
        },

        resetQnaireList: async function() {
          var response = await CnHttpFactory.instance( {
            path: 'qnaire',
            data: {
              select: { column: [ 'id', 'name' ] },
              modifier: { order: { name: false } }
            },
          } ).query();

          var self = this;
          this.qnaireList = response.data
            .filter( item => 'move' != self.operation || 'qnaire' != self.parentType || self.sourceParentId != item.id )
            .map( item => ({ value: item.id, name: item.name }) );
          this.qnaireList.unshift( { value: null, name: '(choose target questionnaire)' } );
        },

        setQnaire: async function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) this.resetData( 'qnaire' );

          // either update the rank list or the module list depending on the type
          if( 'module' == this.type ) {
            await this.updateRankList();
          } else if( null == this.data.qnaireId ) {
            this.moduleList = [];
          } else {
            var response = await CnHttpFactory.instance( {
              path: ['qnaire', this.data.qnaireId, 'module'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: { rank: false } }
              },
            } ).query();

            var self = this;
            this.moduleList = response.data
              .filter( item => 'move' != self.operation || 'module' != self.parentType || self.sourceParentId != item.id )
              .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
            this.moduleList.unshift( { value: null, name: '(choose target module)' } );
          }
        },

        setModule: async function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) this.resetData( 'module' );

          // either update the rank list or the page list depending on the type
          if( 'page' == this.type ) {
            await this.updateRankList();
          } else if( null == this.data.moduleId ) {
            this.pageList = [];
          } else {
            var response = await CnHttpFactory.instance( {
              path: ['module', this.data.moduleId, 'page'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { order: { rank: false } }
              },
            } ).query();

            var self = this;
            this.pageList = response.data
              .filter( item => 'move' != self.operation || 'page' != self.parentType || self.sourceParentId != item.id )
              .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
            this.pageList.unshift( { value: null, name: '(choose target page)' } );
          }
        },

        setPage: async function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) this.resetData( 'page' );

          // either update the rank list or the question list depending on the type
          if( 'question' == this.type ) {
            await this.updateRankList();
          } else if( null == this.data.pageId ) {
            this.questionList = [];
          } else {
            var response = await CnHttpFactory.instance( {
              path: ['page', this.data.pageId, 'question'].join( '/' ),
              data: {
                select: { column: [ 'id', 'rank', 'name' ] },
                modifier: { where: { column: 'question.type', operator: '=', value: 'list' }, order: { rank: false } }
              },
            } ).query();

            var self = this;
            this.questionList = response.data
              .filter( item => 'move' != self.operation || 'question' != self.parentType || self.sourceParentId != item.id )
              .map( item => ({ value: item.id, name: item.rank + '. ' + item.name }) );
            this.questionList.unshift( {
              value: null,
              name: 0 == this.questionList.length ?
                '(the selected page has no list type questions)' : '(choose target list question)'
            } );
          }
        },

        setQuestion: async function( noReset ) {
          if( angular.isUndefined( noReset ) ) noReset = false;
          if( !noReset ) await this.resetData( 'question' );
          await this.updateRankList();
        },

        updateRankList: async function() {
          // if the parent hasn't been selected then the rank list should be empty
          if( null == this.data[this.parentIdName] ) {
            this.rankList = [];
          } else {
            var response = await CnHttpFactory.instance( {
              path: [this.parentType, this.data[this.parentIdName], this.type].join( '/' ),
              data: {
                select: { column: { column: 'MAX( ' + this.type + '.rank )', alias: 'max', table_prefix: false } }
              },
            } ).query();

            var maxRank = null == response.data[0].max ? 1 : parseInt( response.data[0].max ) + 1;
            this.rankList = [];
            for( var rank = 1; rank <= maxRank; rank++ ) {
              this.rankList.push( { value: rank, name: $filter( 'cnOrdinal' )( rank ) } );
            }
            this.rankList.unshift( { value: null, name: '(choose target rank)' } );
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

        cancel: async function() {
          await $state.go( this.type + '.view', { identifier: this.sourceId } );
        },

        save: async function() {
          var data = { rank: this.data.rank };
          data[this.parentType + '_id'] = this.data[this.parentIdName];

          if( 'move' == this.operation ) {
            try {
              this.working = true;
              await CnHttpFactory.instance( { path: this.type + '/' + this.sourceId, data: data } ).patch();
              await $state.go( this.type + '.view', { identifier: this.sourceId } );
            } finally {
              this.working = false;
            }
          } else { // clone
            // make sure the name is valid
            var re = new RegExp( 'question_option' == this.type ? '^[a-zA-Z0-9_]*$' : '^[a-zA-Z_][a-zA-Z0-9_]*$' );
            if( null == re.test( this.data.name ) ) {
              this.formatError = true;
            } else {
              // add the new name to the http data
              data.name = this.data.name;
              try {
                var self = this;
                this.working = true;
                var response = await CnHttpFactory.instance( {
                  path: this.type + '?clone=' + this.sourceId,
                  data: data,
                  onError: function( error ) {
                    if( 409 == error.status ) self.nameConflict = true;
                    else CnModalMessageFactory.httpError( error );
                  }
                } ).post();

                await $state.go( this.type + '.view', { identifier: response.data } );
              } finally {
                this.working = false;
              }
            }
          }
        }
      } );
    }
    return { instance: function( type ) { return new object( type ); } };
  }
] );

/* ######################################################################################################## */
cenozo.service( 'CnModalPreStageFactory', [
  '$uibModal', '$window',
  function( $uibModal, $window ) {
    var object = function( params ) {
      angular.extend( this, { 
        title: '',
        deviationTypeList: null,
        validToken: null,
        token: null,
        deviationTypeId: undefined,
        comments: null
      } );
      angular.extend( this, params );
      if( null != this.deviationTypeList ) this.deviationTypeList.unshift( { value: undefined, name: '(Select one)' } ); 

      angular.extend( this, {
        show: function() {
          var self = this;
          return $uibModal.open( {
            backdrop: 'static',
            keyboard: !this.block,
            size: 'lg',
            modalFade: true,
            templateUrl: cenozoApp.getFileUrl( 'pine', 'modal-pre-stage.tpl.html' ),
            controller: [ '$scope', '$uibModalInstance', function( $scope, $uibModalInstance ) {
              $scope.model = self;
              $scope.checkToken = function() {
                if( $scope.model.validToken == $scope.model.token ) {
                  // the token is valid
                  $scope.form.token.$invalid = false;
                  $scope.form.token.$error.mismatch = false;
                } else {
                  if( $scope.model.token ) {
                    $scope.form.token.$error.mismatch = true;
                    $scope.form.token.$invalid = true;
                  } else {
                    $scope.form.token.$error.mismatch = false;
                  }
                }
              },
              $scope.ok = function() {
                if( !$scope.form.$valid ) {
                  // dirty all relevant inputs so we can find the problem
                  $scope.form.token.$dirty = true;
                  if( null != $scope.model.deviationTypeList ) $scope.form.deviationTypeId.$dirty = true;
                } else {
                  var response = { comments: $scope.model.comments };
                  if( null != $scope.model.deviationTypeList ) response.deviation_type_id = $scope.model.deviationTypeId;
                  $uibModalInstance.close( response );
                }
              },
              $scope.cancel = function() { $uibModalInstance.close( null ); }
            } ]
          } ).result;
        }
      } );
    };

    return { instance: function( params ) { return new object( angular.isUndefined( params ) ? {} : params ); } };
  }
] );

