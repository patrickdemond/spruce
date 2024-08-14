cenozoApp.extendModule({
  name: "root",
  dependencies: "respondent",
  create: (module) => {
    var respondentModule = cenozoApp.module("respondent");

    // extend the view factory
    cenozo.providers.decorator("cnHomeDirective", [
      "$delegate",
      "$compile",
      "CnSession",
      "CnRespondentModelFactory",
      function ($delegate, $compile, CnSession, CnRespondentModelFactory) {
        var oldController = $delegate[0].controller;
        var oldLink = $delegate[0].link;

        if ("interviewer" == CnSession.role.name) {
          // show interviewers all of today's respondents on their home page
          angular.extend($delegate[0], {
            compile: function () {
              return function (scope, element, attrs) {
                if (angular.isFunction(oldLink)) oldLink(scope, element, attrs);
                angular
                  .element(element[0].querySelector(".inner-view-frame div"))
                  .append(
                    '<div class="noselect">' +
                      '<cn-respondent-list model="todayRespondentModel"></cn-respondent-list>' +
                      '<div class="spacer"></div>' +
                      '<cn-respondent-list model="inProgressRespondentModel"></cn-respondent-list>' +
                    "</div>"
                  );
                $compile(element.contents())(scope);
              };
            },
            controller: function ($scope) {
              oldController($scope);

              // setup today's respondent list
              $scope.todayRespondentModel = CnRespondentModelFactory.instance();

              // change the default ordering to the start datetime
              $scope.todayRespondentModel.module.defaultOrder = { column: "start_datetime", reverse: true };
              $scope.todayRespondentModel.listModel.heading =
                "Today's " + $scope.todayRespondentModel.listModel.heading;
              angular.extend($scope.todayRespondentModel, {
                subList: "today",

                // get a list of all respondents for all qnaires
                getServiceCollectionPath: (ignoreParent) => "respondent",

                // restrict the respondent list to those starting today
                getServiceData: function (type, columnRestrictLists) {
                  let data = this.$$getServiceData(type, columnRestrictLists);

                  // restrict to the user's definition of "today" based on their timezone
                  let minTime = moment().tz(CnSession.user.timezone).hour(0).minute(0).second(0).tz("UTC");
                  let maxTime = minTime.clone().add(1, "days");

                  if (angular.isUndefined(data.modifier) ) data.modifier = {};
                  if (angular.isUndefined(data.modifier.where) ) data.modifier.where = [];
                  data.modifier.where.push(
                    { column: "qnaire.closed", operator: "=", value: false },
                    {
                      column: "respondent.start_datetime",
                      operator: ">=",
                      value: minTime.format("YYYY-MM-DD HH:mm:ss"),
                    },
                    {
                      column: "respondent.start_datetime",
                      operator: "<",
                      value: maxTime.format("YYYY-MM-DD HH:mm:ss"),
                    },
                  );

                  return data;
                },
              });

              // setup the full respondent list
              $scope.inProgressRespondentModel = CnRespondentModelFactory.instance();

              // change the default ordering to the start datetime
              $scope.inProgressRespondentModel.module.defaultOrder = { column: "start_datetime", reverse: true };
              angular.extend($scope.inProgressRespondentModel.listModel, {
                heading: "Full " + $scope.inProgressRespondentModel.listModel.heading,

                // update the today respondent list anytime the inProgress list is updated
                onList: async function (replace) {
                  await this.$$onList(replace);
                  await this.parentModel.updateUsesParent();

                  await $scope.todayRespondentModel.listModel.onList(replace);
                  await $scope.todayRespondentModel.updateUsesParent();
                },
              });

              angular.extend($scope.inProgressRespondentModel, {
                // get a list of all respondents for all qnaires
                getServiceCollectionPath: (ignoreParent) => "respondent",

                // restrict the respondent list to those starting today
                getServiceData: function (type, columnRestrictLists) {
                  let data = this.$$getServiceData(type, columnRestrictLists);

                  // restrict to non closed qnaires
                  if (angular.isUndefined(data.modifier)) data.modifier = {};
                  if (angular.isUndefined(data.modifier.where)) data.modifier.where = [];
                  data.modifier.where.push({ column: "qnaire.closed", operator: "=", value: false });

                  return data;
                },
              });
            },
          });
        }

        return $delegate;
      },
    ]);
  },
});
