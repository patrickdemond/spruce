cenozoApp.defineModule({
  name: "stage",
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
        singular: "stage",
        plural: "stages",
        possessive: "stage's",
      },
      columnList: {
        rank: {
          column: "stage.rank",
          title: "Rank",
          type: "rank",
        },
        name: {
          title: "Name",
          column: "stage.name",
        },
        precondition: {
          column: "stage.precondition",
          title: "Precondition",
        },
        first_module: {
          title: "First Module",
          column: "first_module.name",
        },
        last_module: {
          title: "Last Module",
          column: "last_module.name",
        },
        module_count: {
          title: "Number of Modules",
        },
      },
      defaultOrder: {
        column: "stage.rank",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      rank: {
        title: "Rank",
        type: "rank",
      },
      name: {
        title: "Name",
        type: "string",
        format: "identifier",
      },
      precondition: {
        title: "Precondition",
        type: "text",
        help: "An expression which restricts whether or not the stage can proceed.",
      },
      token_check_precondition: {
        title: "Token Check Precondition",
        type: "text",
        help:
          "An expression which determines if the interviewer must provide the token before launching the stage.",
      },
      first_module_id: {
        title: "First Module",
        type: "enum",
        help: "Note that you must create at least one module before a stage can be created",
      },
      last_module_id: {
        title: "Last Module",
        type: "enum",
        help: "Note that you must create at least one module before a stage can be created",
      },
      first_module_rank: { type: "hidden", column: "first_module.rank" },
      last_module_rank: { type: "hidden", column: "last_module.rank" },
    });

    /* ############################################################################################## */
    cenozo.providers.directive("cnStageAdd", [
      "CnStageModelFactory",
      "CnHttpFactory",
      function (CnStageModelFactory, CnHttpFactory) {
        return {
          templateUrl: module.getFileUrl("add.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnStageModelFactory.root;

            var cnRecordAddScope = null;
            $scope.$on("cnRecordAdd linked", function (event, data) {
              cnRecordAddScope = data;

              // change which modules are disabled based on the new module's rank
              cnRecordAddScope.baseCheckFn = cnRecordAddScope.check;
              cnRecordAddScope.check = async function (property) {
                // run the original check function first
                await cnRecordAddScope.baseCheckFn(property);

                if (
                  ["rank", "first_module_id", "last_module_id"].includes(
                    property
                  )
                ) {
                  var inputArray = cnRecordAddScope.dataArray.findByProperty(
                    "title",
                    ""
                  ).inputArray;
                  $scope.model.updateModuleListState(
                    inputArray.findByProperty("key", "first_module_id")
                      .enumList,
                    inputArray.findByProperty("key", "last_module_id").enumList,
                    cnRecordAddScope.record.rank
                      ? $scope.model.stageList.findByProperty(
                          "rank",
                          cnRecordAddScope.record.rank - 1
                        )
                      : null,
                    cnRecordAddScope.record.rank
                      ? $scope.model.stageList.findByProperty(
                          "rank",
                          cnRecordAddScope.record.rank
                        )
                      : null,
                    cnRecordAddScope.record.first_module_id,
                    cnRecordAddScope.record.last_module_id
                  );
                }
              };
            });
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnStageAddFactory", [
      "CnBaseAddFactory",
      function (CnBaseAddFactory) {
        var object = function (parentModel) {
          CnBaseAddFactory.construct(this, parentModel);

          this.onNew = async function (record) {
            await this.$$onNew(record);
            await this.parentModel.updateStageAndModuleList();
            this.parentModel.metadata.columnList.first_module_id.enumList.forEach(
              (item) => {
                item.disabled = true;
              }
            );
            this.parentModel.metadata.columnList.last_module_id.enumList.forEach(
              (item) => {
                item.disabled = true;
              }
            );
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
    cenozo.providers.factory("CnStageViewFactory", [
      "CnBaseViewFactory",
      function (CnBaseViewFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);

          angular.extend(this, {
            onView: async function (force) {
              await this.$$onView(force);
              await this.parentModel.updateStageAndModuleList();
              this.parentModel.updateModuleListState(
                this.parentModel.metadata.columnList.first_module_id.enumList,
                this.parentModel.metadata.columnList.last_module_id.enumList,
                this.parentModel.stageList.findByProperty(
                  "rank",
                  this.record.rank - 1
                ),
                this.parentModel.stageList.findByProperty(
                  "rank",
                  this.record.rank + 1
                ),
                this.record.first_module_id,
                this.record.last_module_id
              );
            },
            onPatch: async function (data) {
              // when changing the first/last module update which can be selected by the other
              if (angular.isDefined(data.first_module_id)) {
                this.parentModel.metadata.columnList.last_module_id.enumList.forEach(
                  (item) => {
                    item.disabled = this.record.first_module_rank >= item.rank;
                  }
                );
              } else if (angular.isDefined(data.last_module_id)) {
                this.parentModel.metadata.columnList.first_module_id.enumList.forEach(
                  (item) => {
                    item.disabled = this.record.last_module_rank <= item.rank;
                  }
                );
              }

              this.$$onPatch(data);
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
    cenozo.providers.factory("CnStageModelFactory", [
      "CnBaseModelFactory",
      "CnStageAddFactory",
      "CnStageListFactory",
      "CnStageViewFactory",
      "CnHttpFactory",
      function (
        CnBaseModelFactory,
        CnStageAddFactory,
        CnStageListFactory,
        CnStageViewFactory,
        CnHttpFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.addModel = CnStageAddFactory.instance(this);
          this.listModel = CnStageListFactory.instance(this);
          this.viewModel = CnStageViewFactory.instance(this, root);

          angular.extend(this, {
            stageList: null,

            updateStageAndModuleList: async function () {
              const parent = this.getParentIdentifier();

              var response = await CnHttpFactory.instance({
                path: [parent.subject, parent.identifier, 'stage'].join("/"),
                data: {
                  select: {
                    column: [
                      "rank",
                      {
                        table: "first_module",
                        column: "rank",
                        alias: "first_module_rank",
                      },
                      {
                        table: "last_module",
                        column: "rank",
                        alias: "last_module_rank",
                      },
                    ],
                  },
                  modifier: { order: "stage.rank", },
                },
              }).query();

              this.stageList = response.data;

              var response = await CnHttpFactory.instance({
                path: angular.isDefined(parent.subject)
                  ? [parent.subject, parent.identifier, "module"].join("/")
                  : this.getServiceCollectionPath().replace("stage", "module"),
                data: {
                  select: { column: ["id", "rank", "name"] },
                  modifier: { order: "rank" },
                },
              }).query();

              this.metadata.columnList.first_module_id.enumList =
                response.data.reduce((list, item) => {
                  list.push({
                    value: item.id,
                    rank: item.rank,
                    name: item.rank + ". " + item.name,
                    disabled: true,
                  });
                  return list;
                }, []);
              this.metadata.columnList.last_module_id.enumList = angular.copy(
                this.metadata.columnList.first_module_id.enumList
              );
            },

            updateModuleListState: function (
              firstEnumList,
              lastEnumList,
              prevStage,
              nextStage,
              firstModuleId,
              lastModuleId
            ) {
              if (angular.isUndefined(prevStage)) prevStage = null;
              if (angular.isUndefined(nextStage)) nextStage = null;

              // get the min and max module ranks (it doesn't matter which enumList we get them from)
              var minModuleRank = angular.isDefined(firstEnumList[0].rank)
                ? firstEnumList[0].rank
                : firstEnumList[1].rank;
              var maxModuleRank = firstEnumList[firstEnumList.length - 1].rank;
              var firstModuleRank = angular.isDefined(firstModuleId)
                ? firstEnumList.findByProperty("value", firstModuleId).rank
                : null;
              var lastModuleRank = angular.isDefined(lastModuleId)
                ? lastEnumList.findByProperty("value", lastModuleId).rank
                : null;

              // If a stage comes before this one then it has to retain at least one module
              var minRank = prevStage ? prevStage.first_module_rank + 1 : 1;
              // If there is no previous stage then we must include the first module, otherwise if a stage comes after this
              // one then it has to retain at least one module
              var maxRank = !prevStage
                ? minModuleRank
                : nextStage
                ? nextStage.last_module_rank - 1
                : 1000000;
              // The max rank has to be equal or greater than the current last rank
              if (lastModuleRank && lastModuleRank < maxRank)
                maxRank = lastModuleRank;
              firstEnumList.forEach((item) => {
                item.disabled =
                  angular.isDefined(item.rank) &&
                  !(minRank <= item.rank && item.rank <= maxRank);
              });

              // If there is no next stage then we must include all remaining modules, otherwise if a stage comes before this
              // one then it has to retain at least one module
              var minRank = !nextStage
                ? maxModuleRank
                : prevStage
                ? prevStage.first_module_rank
                : 1;
              // The min rank has to be equal or less than the current first rank
              if (firstModuleRank && firstModuleRank > minRank)
                minRank = firstModuleRank;
              // If a stage comes after this one then it has to retain at least one module
              var maxRank = nextStage
                ? nextStage.last_module_rank - 1
                : 1000000;
              lastEnumList.forEach((item) => {
                item.disabled =
                  angular.isDefined(item.rank) &&
                  !(minRank <= item.rank && item.rank <= maxRank);
              });
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
