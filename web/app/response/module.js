cenozoApp.defineModule({
  name: "response",
  models: ["add", "list", "view"],
  defaultTab: "attribute",
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "respondent",
          column: "respondent.id",
        },
      },
      name: {
        singular: "response",
        plural: "responses",
        possessive: "response's",
      },
      columnList: {
        rank: {
          column: "response.rank",
          title: "Rank",
          type: "rank",
        },
        qnaire_version: {
          title: "Version",
        },
        interview_type: {
          title: "Type",
        },
        language: {
          column: "language.code",
          title: "Language",
        },
        submitted: {
          title: "Submitted",
          type: "boolean",
        },
        checked_in: {
          title: "Checked In",
          type: "boolean",
        },
        page_progress: {
          title: "Progress",
        },
        module: {
          column: "module.name",
          title: "Module",
        },
        page: {
          column: "page.name",
          title: "Page",
        },
        time_spent: {
          title: "Time Spent",
          type: "seconds",
        },
        start_datetime: {
          title: "Start",
          type: "datetime",
        },
        last_datetime: {
          title: "Last",
          type: "datetime",
        },
      },
      defaultOrder: {
        column: "start_datetime",
        reverse: true,
      },
    });

    module.addInputGroup("", {
      uid: {
        column: "participant.uid",
        title: "Participant",
        type: "string",
        isConstant: true,
      },
      rank: {
        title: "rank",
        type: "rank",
        isConstant: true,
      },
      qnaire_version: {
        title: "Questionnaire Version",
        type: "string",
        isConstant: true,
      },
      interview_type: {
        title: "Interview Type",
        type: "string",
        isConstant: true,
        isExcluded: function ($state, model) {
          return "view" != model.getActionFromState() || !model.viewModel.record.stages;
        },
      },
      language_id: {
        column: "response.language_id",
        title: "Language",
        type: "enum",
      },
      submitted: {
        title: "Submitted",
        type: "boolean",
        isConstant: true,
      },
      checked_in: {
        title: "Checked In",
        type: "boolean",
        isConstant: true,
      },
      page_progress: {
        title: "Page Progress",
        type: "string",
        isConstant: true,
      },
      module: {
        column: "module.name",
        title: "Module",
        type: "string",
        isConstant: true,
      },
      page: {
        column: "page.name",
        title: "Page",
        type: "string",
        isConstant: true,
      },
      start_datetime: {
        title: "Start Date & Time",
        type: "datetime",
        isConstant: true,
      },
      last_datetime: {
        title: "Last Date & Time",
        type: "datetime",
        isConstant: true,
      },
      comments: {
        title: "Comments",
        type: "text",
      },
      page_id: { isExcluded: true },
      qnaire_id: { column: "qnaire.id", isExcluded: true },
      lang: { column: "language.code", isExcluded: true },
      respondent_id: { column: "respondent.id", isExcluded: true },
      has_devices: { type: "hidden", },
    });

    module.addExtraOperation("view", {
      title: "Display",
      operation: async function ($state, model) {
        await $state.go("response.display", {
          identifier: model.viewModel.record.getIdentifier(),
        });
      },
    });

    /* ############################################################################################## */
    cenozo.providers.directive("cnResponseDisplay", [
      "CnResponseModelFactory",
      "CnSession",
      "$state",
      function (CnResponseModelFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("display.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnResponseModelFactory.root;

            await $scope.model.displayModel.onLoad();

            CnSession.setBreadcrumbTrail([
              {
                title: "Respondent",
                go: async function () {
                  await $state.go("respondent.list");
                },
              },
              {
                title: $scope.model.displayModel.respondent_id,
                go: async function () {
                  await $state.go("respondent.view", {
                    identifier: $scope.model.displayModel.respondent_id,
                  });
                },
              },
              {
                title: "Responses",
              },
              {
                title: $scope.model.displayModel.rank,
                go: async function () {
                  await $state.go("response.view", {
                    identifier: $scope.model.displayModel.response_id,
                  });
                },
              },
              {
                title: "display",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnResponseDisplayFactory", [
      "CnHttpFactory",
      "CnTranslationHelper",
      "$state",
      "$sce",
      function (CnHttpFactory, CnTranslationHelper, $state, $sce) {
        var object = function (parentModel) {
          angular.extend(this, {
            parentModel: parentModel,
            isDataLoading: false,
            dataList: [],
            onLoad: async function () {
              angular.extend(this, {
                isDataLoading: true,
                dataList: [],
                response_id: null,
                respondent_id: null,
                rank: null,
                qnaire_id: null,
              });

              // make sure the identifier is valid
              const identifier = this.parentModel.getQueryParameter("identifier");
              if( !identifier ) {
                $state.go("error.404");
                return;
              }

              // get general information about the response (for the breadcrumb trail)
              try {
                var response = await CnHttpFactory.instance({
                  path:
                    "response/" +
                    this.parentModel.getQueryParameter("identifier"),
                  data: {
                    select: {
                      column: [
                        "respondent_id",
                        "rank",
                        { table: "respondent", column: "qnaire_id" },
                      ],
                    },
                  },
                  onError: async function (error) {
                    
                  },
                }).get();

                angular.extend(this, {
                  response_id: response.data.id,
                  respondent_id: response.data.respondent_id,
                  rank: response.data.rank,
                  qnaire_id: response.data.qnaire_id,
                });

                // get a list of all response data
                var response = await CnHttpFactory.instance({
                  path: [
                    "response",
                    this.parentModel.getQueryParameter("identifier"),
                    "question",
                  ].join("/"),
                }).query();

                this.dataList = response.data;
                this.dataList.forEach( module => {
                  module.page_list.forEach( page => {
                    page.question_list.forEach( question => {
                      if( "audio" == question.type ) {
                        // the recording is stored in the file property as a base64 audio string
                        question.answer = $sce.trustAsHtml(
                          '<audio controls class="full-width" style="height: 40px;" src="' +
                          question.file + '"></audio>'
                        );
                      } else if( "list" == question.type ) {
                        question.isString = angular.isString(question.answer);
                      }
                    })
                  })
                });
              } finally {
                this.isDataLoading = false;
              }
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
    cenozo.providers.factory("CnResponseViewFactory", [
      "CnBaseViewFactory",
      function (CnBaseViewFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(
            this,
            parentModel,
            root,
            "response_attribute"
          );

          this.getChildList = function () {
            return this.$$getChildList().filter( (child) =>
              (!["response_stage", "answer_device"].includes(child.subject.snake)) ||
              // show stage list if the qnaire has stages
              ("response_stage" == child.subject.snake && this.record.stages) ||
              // show device list if the qnaire has devices and the qnaire is only answered once
              ("answer_device" == child.subject.snake && this.record.has_devices)
            );
          };
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnResponseModelFactory", [
      "CnBaseModelFactory",
      "CnResponseDisplayFactory",
      "CnResponseListFactory",
      "CnResponseViewFactory",
      "CnHttpFactory",
      function (
        CnBaseModelFactory,
        CnResponseDisplayFactory,
        CnResponseListFactory,
        CnResponseViewFactory,
        CnHttpFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.displayModel = CnResponseDisplayFactory.instance(this);
          this.listModel = CnResponseListFactory.instance(this);
          this.viewModel = CnResponseViewFactory.instance(this, root);

          this.getMetadata = async function () {
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

            this.metadata.columnList.language_id.enumList =
              response.data.reduce((list, item) => {
                list.push({ value: item.id, name: item.name });
                return list;
              }, []);
          };
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
