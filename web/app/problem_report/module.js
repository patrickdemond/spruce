cenozoApp.defineModule({
  name: "problem_report",
  models: ["list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "response",
          column: "response.id",
        },
      },
      name: {
        singular: "problem report",
        plural: "problem reports",
        possessive: "problem report's",
      },
      columnList: {
        questionnaire: {
          title: "Questionnaire",
          column: "qnaire.name",
          isIncluded: function( $state, model ) {
            return 'problem_report' == model.getSubjectFromState();
          },
        },
        uid: {
          title: "Participant",
          column: "participant.uid",
          isIncluded: function( $state, model ) {
            return 'problem_report' == model.getSubjectFromState();
          },
        },
        show_hidden: {
          title: "Show Hidden",
          type: "boolean",
        },
        page_name: {
          title: "Page",
        },
        remote_address: {
          title: "Remote Address",
        },
        datetime: {
          title: "Date & Time",
          column: "problem_report.datetime",
          type: "datetime"
        },
      },
      defaultOrder: {
        column: "problem_report.datetime",
        reverse: true,
      },
    });

    module.addInputGroup("", {
      questionnaire: {
        title: "Questionnaire",
        column: "qnaire.name",
        type: "string",
      },
      uid: {
        title: "Participant",
        column: "participant.uid",
        type: "string",
      },
      show_hidden: {
        title: "Show Hidden",
        type: "boolean",
      },
      page_name: {
        title: "Page",
        type: "string",
      },
      remote_address: {
        title: "Remote Address",
        type: "string",
      },
      user_agent: {
        title: "User Agent",
        type: "string",
      },
      brand: {
        title: "User Agent",
        type: "string",
      },
      platform: {
        title: "User Agent",
        type: "string",
      },
      mobile: {
        title: "User Agent",
        type: "string",
      },
      datetime: {
        title: "Date & Time",
        column: "problem_report.datetime",
        type: "datetime",
      },
      description: {
        title: "Description",
        type: "text",
      }
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnProblemReportModelFactory", [
      "CnBaseModelFactory",
      "CnProblemReportListFactory",
      "CnProblemReportViewFactory",
      function (
        CnBaseModelFactory,
        CnProblemReportListFactory,
        CnProblemReportViewFactory
      ) { 
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.listModel = CnProblemReportListFactory.instance(this);
          this.viewModel = CnProblemReportViewFactory.instance(this, root);
          this.getAddEnabled = function () { return false; };
        };

        return {
          root: new object(true),
          instance: function () {
            return new object(false);
          },
        };
      },  
    ]); 
  },
});
