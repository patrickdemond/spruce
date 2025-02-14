cenozoApp.defineModule({
  name: "question",
  dependencies: "question_option",
  models: ["add", "list", "view"],
  create: (module) => {
    cenozoApp.initQnairePartModule(module, "question");

    module.identifier.parent = {
      subject: "page",
      column: "page.id",
    };

    // The column list is different when looking at a qnaire's list of questions
    angular.extend(module.columnList, {
      module_name: {
        column: "module.name",
        title: "Module",
        isIncluded: function ($state, model) {
          return "qnaire" == model.getSubjectFromState();
        },
      },
      page_name: {
        column: "page.name",
        title: "Page",
        isIncluded: function ($state, model) {
          return "qnaire" == model.getSubjectFromState();
        },
      },
      question_name: {
        column: "question.name",
        title: "Question",
        isIncluded: function ($state, model) {
          return "qnaire" == model.getSubjectFromState();
        },
      },
      type: { title: "Type" },
      export: { title: "Exported", type: "boolean" },
    });

    module.columnList.rank.isIncluded = function ($state, model) {
      return "qnaire" != model.getSubjectFromState();
    };
    module.columnList.name.isIncluded = function ($state, model) {
      return "qnaire" != model.getSubjectFromState();
    };
    module.columnList.question_option_count.isIncluded = function (
      $state,
      model
    ) {
      return "qnaire" != model.getSubjectFromState();
    };
    module.columnList.precondition.isIncluded = function ($state, model) {
      return "qnaire" != model.getSubjectFromState();
    };

    module.addInput("", "type", { title: "Type", type: "enum" });
    module.addInput("", "export", {
      title: "Export",
      type: "boolean",
      help: "Whether answers to this question are exported.",
    });
    module.addInput("", "dkna_allowed", {
      title: "Allow DKNA",
      type: "boolean",
      isExcluded: function ($state, model) {
        return "comment" == model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "refuse_allowed", {
      title: "Allow Refuse",
      type: "boolean",
      isExcluded: function ($state, model) {
        return "comment" == model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "device_id", {
      title: "Device",
      type: "enum",
      isExcluded: function ($state, model) {
        return "device" != model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "equipment_type_id", {
      title: "Equipment Type",
      type: "enum",
      isExcluded: function ($state, model) {
        return "equipment" != model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "lookup_id", {
      title: "Lookup",
      type: "enum",
      isExcluded: function ($state, model) {
        return "lookup" != model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "unit_list", {
      title: "Unit List",
      type: "text",
      help:
        'Must be defined in JSON format.  For example:<br>\n' +
        '[ "mg", "IU" ]<br>\n' +
        'or<br>\n' +
        '[ { "MG": "mg" }, { "IU": { "en": "IU", "fr": "U. I." } } ]<br>\n' +
        'or<br>\n' +
        '{ "MG": "mg", "IU": { "en": "IU", "fr": "U. I." } }',
      isExcluded: function ($state, model) {
        return "number with unit" != model.viewModel.record.type ? true : "add";
      },
    });
    module.addInput("", "minimum", {
      title: "Minimum",
      type: "string",
      isExcluded: function ($state, model) {
        return !["date", "number", "number with unit"].includes(model.viewModel.record.type)
          ? true
          : "add";
      },
      help: "The minimum possible value for this question.",
    });
    module.addInput("", "maximum", {
      title: "Maximum",
      type: "string",
      isExcluded: function ($state, model) {
        return !["audio", "date", "number", "number with unit", "string", "text"].includes(
          model.viewModel.record.type
        )
          ? true
          : "add";
      },
      help: "The maximum possible value for this question, maximum number of seconds for audio recordings, or the maximum number of characters for string or text questions.",
    });
    module.addInput("", "default_answer", {
      title: "Default Answer",
      type: "string",
    });
    module.addInput("", "note", { title: "Note", type: "text" });
    module.addInput("", "parent_name", {
      column: "page.name",
      isExcluded: true,
    });
    module.addInput("", "question_option_count", { isExcluded: true });
    module.addInput("", "qnaire_id", { column: "qnaire.id", isExcluded: true });

    module.addExtraOperation("view", {
      title: "Visualize",
      operation: async function ($state, model) {
        await $state.go( "question.chart", { identifier: model.viewModel.record.getIdentifier() } );
      },
      isIncluded: function ($state, model) {
        return (
          "function" == typeof Chart &&
          ["boolean", "list"].includes(model.viewModel.record.type)
        );
      }
    });

    /* ############################################################################################## */
    cenozo.providers.directive("cnQuestionChart", [
      "CnQuestionModelFactory",
      "CnSession",
      "$state",
      function (CnQuestionModelFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("chart.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model)) $scope.model = CnQuestionModelFactory.root;

            angular.extend($scope, {
              isComplete: false,

              refresh: async function () {
                if ($scope.isComplete) {
                  $scope.isComplete = false;

                  try {
                    await $scope.model.chartModel.onView();
                  } finally {
                    $scope.isComplete = true;
                  }
                }
              },
            });

            try {
              await $scope.model.chartModel.onView();
              CnSession.setBreadcrumbTrail([
                { title: "Question", },
                {
                  title: $scope.model.chartModel.record.name,
                  go: async function () {
                    await $state.go("question.view", {
                      identifier: $scope.model.chartModel.record.id,
                    });
                  },
                },
                { title: "Answer Summary", },
              ]);
            } finally {
              $scope.isComplete = true;
            }
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnQuestionClone", [
      "CnQnairePartCloneFactory",
      "CnQuestionModelFactory",
      "CnSession",
      "$state",
      function (CnQnairePartCloneFactory, CnQuestionModelFactory, CnSession, $state) {
        return {
          templateUrl: cenozoApp.getFileUrl(
            "pine",
            "qnaire_part_clone.tpl.html"
          ),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model)) {
              $scope.model = CnQnairePartCloneFactory.instance("question");
              $scope.model.parentModel = CnQuestionModelFactory.root;
            }

            await $scope.model.onLoad();
            CnSession.setBreadcrumbTrail([
              {
                title: "Page",
                go: async function () {
                  await $state.go("page.list");
                },
              },
              {
                title: $scope.model.parentSourceName,
                go: async function () {
                  await $state.go("page.view", {
                    identifier: $scope.model.sourceParentId,
                  });
                },
              },
              {
                title: "Questions",
              },
              {
                title: $scope.model.sourceName,
                go: async function () {
                  await $state.go("question.view", {
                    identifier: $scope.model.sourceId,
                  });
                },
              },
              {
                title: "move/copy",
              },
            ]);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQuestionChartFactory", [
      "CnHttpFactory",
      "$state",
      function (CnHttpFactory, $state) {
        var object = function (parentModel) {
          angular.extend(this, {
            installed: "function" == typeof Chart,
            record: {},
            parentModel: parentModel,
            navigating: false,

            total: null,
            labels: [],
            data: [],

            transitionOnViewQuestion: async function () {
              await this.parentModel.transitionToViewState(this.record);
            },

            viewPrevious: async function () {
              if (!this.navigating && this.record.previous_id) {
                try {
                  this.navigating = true;
                  await $state.go(
                    "question.chart",
                    { identifier: this.record.previous_id },
                    { reload: true }
                  );
                } finally {
                  this.navigating = false;
                }
              }
            },

            viewNext: async function () {
              if (!this.navigating && this.record.next_id) {
                try {
                  this.navigating = true;
                  await $state.go(
                    "question.chart",
                    { identifier: this.record.next_id },
                    { reload: true }
                  );
                } finally {
                  this.navigating = false;
                }
              }
            },

            onView: async function (force) {
              const response = await CnHttpFactory.instance({
                path: this.parentModel.getServiceResourcePath(),
                data: { select: { column: ["id", "name", "type", "answer_summary", "previous_id", "next_id"] } },
              }).get();

              this.record = response.data;
              this.record.getIdentifier = () => String(this.record.id);

              if (this.installed) {
                const answerSummary = JSON.parse(this.record.answer_summary);
                this.data = Object.values(answerSummary).map(value => Number(value));
                this.total = this.data.reduce((total, value) => { total += Number(value); return total; }, 0);
                this.labels = Object.keys(answerSummary).map(
                  (label, index) => label + " (" + (100*this.data[index]/this.total).toFixed(2) + "%)"
                );
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
    cenozo.providers.factory("CnQuestionListFactory", [
      "CnBaseListFactory",
      function (CnBaseListFactory) {
        var object = function (parentModel) {
          CnBaseListFactory.construct(this, parentModel);
        };
        return {
          instance: function (parentModel) {
            // if we are looking at the list of questions in a qnaire then we must change the default column order
            var obj = new object(parentModel);
            if ("qnaire" == parentModel.getSubjectFromState())
              obj.order.column = "module.rank";
            return obj;
          },
        };
      },
    ]);

    /* ############################################################################################## */
    // extend the model factory
    cenozo.providers.decorator("CnQuestionViewFactory", [
      "$delegate",
      "CnHttpFactory",
      "CnModalConfirmFactory",
      function ($delegate, CnHttpFactory, CnModalConfirmFactory) {
        var instance = $delegate.instance;
        $delegate.instance = function (parentModel, root) {
          // if we are looking at the list of questions in a qnaire then we must change the default column order
          var object = instance(parentModel, root);
          angular.extend(object, {
            getChildList: function () {
              return object
                .$$getChildList()
                .filter(
                  (child) =>
                    "list" == object.record.type ||
                    "question_option" != child.subject.snake
                );
            },

            onPatch: async function (data) {
              var proceed = true;

              // see if we're changing from a list question which would result in deleting options
              var removingOptions =
                angular.isDefined(data.type) &&
                "list" != object.record.type &&
                0 < object.record.question_option_count;

              // warn if changing name will cause automatic change to preconditions
              if (angular.isDefined(data.name)) {
                var response = await CnHttpFactory.instance({
                  path: object.parentModel.getServiceResourcePath(),
                  data: { select: { column: "qnaire_dependencies" } },
                }).query();

                const dependencies = JSON.parse(response.data.qnaire_dependencies);
                if (null != dependencies) {
                  var message =
                    "The following parts of the questionnaire refer to this question in their precondition " +
                    "and will automatically be updated to refer to the question's new name:\n";

                  for (const table in dependencies) {
                    const tableName = table.replace(/_/, " ").ucWords();
                    for (const column in dependencies[table]) {
                      message += "\n" + tableName + " " + column + ": " + dependencies[table][column].join(", ");
                    }
                  }

                  message += "\n\nAre you sure you wish to proceed?";

                  var response = await CnModalConfirmFactory.instance({
                    message: message,
                  }).show();

                  if (!response) {
                    // put the old value back
                    object.record.name = object.backupRecord.name;
                    proceed = false;
                  }
                }
              } else if (removingOptions) {
                var response = await CnModalConfirmFactory.instance({
                  message:
                    "By changing this question's type to \"" +
                    object.record.type +
                    '" ' +
                    object.record.question_option_count +
                    " question option" +
                    (1 == object.record.question_option_count ? "" : "s") +
                    " will be deleted. " +
                    "Are you sure you wish to proceed?",
                }).show();

                if (!response) {
                  // put the old value back
                  object.record.type = object.backupRecord.type;
                  proceed = false;
                }
              }

              if (proceed) {
                await object.$$onPatch(data);

                if (angular.isDefined(data.type)) {
                  if ("device" != object.record.type) object.record.device_id = null;
                  if ("equipment" != object.record.type) object.record.equipment_type_id = null;
                  if ("lookup" != object.record.type) object.record.lookup_id = null;
                  if ("number with unit" != object.record.type) object.record.unit_list = null;
                  if (!["date", "number","number with unit"].includes(object.record.type)) {
                    object.record.minimum = null;
                    if (!["string", "text"].includes(object.record.type)) object.record.maximum = null;
                  }
                }

                if (removingOptions) {
                  // update the question option list since we may have deleted them
                  if (
                    0 < response.length &&
                    angular.isDefined(object.questionOptionModel)
                  )
                    await object.questionOptionModel.listModel.onList(true);
                }
              }
            },
          });

          return object;
        };


        return $delegate;
      },
    ]);

    // extend the base model factory created by caling initQnairePartModule()
    cenozo.providers.decorator("CnQuestionModelFactory", [
      "$delegate",
      "CnQuestionChartFactory",
      "CnHttpFactory",
      function ($delegate, CnQuestionChartFactory, CnHttpFactory) {
        function extendModelObject(object) {
          angular.extend(object, {
            chartModel: CnQuestionChartFactory.instance(object),
            getAddEnabled: function () {
              // don't allow the add button while viewing the qnaire
              return (
                "qnaire" != object.getSubjectFromState() &&
                object.$$getAddEnabled()
              );
            },
            getDeleteEnabled: function () {
              // don't allow the add button while viewing the qnaire
              return (
                !object.viewModel.record.readonly &&
                "qnaire" != object.getSubjectFromState() &&
                object.$$getDeleteEnabled()
              );
            },
            getMetadata: async function () {
              await this.$$getMetadata();

              let queryList = [
                CnHttpFactory.instance({
                  path: "equipment_type",
                  data: {
                    select: { column: ["id", "name"] },
                    modifier: { order: "name" },
                  },
                }).query(),

                CnHttpFactory.instance({
                  path: "lookup",
                  data: {
                    select: { column: ["id", "name"] },
                    modifier: { order: "name" },
                  },
                }).query()
              ];

              if( 'question' == this.getSubjectFromState() && 'view' == this.getActionFromState() ) {
                queryList.push(
                  CnHttpFactory.instance({
                    path: [
                      "qnaire",
                      this.viewModel.record.qnaire_id,
                      "device",
                    ].join("/"),
                    data: {
                      select: { column: ["id", "name"] },
                      modifier: { order: "name" },
                    },
                  }).query()
                );
              }

              const responseList = await Promise.all(queryList);

              this.metadata.columnList.equipment_type_id.enumList =
                responseList[0].data.reduce((list, item) => {
                  list.push({ value: item.id, name: item.name });
                  return list;
                }, []);

              this.metadata.columnList.lookup_id.enumList =
                responseList[1].data.reduce((list, item) => {
                  list.push({ value: item.id, name: item.name });
                  return list;
                }, []);

              if( 'question' == this.getSubjectFromState() && 'view' == this.getActionFromState() ) {
                this.metadata.columnList.device_id.enumList =
                  responseList[2].data.reduce((list, item) => {
                    list.push({ value: item.id, name: item.name });
                    return list;
                  }, []);
              }
            },
          });
          return object;
        }

        var instance = $delegate.instance;
        $delegate.root = extendModelObject($delegate.root);
        $delegate.instance = function (parentModel, root) {
          return extendModelObject(instance(root));
        };

        return $delegate;
      },
    ]);
  },
});
