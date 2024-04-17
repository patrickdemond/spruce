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
              $scope.todayRespondentModel.listModel.heading =
                "Today's " + $scope.todayRespondentModel.listModel.heading;
              angular.extend($scope.todayRespondentModel, {
                subList: "today",  

                // get a list of all respondents for all qnaires
                getServiceCollectionPath: (ignoreParent) => "respondent",

                // restrict the respondent list to those starting today
                getServiceData: function (type, columnRestrictLists) {
                  let data = this.$$getServiceData(type, columnRestrictLists);

                  if (angular.isUndefined(data.modifier) ) data.modifier = {};
                  if (angular.isUndefined(data.modifier.w) ) data.modifier.w = [];
                  data.modifier.w.push(
                    { c: "qnaire.closed", op: "=", v: false },
                    { c: "respondent.start_datetime", op: "LIKE", v: moment().format("YYYY-MM-DD") + " %" }
                  );

                  return data;
                },
              });

              // setup the full respondent list
              $scope.inProgressRespondentModel = CnRespondentModelFactory.instance();
              $scope.inProgressRespondentModel.listModel.heading =
                "Full " + $scope.inProgressRespondentModel.listModel.heading;
              angular.extend($scope.inProgressRespondentModel, {
                // get a list of all respondents for all qnaires
                getServiceCollectionPath: (ignoreParent) => "respondent",

                // restrict the respondent list to those starting today
                getServiceData: function (type, columnRestrictLists) {
                  let data = this.$$getServiceData(type, columnRestrictLists);

                  if (angular.isUndefined(data.modifier) ) data.modifier = {};
                  if (angular.isUndefined(data.modifier.w) ) data.modifier.w = [];
                  data.modifier.w.push({ c: "qnaire.closed", op: "=", v: false });

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
