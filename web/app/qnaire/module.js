cenozoApp.defineModule({
  name: "qnaire",
  dependencies: "module",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {},
      name: {
        singular: "questionnaire",
        plural: "questionnaires",
        possessive: "questionnaire's",
      },
      columnList: {
        name: {
          column: "qnaire.name",
          title: "Name",
        },
        version: {
          title: "Version",
        },
        closed: {
          title: "Closed",
          type: "boolean",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        debug: {
          title: "Debug Mode",
          type: "boolean",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        readonly: {
          title: "Read-Only",
          type: "boolean",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        anonymous: {
          title: "Anonymous",
          type: "boolean",
        },
        stages: {
          title: "Use Stages",
          type: "boolean",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        repeat_detail: {
          title: "Repeated",
          type: "string",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        max_responses: {
          title: "Max Responses",
          type: "string",
          isIncluded: function ($state, model) {
            return !model.isRole("interviewer");
          },
        },
        module_count: {
          title: "Modules",
        },
        respondent_count: {
          title: "Participants",
        },
      },
      defaultOrder: {
        column: "qnaire.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
        format: "identifier",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      version: {
        title: "Version",
        type: "string",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      variable_suffix: {
        title: "Variable Suffix",
        type: "string",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      base_language_id: {
        title: "Base Language",
        type: "enum",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      average_time: {
        title: "Average Time",
        type: "string",
        isConstant: true,
        isExcluded: "add",
      },
      debug: {
        title: "Debug Mode",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      readonly: {
        title: "Read Only",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") ? true : "add";
        },
      },
      anonymous: {
        title: "Anonymous Respondents Allowed",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.viewModel.record.stages;
        },
      },
      anonymous_url: {
        title: "Anonymous URL",
        type: "string",
        isConstant: true,
        isExcluded: function ($state, model) {
          return !model.viewModel.record.anonymous ? true : "add";
        },
      },
      show_progress: {
        title: "Show Progress Bar",
        type: "boolean",
        help: "Whether to show the progress bar to the respondent.",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
      },
      allow_in_hold: {
        title: "Allow when in Hold",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") ? true : "add";
        },
        help: "Whether to allow participants who are in a final hold to answer the questionnaire.",
      },
      problem_report: {
        title: "Enable Problem Reports",
        type: "boolean",
        isExcluded: function ($state, model) { return model.isRole("interviewer"); },
        help: "Whether to enable the \"Report Problem\" button when running a questionnaire.",
      },
      attributes_mandatory: {
        title: "Attributes Mandatory",
        type: "boolean",
        isExcluded: function ($state, model) { return model.isRole("interviewer"); },
        help: "Whether to not allow a response to proceed if the attributes failed to load.",
      },
      stages: {
        title: "Stages",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.viewModel.record.anonymous || model.isRole("interviewer") ? true : "add";
        },
      },
      closed: {
        title: "Closed",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") ? true : "add";
        },
      },
      token_regex: {
        title: "Token Regex",
        type: "string",
        help:
          "A regular expression that restricts the format of tokens when in stage-mode.",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") ? true : "add";
        },
      },
      token_check: {
        title: "Token Check",
        type: "boolean",
        help:
          "Whether to check the token at check-in and before launching a stage.",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") ? true : "add";
        },
      },
      description: {
        title: "Description",
        type: "text",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      note: {
        title: "Note",
        type: "text",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      first_page_id: { isExcluded: true },
    });

    module.addInputGroup("Email Communication", {
      email_invitation: {
        title: "Send Invitation Email",
        type: "boolean",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      email_from_name: {
        title: "Email From Name",
        type: "string",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      email_from_address: {
        title: "Email From Address",
        type: "string",
        format: "email",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
    });

    module.addInputGroup("Repeated Questionnaires", {
      repeated: {
        title: "Repeated",
        type: "enum",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer");
        },
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
      },
      repeat_offset: {
        title: "Repeat Offset",
        type: "string",
        format: "integer",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
        isExcluded: function ($state, model) {
          return model.isRole("interviewer")
            ? true
            : !model.viewModel.record.repeated;
        },
      },
      max_responses: {
        title: "Maximum Number of Responses",
        type: "string",
        format: "integer",
        isConstant: function ($state, model) {
          return model.viewModel.record.readonly;
        },
        isExcluded: function ($state, model) {
          return model.isRole("interviewer")
            ? true
            : !model.viewModel.record.repeated;
        },
        help: "If set to 0 then there will be no maximum number of responses",
      },
    });

    module.addInputGroup("Detached Settings", {
      parent_beartooth_url: {
        title: "Parent Beartooth URL",
        type: "string",
        help: "The base Beartooth URL to fetch appointments from.<br/>\n" +
              "WARNING: this information is never included in the import/export process. It must be set " +
              "in every instance independently!",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") || !model.isDetached();
        },
      },
      parent_username: {
        title: "Parent Username",
        type: "string",
        help: "The parent server's interviewing instance username.<br/>\n" +
              "WARNING: this information is never included in the import/export process. It must be set " +
              "in every instance independently!",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") || !model.isDetached();
        },
      },
      parent_password: {
        title: "Parent Password",
        type: "string",
        help: "The parent server's interviewing instance password.<br/>\n" +
              "WARNING: this information is never included in the import/export process. It must be set " +
              "in every instance independently!",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") || !model.isDetached();
        },
      },
      appointment_type: {
        title: "Appointment Type",
        type: "string",
        help: "This can be used to restrict the list of appointments from Beartooth by appointment type.<br/>\n" +
              "WARNING: this information is never included in the import/export process. It must be set " +
              "in every instance independently!",
        isExcluded: function ($state, model) {
          return model.isRole("interviewer") || !model.isDetached();
        },
      },
    });

    module.addExtraOperation("list", {
      title: "Import",
      operation: async function ($state, model) {
        await $state.go("qnaire.import");
      },
      isIncluded: function ($state, model) {
        return model.getEditEnabled();
      },
    });

    module.addExtraOperation("view", {
      title: "Preview",
      isDisabled: function ($state, model) {
        return !model.viewModel.record.first_page_id;
      },
      isIncluded: function ($state, model) {
        return !model.isRole("interviewer");
      },
      operation: async function ($state, model) {
        await $state.go(
          "page.render",
          { identifier: model.viewModel.record.first_page_id },
          { reload: true }
        );
      },
    });

    module.addExtraOperation("view", {
      title: "Export",
      operation: async function ($state, model) {
        await $state.go("qnaire.clone", {
          identifier: model.viewModel.record.getIdentifier(),
        });
      },
      isIncluded: function ($state, model) {
        return model.getEditEnabled();
      },
    });

    module.addExtraOperation("view", {
      title: "Patch",
      operation: async function ($state, model) {
        await $state.go("qnaire.patch", {
          identifier: model.viewModel.record.getIdentifier(),
        });
      },
      isIncluded: function ($state, model) {
        return model.getEditEnabled();
      },
    });

    module.addExtraOperation("view", {
      title: "Test Connection",
      isIncluded: function ($state, model) {
        return (
          model.viewModel.record.parent_beartooth_url &&
          model.viewModel.record.parent_username &&
          model.isDetached()
        );
      },
      operation: function ($state, model) {
        model.viewModel.testConnection();
      },
    });

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireAdd", [
      "CnQnaireModelFactory",
      function (CnQnaireModelFactory) {
        return {
          templateUrl: module.getFileUrl("add.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireModelFactory.root;

            // a special function to define whether to show certain inputs based on the repeated property
            function defineRepeatedExcludes() {
              var repeatedInputGroup =
                $scope.model.module.inputGroupList.findByProperty("title", "Repeated Questionnaires");

              repeatedInputGroup.inputList.repeat_offset.isExcluded = function ($state, model) {
                return !("add" == model.getActionFromState()
                  ? cnRecordAddScope.record.repeated
                  : model.viewModel.record.repeated);
              };

              repeatedInputGroup.inputList.max_responses.isExcluded = function ($state, model) {
                return !("add" == model.getActionFromState()
                  ? cnRecordAddScope.record.repeated
                  : model.viewModel.record.repeated);
              };
            }

            var cnRecordAddScope = null;
            $scope.$on(
              "cnRecordAdd ready",
              function (event, data) {
                cnRecordAddScope = data;

                // add/remove inputs based on whether repeated is set to true or false
                var checkFunction = cnRecordAddScope.check;
                cnRecordAddScope.check = function (property) {
                  // run the original check function first
                  checkFunction(property);
                  if ("repeated" == property) defineRepeatedExcludes();
                };

                defineRepeatedExcludes();
              },
              500
            );
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireClone", [
      "CnQnaireCloneFactory",
      "CnSession",
      "$state",
      function (CnQnaireCloneFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("clone.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireCloneFactory.instance();

            await $scope.model.onLoad();

            CnSession.setBreadcrumbTrail([
              {
                title: "Questionnaires",
                go: async function () {
                  await $state.go("qnaire.list");
                },
              },
              {
                title: $scope.model.sourceName,
                go: async function () {
                  await $state.go("qnaire.view", {
                    identifier: $scope.model.parentQnaireId,
                  });
                },
              },
              {
                title: "Export",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireImport", [
      "CnQnaireImportFactory",
      "CnSession",
      "$state",
      function (CnQnaireImportFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("import.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireImportFactory.instance();

            CnSession.setBreadcrumbTrail([
              {
                title: "Questionnaires",
                go: async function () {
                  await $state.go("qnaire.list");
                },
              },
              {
                title: "Import",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireImportResponses", [
      "CnQnaireImportResponsesFactory",
      "CnSession",
      "$state",
      function (CnQnaireImportResponsesFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("import_responses.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireImportResponsesFactory.instance();

            await $scope.model.onLoad();

            CnSession.setBreadcrumbTrail([
              {
                title: "Questionnaires",
                go: async function () {
                  await $state.go("qnaire.list");
                },
              },
              {
                title: $scope.model.qnaireName,
                go: async function () {
                  await $state.go("qnaire.view", {
                    identifier: $scope.model.qnaireId,
                  });
                },
              },
              {
                title: "Import Responses",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireMassRespondent", [
      "CnQnaireMassRespondentFactory",
      "CnSession",
      "$state",
      function (CnQnaireMassRespondentFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("mass_respondent.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireMassRespondentFactory.instance();

            await $scope.model.onLoad();

            CnSession.setBreadcrumbTrail([
              {
                title: "Questionnaires",
                go: async function () {
                  await $state.go("qnaire.list");
                },
              },
              {
                title: $scope.model.qnaireName,
                go: async function () {
                  await $state.go("qnaire.view", {
                    identifier: $scope.model.qnaireId,
                  });
                },
              },
              {
                title: "Mass Respondent",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnairePatch", [
      "CnQnaireModelFactory",
      "CnSession",
      "$state",
      function (CnQnaireModelFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("patch.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireModelFactory.root;

            await $scope.model.viewModel.onView();

            CnSession.setBreadcrumbTrail([
              {
                title: "Questionnaires",
                go: async function () {
                  await $state.go("qnaire.list");
                },
              },
              {
                title: $scope.model.viewModel.record.name,
                go: async function () {
                  await $state.go("qnaire.view", {
                    identifier: $scope.model.viewModel.record.getIdentifier(),
                  });
                },
              },
              {
                title: "Patch",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQnaireView", [
      "CnQnaireModelFactory",
      "CnModalConfirmFactory",
      function (CnQnaireModelFactory, CnModalConfirmFactory) {
        return {
          templateUrl: module.getFileUrl("view.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnQnaireModelFactory.root;

            $scope.$on("cnRecordView ready", function (event, data) {
              var cnRecordViewScope = data;
              cnRecordViewScope.basePatchFn = cnRecordViewScope.patch;

              var mainInputGroup =
                $scope.model.module.inputGroupList.findByProperty("title", "");

              mainInputGroup.inputList.token_regex.isExcluded = function ($state, model) {
                return "add" == model.getActionFromState() || !model.viewModel.record.stages;
              };

              mainInputGroup.inputList.token_check.isExcluded = function ($state, model) {
                return "add" == model.getActionFromState() || !model.viewModel.record.stages;
              };

              cnRecordViewScope.patch = async function (property) {
                var proceed = true;

                // warn that stages/deveiation-types will be deleted when switching to non-stages mode
                if (
                  "stages" == property &&
                  !$scope.model.viewModel.record.stages
                ) {
                  var response = await CnModalConfirmFactory.instance({
                    message:
                      "Turning off stages mode will automatically delete all stages and deviation types. " +
                      "Are you sure you wish to proceed?",
                  }).show();
                  if (!response) {
                    // undo the change
                    $scope.model.viewModel.record.stages = true;
                    proceed = false;
                  }
                }

                if (proceed) {
                  await cnRecordViewScope.basePatchFn(property);

                  // after changing the state always reload the page (due to changes in UI)
                  if ("stages" == property) $scope.model.reloadState(true);
                }
              };
            });
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireCloneFactory", [
      "CnQnaireModelFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      "$state",
      function (CnQnaireModelFactory, CnHttpFactory, CnModalMessageFactory, $state) {
        var object = function () {
          angular.extend(this, {
            parentModel: CnQnaireModelFactory.root,
            parentQnaireId: $state.params.identifier,
            sourceName: null,
            working: false,
            operation: "clone",
            name: null,
            nameConflict: false,

            onLoad: async function () {
              // reset data
              this.name = null;
              this.nameConflict = false;
              var response = await CnHttpFactory.instance({
                path: "qnaire/" + this.parentQnaireId,
                data: { select: { column: "name" } },
              }).get();
              this.sourceName = response.data.name;
            },
            isComplete: function () {
              return (
                !this.working &&
                !this.nameConflict &&
                (null != this.name || "clone" != this.operation)
              );
            },
            cancel: async function () {
              await $state.go("qnaire.view", { identifier: this.parentQnaireId });
            },

            save: async function () {
              var self = this;
              var httpObj = {
                onError: function (error) {
                  if (409 == error.status) self.nameConflict = true;
                  else CnModalMessageFactory.httpError(error);
                },
              };

              if ("clone" == this.operation) {
                httpObj.path = "qnaire?clone=" + this.parentQnaireId;
                httpObj.data = { name: this.name };
              } else if ("export" == this.operation) {
                httpObj.path =
                  "qnaire/" + this.parentQnaireId + "?output=export";
                httpObj.format = "txt";
              } else if ("print" == this.operation) {
                httpObj.path =
                  "qnaire/" + this.parentQnaireId + "?output=print";
                httpObj.format = "txt";
              }

              try {
                this.working = true;
                var http = await CnHttpFactory.instance(httpObj);
                var response = await ("clone" == this.operation
                  ? http.post()
                  : http.file());
                if ("clone" == this.operation)
                  await $state.go("qnaire.view", { identifier: response.data });
              } finally {
                this.working = false;
              }
            },
          });
        };
        return {
          instance: function () {
            return new object();
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireImportFactory", [
      "CnQnaireModelFactory",
      "CnHttpFactory",
      "$state",
      function (CnQnaireModelFactory, CnHttpFactory, $state) {
        var object = function () {
          angular.extend(this, {
            parentModel: CnQnaireModelFactory.root,
            working: false,
            file: null,

            cancel: async function () {
              await $state.go("qnaire.list");
            },

            import: async function () {
              var data = new FormData();
              data.append("file", this.file);

              try {
                this.working = true;
                var response = await CnHttpFactory.instance({
                  path: "qnaire?import=1",
                  data: this.file,
                }).post();
                await $state.go("qnaire.view", { identifier: response.data });
              } finally {
                this.working = false;
              }
            },
          });
        };
        return {
          instance: function () {
            return new object();
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireImportResponsesFactory", [
      "CnQnaireModelFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      "CnModalConfirmFactory",
      "CnModalInputFactory",
      "$rootScope",
      "$state",
      function (
        CnQnaireModelFactory,
        CnHttpFactory,
        CnModalMessageFactory,
        CnModalConfirmFactory,
        CnModalInputFactory,
        $rootScope,
        $state
      ) {
        var object = function () {
          const self = this;
          angular.extend(this, {
            parentModel: CnQnaireModelFactory.root,
            working: false,
            qnaireId: $state.params.identifier,
            qnaireName: null,
            file: null,
            confirmData: null,

            onLoad: async function () {
              angular.extend(this, {
                qnaireName: '',
                working: false,
                file: null,
                confirmData: null,
              });

              // reset data
              var response = await CnHttpFactory.instance({
                path: "qnaire/" + this.qnaireId,
                data: { select: { column: "name" } },
              }).get();

              this.qnaireName = response.data.name;
            },

            navigateToDataDictionaryReport: async function() {
              await $state.go("report_type.view", { identifier: "name=annotation" });
            },

            checkImport: async function () {
              // need to wait for cnUpload to do its thing
              const removeFn = $rootScope.$on("cnUpload read", async () => {
                removeFn(); // only run once
                try {
                  this.working = true;
                  var data = new FormData();
                  data.append("file", this.file);

                  // check the patch file
                  var response = await CnHttpFactory.instance({
                    path: ["qnaire", this.qnaireId, "response"].join("/") + "?mode=confirm",
                    data: this.file,
                    onError: async function (error) {
                      await CnModalMessageFactory.httpError(error);
                      self.onLoad();
                    },
                  }).post();
                  this.confirmData = response.data;
                } finally {
                  this.working = false;
                }
              });
            },

            import: async function () {
              // do nothing if we don't have valid data
              if(
                null == this.confirmData ||
                0 == this.confirmData.valid_column_list.length ||
                0 < this.confirmData.column_errors
              ) {
                console.warn( "Tried to import invalid data." );
                return;
              }

              let importMode = false;
              if(0 < this.confirmData.existing_responses && 0 < this.confirmData.new_responses) {
                // There are both new and existing responses
                const response = await CnModalInputFactory.instance({
                  message:
                    "Please specify whether you would like to import all responses or " +
                    "only new responses that do not already exist in the database:",
                  format: "enum",
                  enumList: [
                    { value: "import", name: "All Responses" },
                    { value: "import_new", name: "New Responses Only" },
                  ],
                  value: "import",
                }).show();
                importMode = response;
              } else {
                // There are only new or existing responses
                const response = await CnModalConfirmFactory.instance({
                  message: "Are you sure you wish to import all responses?"
                }).show();
                importMode = response ? "import" : false;
              }

              if(false !== importMode) {
                try {
                  var data = new FormData();
                  data.append("file", this.file);

                  var response = await CnHttpFactory.instance({
                    path: ["qnaire", this.qnaireId, "response"].join("/") + "?mode=" + importMode,
                    data: this.file,
                    onError: async function (error) {
                      await CnModalMessageFactory.httpError(error);
                      self.onLoad();
                    },
                  }).post();

                  if(response) {
                    let messages = [];
                    if(0 < this.confirmData.new_responses) {
                      messages.push(
                        this.confirmData.new_responses + " new response" +
                        ( 1 == this.confirmData.new_responses ?  " has" : "s have" ) + 
                        " been imported"
                      );
                    }
                    if(0 < this.confirmData.existing_responses) {
                      messages.push(
                        this.confirmData.existing_responses + " existing response" +
                        ( 1 == this.confirmData.existing_responses ? " has" : "s have" ) +
                        " been overwritten"
                      );
                    }

                    await CnModalMessageFactory.instance({
                      title: "Import Complete",
                      message: messages.join( " and " )
                    }).show();
                    await $state.go("qnaire.view", { identifier: this.qnaireId });
                  }
                } finally {
                  this.working = false;
                }
              }
            },
          });
        };
        return {
          instance: function () {
            return new object();
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireMassRespondentFactory", [
      "CnQnaireModelFactory",
      "CnSession",
      "CnHttpFactory",
      "CnModalMessageFactory",
      "CnParticipantSelectionFactory",
      "$state",
      function (
        CnQnaireModelFactory,
        CnSession,
        CnHttpFactory,
        CnModalMessageFactory,
        CnParticipantSelectionFactory,
        $state
      ) {
        var object = function () {
          angular.extend(this, {
            parentModel: CnQnaireModelFactory.root,
            working: false,
            participantSelection: CnParticipantSelectionFactory.instance({
              path: ["qnaire", $state.params.identifier, "participant"].join(
                "/"
              ),
              data: { mode: "confirm" },
            }),
            qnaireId: $state.params.identifier,
            qnaireName: null,

            onLoad: async function () {
              // reset data
              var response = await CnHttpFactory.instance({
                path: "qnaire/" + this.qnaireId,
                data: { select: { column: "name" } },
              }).get();

              this.qnaireName = response.data.name;
              this.participantSelection.reset();
            },

            proceed: async function () {
              if (
                !this.participantSelection.confirmInProgress &&
                0 < this.participantSelection.confirmedCount
              ) {
                try {
                  this.working = true;
                  var self = this;
                  var response = await CnHttpFactory.instance({
                    path: ["qnaire", this.qnaireId, "participant"].join("/"),
                    data: {
                      mode: "create",
                      identifier_id: this.participantSelection.identifierId,
                      identifier_list:
                        this.participantSelection.getIdentifierList(),
                    },
                    onError: async function (error) {
                      await CnModalMessageFactory.httpError(error);
                      self.onLoad();
                    },
                  }).post();

                  let message = 
                    "You have successfully created " + response.data.success.length + " out of " +
                    this.participantSelection.confirmedCount + ' new recipients for the "' +
                    this.qnaireName + '" questionnaire.';

                  if (0 < response.data.fail.length) {
                    message +=
                      "\n\nNote that the following recipients were not created due to errors:\n" +
                      response.data.fail.join(", ");
                  }

                  await CnModalMessageFactory.instance({
                    title: "Respondents Created",
                    message: message,
                  }).show();
                  await this.onLoad();
                } finally {
                  this.working = false;
                }
              }
            },
          });
        };
        return {
          instance: function () {
            return new object();
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireViewFactory", [
      "CnBaseViewFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      "$filter",
      "$state",
      "$rootScope",
      function (
        CnBaseViewFactory,
        CnHttpFactory,
        CnModalMessageFactory,
        $filter,
        $state,
        $rootScope
      ) {
        var object = function (parentModel, root) {
          // the respondent only has one list (respondent list) so the default tab for them is null
          CnBaseViewFactory.construct(
            this,
            parentModel,
            root,
            "respondent"
          );

          angular.extend(this, {
            working: false,
            file: null,
            difference: null,
            differenceIsEmpty: false,

            // only show stage and deviation types in qnaires with stages and only show the respondent list to respondents
            getChildList: function () {
              var stagesChildList = [
                "stage",
                "deviation_type",
                "device",
                "qnaire_consent_type_confirm",
              ];
              return this.$$getChildList().filter(
                (child) => (
                  this.record.stages || !stagesChildList.includes(child.subject.snake)
                ) && (
                  !this.parentModel.isRole("interviewer") ||
                  ["qnaire_document","respondent"].includes(child.subject.snake)
                )
              );
            },

            onView: async function (force) {
              await this.$$onView(force);

              if (this.parentModel.isDetached() && angular.isDefined(this.respondentModel)) {
                await this.respondentModel.updateUsesParent();
              }

              this.record.average_time = $filter("cnSeconds")(
                Math.round(this.record.average_time)
              );
              this.working = false;
              this.file = null;
              this.difference = null;
              this.differenceIsEmpty = false;

              // make some columns dependent on the parent qnaire
              var self = this;
              var respondentModule = cenozoApp.module("respondent");
              respondentModule.columnList.interview_type.isIncluded =
                function ($state, model) { return self.record.stages && !self.record.repeated; };
              respondentModule.columnList.language.isIncluded =
                function ($state, model) { return !self.record.repeated; };
              respondentModule.columnList.response_count.isIncluded =
                function ($state, model) { return self.record.repeated; };
              respondentModule.columnList.qnaire_progress.isIncluded =
                function ($state, model) { return !self.record.repeated; };
            },

            onPatch: async function (data) {
              await this.$$onPatch(data);
              
              if (angular.isDefined(data.repeated) && data.repeated) {
                await this.onView();
              }
              
              if (
                angular.isDefined(data.parent_beartooth_url) &&
                angular.isDefined(data.parent_username) && 
                angular.isDefined(this.respondentModel)
              ){
                await this.respondentModel.updateUsesParent();
              }
            },

            cancel: async function () {
              await $state.go("qnaire.view", {
                identifier: this.record.getIdentifier(),
              });
            },

            checkPatch: function () {
              // need to wait for cnUpload to do its thing
              const removeFn = $rootScope.$on("cnUpload read", async () => {
                removeFn(); // only run once
                try {
                  this.working = true;
                  var data = new FormData();
                  data.append("file", this.file);

                  // check the patch file
                  var response = await CnHttpFactory.instance({
                    path:
                      this.parentModel.getServiceResourcePath() +
                      "?patch=check",
                    data: this.file,
                  }).patch();

                  this.difference = response.data;
                  this.differenceIsEmpty =
                    0 == Object.keys(this.difference).length;
                } finally {
                  this.working = false;
                }
              });
            },

            applyPatch: async function () {
              try {
                // apply the patch file
                this.working = true;
                await CnHttpFactory.instance({
                  path:
                    this.parentModel.getServiceResourcePath() + "?patch=apply",
                  data: this.file,
                }).patch();
                await $state.go("qnaire.view", {
                  identifier: this.record.getIdentifier(),
                });
              } finally {
                this.working = false;
              }
            },

            testConnection: async function () {
              var response = await CnHttpFactory.instance({
                path:
                  this.parentModel.getServiceResourcePath() +
                  "?test_connection=1",
              }).get();

              await CnModalMessageFactory.instance({
                title: "Test Connection",
                message: response.data,
              }).show();
            },
          });

          async function init(object) {
            await object.deferred.promise;

            if (angular.isDefined(object.moduleModel)) {
              object.moduleModel.getAddEnabled = function () {
                return (
                  !object.record.readonly &&
                  object.moduleModel.$$getAddEnabled()
                );
              };
              object.moduleModel.getDeleteEnabled = function () {
                return (
                  !object.record.readonly &&
                  object.moduleModel.$$getDeleteEnabled()
                );
              };
            }

            if (angular.isDefined(object.attributeModel)) {
              object.attributeModel.getAddEnabled = function () {
                return (
                  !object.record.readonly &&
                  object.attributeModel.$$getAddEnabled()
                );
              };
              object.attributeModel.getDeleteEnabled = function () {
                return (
                  !object.record.readonly &&
                  object.attributeModel.$$getDeleteEnabled()
                );
              };
            }

            if (angular.isDefined(object.embeddedFileModel)) {
              object.embeddedFileModel.getAddEnabled = function () {
                return (
                  !object.record.readonly && object.embeddedFileModel.$$getAddEnabled()
                );
              };
              object.embeddedFileModel.getDeleteEnabled = function () {
                return (
                  !object.record.readonly &&
                  object.embeddedFileModel.$$getDeleteEnabled()
                );
              };
            }
          }

          init(this);
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireModelFactory", [
      "CnBaseModelFactory",
      "CnQnaireAddFactory",
      "CnQnaireListFactory",
      "CnQnaireViewFactory",
      "CnHttpFactory",
      "CnSession",
      function (
        CnBaseModelFactory,
        CnQnaireAddFactory,
        CnQnaireListFactory,
        CnQnaireViewFactory,
        CnHttpFactory,
        CnSession
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.addModel = CnQnaireAddFactory.instance(this);
          this.listModel = CnQnaireListFactory.instance(this);
          this.viewModel = CnQnaireViewFactory.instance(this, root);

          angular.extend(this, {
            getBreadcrumbTitle: function () {
              return this.viewModel.record.name;
            },

            isDetached: function () {
              return CnSession.setting.detached;
            },

            // override the service collection path so that respondents can view the qnaire list from the home screen
            getServiceCollectionPath: function () {
              // ignore the parent if it is root
              return this.$$getServiceCollectionPath(
                "root" == this.getSubjectFromState()
              );
            },

            // extend getMetadata
            getMetadata: async function () {
              await this.$$getMetadata();

              var response = await CnHttpFactory.instance({
                path: "language",
                data: {
                  select: { column: ["id", "name", "code"] },
                  modifier: {
                    where: { column: "active", operator: "=", value: true },
                    order: "name",
                    limit: 1000,
                  },
                },
              }).query();

              this.metadata.columnList.base_language_id.enumList =
                response.data.reduce((list, item) => {
                  list.push({
                    value: item.id,
                    name: item.name,
                    code: item.code,
                  }); // code is needed by the withdraw action
                  return list;
                }, []);
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
