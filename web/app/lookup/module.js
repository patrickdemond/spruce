cenozoApp.defineModule({
  name: "lookup",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: { column: "name" },
      name: {
        singular: "lookup",
        plural: "lookups",
        possessive: "lookup's",
      },
      columnList: {
        name: {
          title: "Name",
        },
        indicator_count: {
          title: "Indicators",
        },
        lookup_item_count: {
          title: "Items",
        },
        description: {
          title: "Description",
          align: "left",
        },
      },
      defaultOrder: {
        column: "name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
        format: "identifier",
      },
      version: {
        title: "Version",
        type: "string",
      },
      description: {
        title: "Description",
        type: "text",
      },
    });

    module.addExtraOperation("view", {
      title: "Upload Data",
      operation: async function ($state, model) {
        await $state.go(
          "lookup.upload",
          { identifier: model.viewModel.record.getIdentifier() }
        );
      },
    });

    cenozo.providers.directive("cnLookupUpload", [
      "CnLookupModelFactory",
      "CnSession",
      "$state",
      function (CnLookupModelFactory, CnSession, $state) {
        return {
          templateUrl: module.getFileUrl("upload.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model)) $scope.model = CnLookupModelFactory.root;

            await $scope.model.viewModel.onView();
            $scope.model.viewModel.dataSummary = null;

            CnSession.setBreadcrumbTrail([{
              title: "Lookups",
              go: async function() { await $state.go("lookup.list"); },
            }, {
              title: $scope.model.viewModel.record.name,
              go: async function () {
                await $state.go("lookup.view", {
                  identifier: $scope.model.viewModel.record.getIdentifier(),
                });
              }
            }, {
              title: "Upload Data",
            }]);
          },
        };
      }
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnLookupViewFactory", [
      "CnBaseViewFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      "$state",
      "$rootScope",
      function (
        CnBaseViewFactory,
        CnHttpFactory,
        CnModalMessageFactory,
        $state,
        $rootScope
      ) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct( this, parentModel, root, "indicator" );

          angular.extend(this, {
            working: false,
            file: null,
            dataSummary: null,

            cancel: async function () {
              this.dataSummary = null;
              await $state.go("lookup.view", { identifier: this.record.getIdentifier() });
            },

            checkData: function () {
              // need to wait for cnUpload to do its thing
              const removeFn = $rootScope.$on("cnUpload read", async () => {
                removeFn(); // only run once
                try {
                  this.working = true;
                  var data = new FormData();
                  data.append("file", this.file);

                  // check the data file
                  var response = await CnHttpFactory.instance({
                    path: this.parentModel.getServiceResourcePath() + "?action=check",
                    data: this.file,
                  }).patch();

                  this.dataSummary = response.data;
                } finally {
                  this.working = false;
                }
              });
            },
            applyData: async function() {
              let proceed = true;
              if( 0 == this.dataSummary.lookup_item.created ) {
                proceed = false;
                for( const indicator in this.dataSummary.indicator_list ) {
                  if( 0 < this.dataSummary.indicator_list[indicator].created ) {
                    proceed = true;
                    break;
                  }
                }
              }

              if( proceed ) {
                try {
                  // apply the data file
                  this.working = true;
                  await CnHttpFactory.instance({
                    path: this.parentModel.getServiceResourcePath() + "?action=apply",
                    data: this.file,
                  }).patch();
                  await $state.go("lookup.view", {
                    identifier: this.record.getIdentifier(),
                  });
                } finally {
                  this.working = false;
                }
              } else {
                CnModalMessageFactory.instance({
                  title: "No New Data",
                  message: "The lookup data and indicators found in the file already exist in the lookup.",
                }).show();
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
  },
});
