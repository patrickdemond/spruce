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
                    '<cn-respondent-list model="respondentModel"></cn-respondent-list>'
                  );
                $compile(element.contents())(scope);
              };
            },
            controller: function ($scope) {
              oldController($scope);
              $scope.respondentModel = CnRespondentModelFactory.instance();
            },
          });
        }

        return $delegate;
      },
    ]);
  },
});
