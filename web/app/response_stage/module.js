define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'response_stage', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'qnaire',
        column: 'qnaire.id'
      }
    },
    name: {
      singular: 'response stage',
      plural: 'response stages',
      possessive: 'response stage\'s'
    },
    columnList: {
      stage_rank: {
        title: 'Rank',
        column: 'stage.rank'
      },
      stage_name: {
        title: 'Name',
        column: 'stage.name'
      },
      status: {
        title: 'Status',
        column: 'response_stage.status',
        highlight: 'active'
      },
      deviation_type: {
        title: 'Devaition',
        column: 'deviation_type.name'
      },
      start_datetime: {
        title: 'Start Date & Time',
        type: 'datetimesecond'
      },
      end_datetime: {
        title: 'End Date & Time',
        type: 'datetimesecond'
      },
      comments: {
        title: 'Comments',
        type: 'text',
        limit: null
      }
    },
    defaultOrder: {
      column: 'stage.rank',
      reverse: false
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnResponseStageList', [
    'CnResponseStageModelFactory',
    function( CnResponseStageModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnResponseStageModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseStageListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnResponseStageModelFactory', [
    'CnBaseModelFactory', 'CnResponseStageListFactory',
    function( CnBaseModelFactory, CnResponseStageListFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnResponseStageListFactory.instance( this );
        this.getViewEnabled = function() { return false; }
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

}  );
