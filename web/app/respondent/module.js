cenozoApp.defineModule({
  name: "respondent",
  dependencies: "page",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "qnaire",
          column: "qnaire.id",
        },
      },
      name: {
        singular: "respondent",
        plural: "respondents",
        possessive: "respondent's",
      },
      columnList: {
        qnaire: {
          column: "qnaire.name",
          title: "Questionnaire",
        },
        uid: {
          column: "participant.uid",
          title: "Participant",
        },
        first_name: {
          column: "participant.first_name",
          title: "First Name",
        },
        last_name: {
          column: "participant.last_name",
          title: "Last Name",
        },
        token: {
          title: "Token",
        },
        interview_type: {
          title: "Type",
          isIncluded: function ($state, model) {
            return false;
          }, // this is changed by the qnaire module
        },
        language: {
          column: "language.name",
          title: "Language",
          type: "string",
          isIncluded: function ($state, model) {
            return false;
          }, // this is changed by the qnaire module
        },
        response_count: {
          title: "Responses",
          isIncluded: function ($state, model) {
            return false;
          }, // this is changed by the qnaire module
        },
        status: {
          title: "Status",
          type: "string",
          highlight: "In Progress",
        },
        qnaire_progress: {
          title: "Progress",
          isIncluded: function ($state, model) {
            return false;
          }, // this is changed by the qnaire module
        },
        start_datetime: {
          column: "respondent.start_datetime",
          title: "Start Date",
          type: "datetime",
        },
        end_datetime: {
          column: "respondent.end_datetime",
          title: "End Date",
          type: "datetime",
        },
      },
      defaultOrder: {
        column: "participant.uid",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      participant_id: {
        column: "respondent.participant_id",
        title: "Participant",
        type: "lookup-typeahead",
        typeahead: {
          table: "participant",
          select:
            'CONCAT( participant.first_name, " ", participant.last_name, " (", uid, ")" )',
          where: ["participant.first_name", "participant.last_name", "uid"],
        },
        isConstant: "view",
        help: "For anonymous respondents this value should be left blank.",
      },
      first_name: {
        column: "participant.first_name",
        title: "First Name",
        type: "string",
        isConstant: true,
        isExcluded: "add",
      },
      last_name: {
        column: "participant.last_name",
        title: "Last Name",
        type: "string",
        isConstant: true,
        isExcluded: "add",
      },
      token: {
        title: "Token (Interview ID)",
        type: "string",
        format: "identifier",
        isExcluded: "add",
      },
      status: {
        title: "Status",
        type: "string",
        isConstant: true,
        isExcluded: "add",
      },
      start_datetime: {
        title: "Start Date & Time",
        type: "datetime",
        isConstant: true,
        isExcluded: "add",
      },
      end_datetime: {
        title: "End Date & Time",
        type: "datetime",
        isConstant: true,
        isExcluded: "add",
      },
      sends_mail: {
        type: "boolean",
        isExcluded: true,
      },
      has_attributes: {
        type: "boolean",
        isExcluded: true,
      },
      repeated: {
        column: "qnaire.repeated",
        type: "string",
        isExcluded: true,
      },
      stages: {
        column: "qnaire.stages",
        type: "boolean",
        isExcluded: true,
      },
      current_response_id: {
        column: "response.id",
        type: "string",
        isExcluded: true,
      },
      qnaire_id: { column: "qnaire.id", type: "hidden", },
      lang: { column: "language.code", type: "hidden", },
      has_devices: { type: "hidden", },
      parent_beartooth_url: { column: "qnaire.parent_beartooth_url", type: "hidden", },
      parent_username: { column: "qnaire.parent_username", type: "hidden", },
    });

    module.addInputGroup(
      "Response",
      {
        qnaire_version: {
          column: "response.qnaire_version",
          title: "Questionnaire Version",
          type: "string",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        interview_type: {
          title: "Interview Type",
          type: "string",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              !model.viewModel.record.stages ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        language_id: {
          column: "response.language_id",
          title: "Language",
          type: "enum",
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        qnaire_progress: {
          title: "Progress",
          type: "string",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        checked_in: {
          column: "response.checked_in",
          title: "Checked In",
          type: "boolean",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id ||
              false == model.viewModel.record.stages
            );
          },
        },
        module: {
          column: "module.name",
          title: "Module",
          type: "string",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        page: {
          column: "page.name",
          title: "Page",
          type: "string",
          isConstant: true,
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
        comments: {
          column: "response.comments",
          title: "Comments",
          type: "text",
          isExcluded: function ($state, model) {
            return (
              "view" != model.getActionFromState() ||
              null != model.viewModel.record.repeated ||
              null == model.viewModel.record.current_response_id
            );
          },
        },
      },
      true
    );

    module.addExtraOperation("list", {
      title: "Get Respondents",
      operation: function ($state, model) {
        model.getRespondents();
      },
      isIncluded: function ($state, model) {
        if ("qnaire" == model.getSubjectFromState()) {
        }
        return "today" != model.subList && model.isDetached() && (
          "qnaire" != model.getSubjectFromState() || model.hasParentUsername
        );
      },
      isDisabled: function ($state, model) {
        return model.workInProgress;
      },
    });

    module.addExtraOperation("list", {
      title: "Export",
      operation: async function ($state, model) {
        await model.export();
      },
      isIncluded: function ($state, model) {
        return "today" != model.subList && model.isDetached() && (
          "qnaire" != model.getSubjectFromState() || model.hasParentUsername
        );
      },
      isDisabled: function ($state, model) {
        return model.workInProgress;
      },
    });

    module.addExtraOperation("list", {
      title: "Import",
      operation: async function ($state, model) {
        await $state.go("qnaire.import_responses", {
          identifier: $state.params.identifier,
        });
      },
      isIncluded: function ($state, model) {
        return "today" != model.subList && "qnaire" == model.getSubjectFromState() && !model.isDetached();
      },
    });

    module.addExtraOperation("list", {
      title: "Mass Respondent",
      operation: async function ($state, model) {
        await $state.go("qnaire.mass_respondent", {
          identifier: $state.params.identifier,
        });
      },
      isIncluded: function ($state, model) {
        return "today" != model.subList && "qnaire" == model.getSubjectFromState() && !model.isDetached();
      },
    });

    module.addExtraOperation("view", {
      title: "Export",
      operation: async function ($state, model) {
        await model.export(
          model.getIdentifierFromRecord(model.viewModel.record)
        );
      },
      isIncluded: function ($state, model) {
        return (
          model.isDetached() &&
          model.viewModel.record.parent_beartooth_url &&
          model.viewModel.record.parent_username &&
          null != model.viewModel.record.end_datetime &&
          "Exported" != model.viewModel.record.status
        );
      },
      isDisabled: function ($state, model) {
        return model.workInProgress;
      },
    });

    module.addExtraOperation("view", {
      title: "Re-Export",
      operation: async function ($state, model) {
        await model.export(
          model.getIdentifierFromRecord(model.viewModel.record)
        );
      },
      isIncluded: function ($state, model) {
        return (
          model.isDetached() &&
          model.viewModel.record.parent_beartooth_url &&
          model.viewModel.record.parent_username &&
          model.isRole("administrator") &&
          "Exported" == model.viewModel.record.status
        );
      },
      isDisabled: function ($state, model) {
        return model.workInProgress;
      },
    });

    module.addExtraOperation("view", {
      title: "Display",
      operation: async function ($state, model) {
        await $state.go("response.display", {
          identifier: model.viewModel.record.current_response_id,
        });
      },
      isIncluded: function ($state, model) {
        return null == model.viewModel.record.repeated;
      },
    });

    module.addExtraOperation("view", {
      title: "Download Report",
      operation: async function ($state, model) {
        await model.viewModel.downloadReport();
      },
      isDisabled: function ($state, model) { return model.viewModel.downloadingReport; },
      isIncluded: function ($state, model) {
        return (
          null != model.viewModel.qnaireReportTitles &&
          angular.isDefined( model.viewModel.qnaireReportTitles[model.viewModel.record.lang] )
        );
      },
    });

    module.addExtraOperation("view", {
      title: "Reopen",
      operation: async function ($state, model) {
        await model.viewModel.reopen();
      },
      isIncluded: function ($state, model) {
        return !model.viewModel.record.stages && null != model.viewModel.record.end_datetime;
      },
    });

    module.addExtraOperation("view", {
      title: "Update Attributes",
      operation: async function ($state, model) {
        await model.viewModel.updateAttributes();
      },
      isIncluded: function ($state, model) {
        return model.viewModel.record.has_attributes && null == model.viewModel.record.end_datetime;
      },
    });

    module.addExtraOperation("view", {
      title: "Launch",
      operation: async function ($state, model) {
        await $state.go("respondent.run", {
          token: model.viewModel.record.token,
          show_hidden: 1,
        });
      },
      isIncluded: function ($state, model) {
        return model.viewModel.record.stages || null == model.viewModel.record.end_datetime;
      },
    });

    module.addExtraOperation("view", {
      title: "Re-schedule Email",
      operation: async function ($state, model) {
        try {
          model.viewModel.resendMail();
        } finally {
          if (angular.isDefined(model.viewModel.respondentMailModel))
            await model.viewModel.respondentMailModel.listModel.onList(true);
        }
      },
      isIncluded: function ($state, model) {
        return model.viewModel.record.sends_mail;
      },
      help:
        "This will re-schedule all mail for this respondent. " +
        "This is useful if mail was never sent or if email settings have changed since email was last scheduled.",
    });

    /* ############################################################################################## */
    cenozo.providers.directive("cnRespondentRun", [
      "CnRespondentModelFactory",
      function (CnRespondentModelFactory) {
        return {
          templateUrl: module.getFileUrl("run.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnRespondentModelFactory.root;
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnRespondentAddFactory", [
      "CnBaseAddFactory",
      function (CnBaseAddFactory) {
        var object = function (parentModel) {
          CnBaseAddFactory.construct(this, parentModel);

          // transition to viewing the new record instead of the default functionality
          this.transitionOnSave = function (record) {
            parentModel.transitionToViewState(record);
          };
        };
        return {
          instance: function (parentModel) {
            return new object(parentModel);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnRespondentListFactory", [
      "CnBaseListFactory",
      "CnHttpFactory",
      function (CnBaseListFactory, CnHttpFactory) {
        var object = function (parentModel) {
          CnBaseListFactory.construct(this, parentModel);

          angular.extend(this, {
            onList: async function (replace) {
              await this.$$onList(replace);
              await this.parentModel.updateUsesParent();
            },
          });
        };
        return {
          instance: function (parentModel) {
            return new object(parentModel);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnRespondentViewFactory", [
      "CnBaseViewFactory",
      "CnHttpFactory",
      "CnModalConfirmFactory",
      function (CnBaseViewFactory, CnHttpFactory, CnModalConfirmFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);

          angular.extend(this, {
            qnaireReportTitles: {},

            downloadingReport: false,
            downloadReport: async function () {
              try {
                this.downloadingReport = true;
                await CnHttpFactory.instance({
                  path: ["response", this.record.current_response_id].join("/"),
                  format: "pdf",
                }).file();
              } finally {
                this.downloadingReport = false;
              }
            },

            onView: async function (force) {
              this.defaultTab = null;

              await this.$$onView(force);

              // get the qnaire's reports
              const response = await CnHttpFactory.instance({
                path: ["qnaire", this.record.qnaire_id, "qnaire_report"].join("/"),
                data: { select: { column: ['title', { table: 'language', column: 'code', alias: 'lang' }] } },
              }).query();
              this.qnaireReportTitles = {};
              response.data.forEach(report => {
                this.qnaireReportTitles[report.lang] = report.title;
              } );

              this.defaultTab = this.record.repeated
                ? "response"
                : this.record.stages
                ? "response_stage"
                : this.record.sends_mail
                ? "respondent_mail"
                : "response_attribute";
              if (!this.tab) this.tab = this.defaultTab;
            },

            getChildList: function () {
              return this.$$getChildList().filter( (child) =>
                // show the response list if the qnaire is answered more than once
                ("response" == child.subject.snake && null != this.record.repeated) ||
                // show mail list if the qnaire sends mail
                ("respondent_mail" == child.subject.snake && this.record.sends_mail) ||
                // show stage list if the qnaire has stages and the qnaire is only answered once
                (
                  "response_stage" == child.subject.snake &&
                  this.record.stages &&
                  null == this.record.repeated
                ) ||
                // show attribute list if the qnaire is only answered once
                ("response_attribute" == child.subject.snake && null == this.record.repeated) ||
                // show device list if the qnaire has devices and the qnaire is only answered once
                (
                  "answer_device" == child.subject.snake &&
                  this.record.has_devices &&
                  null == this.record.repeated
                )
              );
            },

            onPatch: async function (data) {
              // when patching response data make sure to send to the response service, not the respondent
              if (angular.isDefined(data.comments) || angular.isDefined(data.language_id)) {
                if (!this.parentModel.getEditEnabled())
                  throw new Error("Calling onPatch() but edit is not enabled.");

                var self = this;
                await CnHttpFactory.instance({
                  path: "response/" + this.record.current_response_id,
                  data: data,
                  onError: function (error) { self.onPatchError(error); },
                }).patch();
                this.afterPatchFunctions.forEach((fn) => fn());
              } else {
                await this.$$onPatch(data);
              }
            },

            reopen: async function () {
              await CnHttpFactory.instance({
                path:
                  this.parentModel.getServiceResourcePath() + "?action=reopen",
              }).patch();
              await this.parentModel.reloadState(true);
            },

            resendMail: async function () {
              await CnHttpFactory.instance({
                path: this.parentModel.getServiceResourcePath() + "?action=resend_mail",
              }).patch();
            },

            updateAttributes: async function () {
              const response = await CnModalConfirmFactory.instance({
                title: "Update Attributes",
                message:
                  "Are you sure you wish to replace all existing attributes? " +
                  "This should only be done if there is a problem with the existing " +
                  "attributes that needs to be corrected.",
              }).show();

              if (response) {
                await CnHttpFactory.instance({
                  path: this.parentModel.getServiceResourcePath() + "?action=update_attributes",
                }).patch();
                await this.parentModel.reloadState(true);
              }
            },
          });
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnRespondentModelFactory", [
      "CnBaseModelFactory",
      "CnRespondentAddFactory",
      "CnRespondentListFactory",
      "CnRespondentViewFactory",
      "CnModalMessageFactory",
      "CnSession",
      "CnHttpFactory",
      "$state",
      function (
        CnBaseModelFactory,
        CnRespondentAddFactory,
        CnRespondentListFactory,
        CnRespondentViewFactory,
        CnModalMessageFactory,
        CnSession,
        CnHttpFactory,
        $state
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);

          angular.extend(this, {
            addModel: CnRespondentAddFactory.instance(this),
            listModel: CnRespondentListFactory.instance(this),
            viewModel: CnRespondentViewFactory.instance(this, root),

            workInProgress: false,
            hasParentUsername: null,

            updateUsesParent: async function() {
              if (this.isDetached() &&
                  "qnaire" == this.getSubjectFromState() &&
                  "view" == this.getActionFromState()) {
                const response = await CnHttpFactory.instance({
                  path: 'qnaire/' + $state.params.identifier,
                  data: { select: { column: ["parent_beartooth_url", "parent_username"] } }
                }).get();

                this.hasParentUsername = (
                  null != response.data.parent_beartooth_url &&
                  null != response.data.parent_username
                );
              } else {
                this.hasParentUsername = null;
              }
            },

            isDetached: function () { return CnSession.setting.detached; },

            getAddEnabled: function() {
              return this.$$getAddEnabled() && "qnaire" == this.getSubjectFromState();
            },

            getMetadata: async function () {
              await this.$$getMetadata();

              var response = await CnHttpFactory.instance({
                path: "language",
                data: {
                  select: { column: ["id", "name"] },
                  modifier: {
                    where: { column: "active", operator: "=", value: true },
                    order: "name",
                    limit: 1000,
                  },
                },
              }).query();
              this.metadata.columnList.language_id = {
                enumList: response.data.reduce((list, item) => {
                  list.push({ value: item.id, name: item.name });
                  return list;
                }, []),
              };
            },

            getRespondents: async function () {
              var modal = CnModalMessageFactory.instance({
                title: "Communicating with Remote Server",
                message:
                  "Please wait while synchronizing with the parent server and retrieving the respondent list.",
                block: true,
              });
              modal.show();

              let errorList = [];
              try {
                this.workInProgress = true;
                const httpData = { path: "respondent?action=get_respondents" };
                if ("qnaire" == this.getSubjectFromState())
                  httpData.path = "qnaire/" + $state.params.identifier + "/" + httpData.path;

                const response = await CnHttpFactory.instance(httpData).post();

                // display errors if there are any
                errorList = response.data
                  .filter(result => 0 < result.fail.length)
                  .map(result => result.qnaire + ": " + result.fail.join(", "));
              } finally {
                modal.close();
                this.workInProgress = false;
              }

              if (0 < errorList.length) {
                await CnModalMessageFactory.instance({
                  title: "Problem Importing Respondents",
                  message:
                    "The following respondents could not be imported because there was a problem retrieving " +
                    "the mandatory attributes:\n\n" + errorList.join("\n"),
                  error: true,
                }).show();
              }

              await this.listModel.onList(true);
            },

            export: async function (respondentId) {
              var modal = CnModalMessageFactory.instance({
                title: "Communicating with Remote Server",
                message:
                  "Please wait while synchronizing with the parent server and exporting respondent data.",
                block: true,
              });
              modal.show();

              try {
                this.workInProgress = true;
                const httpData = {
                  path: "respondent" + (
                    angular.isDefined(respondentId) ? "/" + respondentId : ""
                  ) + "?action=export"
                };

                if ("qnaire" == this.getSubjectFromState())
                  httpData.path = "qnaire/" + $state.params.identifier + "/" + httpData.path;

                var http = CnHttpFactory.instance(httpData);
                var response = angular.isDefined(respondentId)
                  ? await http.patch()
                  : await http.post();

                CnModalMessageFactory.instance({
                  title: "Export Complete",
                  message: angular.isDefined(respondentId)
                    ? "The respondent has been exported."
                    : 0 < response.data.length
                    ? "The following respondents have been exported:\n\n" +
                      response.data.join(", ")
                    : "No respondents have been exported.",
                }).show();
              } finally {
                modal.close();
                this.workInProgress = false;
              }

              if (angular.isDefined(respondentId)) {
                await this.viewModel.onView(true);
              } else {
                await this.listModel.onList(true);
              }
            },
          });
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
