cenozoApp.defineModule({
  name: "page",
  dependencies: ["address", "participant", "question"],
  models: ["add", "list", "view"],
  create: (module) => {
    cenozoApp.initQnairePartModule(module, "page");

    module.identifier.parent = {
      subject: "module",
      column: "module.id",
    };

    module.addInput("", "tabulate", {
      title: "Tabulate",
      type: "boolean",
      help: "Determines whether questions on this page should be organized into a table."
    });
    module.addInput("", "average_time", {
      title: "Average Time (seconds)",
      type: "string",
      isConstant: true,
      isExcluded: "add",
    });
    module.addInput("", "max_time", {
      title: "Max Time (seconds)",
      type: "string",
      format: "integer",
      isConstant: true,
      help:
        "Maximum page time is automatically calculated to exclude major outliers by setting its value to that " +
        "of the outer fence (3 times the interquartile width above the upper quartile).",
    });
    module.addInput("", "note", { title: "Note", type: "text" });
    module.addInput("", "qnaire_id", { column: "qnaire.id", isExcluded: true });
    module.addInput("", "qnaire_name", {
      column: "qnaire.name",
      isExcluded: true,
    });
    module.addInput("", "total_pages", {
      column: "qnaire.total_pages",
      isExcluded: true,
    });
    module.addInput("", "variable_suffix", {
      column: "qnaire.variable_suffix",
      isExcluded: true,
    });
    module.addInput("", "debug", { column: "qnaire.debug", isExcluded: true });
    module.addInput("", "problem_report", { column: "qnaire.problem_report", isExcluded: true });
    module.addInput("", "base_language", {
      column: "base_language.code",
      isExcluded: true,
    });
    module.addInput("", "prompts", { isExcluded: true });
    module.addInput("", "module_prompts", { isExcluded: true });
    module.addInput("", "popups", { isExcluded: true });
    module.addInput("", "module_popups", { isExcluded: true });
    module.addInput("", "module_id", { isExcluded: true });
    module.addInput("", "parent_name", {
      column: "module.name",
      isExcluded: true,
    });

    cenozo.insertPropertyAfter(
      module.columnList,
      "name",
      "tabulate",
      {
        title: "Tabulate",
        type: "boolean",
      }
    );

    cenozo.insertPropertyAfter(
      module.columnList,
      "question_count",
      "average_time",
      {
        title: "Average Time",
        type: "seconds",
      }
    );

    module.addExtraOperation("view", {
      title: "Preview",
      operation: async function ($state, model) {
        await $state.go(
          "page.render",
          { identifier: model.viewModel.record.getIdentifier() },
          { reload: true }
        );
      },
    });

    // used by services below to returns the index of the option matching the second argument
    function searchOptionList(optionList, id) {
      var optionIndex = null;
      if (angular.isArray(optionList))
        optionList.some((option, index) => {
          if (option == id || (angular.isObject(option) && option.id == id)) {
            optionIndex = index;
            return true;
          }
        });
      return optionIndex;
    }

    /* ############################################################################################## */
    cenozo.providers.directive("cnPageClone", [
      "CnQnairePartCloneFactory",
      "CnPageModelFactory",
      "CnSession",
      "$state",
      function (CnQnairePartCloneFactory, CnPageModelFactory, CnSession, $state) {
        return {
          templateUrl: cenozoApp.getFileUrl(
            "pine",
            "qnaire_part_clone.tpl.html"
          ),
          restrict: "E",
          scope: { model: "=?" },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model)) {
              $scope.model = CnQnairePartCloneFactory.instance("page");
              $scope.model.parentModel = CnPageModelFactory.root;
            }

            await $scope.model.onLoad();

            CnSession.setBreadcrumbTrail([
              {
                title: "Module",
                go: async function () {
                  await $state.go("module.list");
                },
              },
              {
                title: $scope.model.parentSourceName,
                go: async function () {
                  await $state.go("module.view", {
                    identifier: $scope.model.sourceParentId,
                  });
                },
              },
              {
                title: "Pages",
              },
              {
                title: $scope.model.sourceName,
                go: async function () {
                  await $state.go("page.view", {
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
    cenozo.providers.directive("cnPageNavigator", [
      function () {
        return {
          templateUrl: module.getFileUrl("page_navigator.tpl.html"),
          restrict: "E",
          scope: {
            model: "=?",
            isComplete: "=",
            placement: "@",
          },
          controller: function ($scope) {
            $scope.text = function (address) {
              return $scope.model.renderModel.text(address);
            };
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.directive("cnPageRender", [
      "CnPageModelFactory",
      "CnTranslationHelper",
      "CnSession",
      "CnHttpFactory",
      "$state",
      "$document",
      function (
        CnPageModelFactory,
        CnTranslationHelper,
        CnSession,
        CnHttpFactory,
        $state,
        $document
      ) {
        return {
          templateUrl: module.getFileUrl("render.tpl.html"),
          restrict: "E",
          scope: { model: "=?" },
          link: function (scope, element) {
            element.on("$destroy", () =>
              angular.isDefined(scope.model) && scope.model.renderModel.cancelDevicePromises()
            );
          },
          controller: async function ($scope) {
            if (angular.isUndefined($scope.model))
              $scope.model = CnPageModelFactory.root;
            angular.extend($scope, {
              isComplete: false,
              text: function (address) {
                return $scope.model.renderModel.text(address);
              },
              patch: async function (property) {
                var model = [
                  "address1",
                  "address2",
                  "city",
                  "region_id",
                  "postcode",
                ].includes(property)
                  ? $scope.model.renderModel.addressModel
                  : $scope.model.renderModel.participantModel;

                if (model.getEditEnabled()) {
                  var element = cenozo.getFormElement(property);
                  var valid = model.testFormat(
                    property,
                    model.viewModel.record[property]
                  );

                  if (element) {
                    element.$error.format = !valid;
                    cenozo.updateFormElement(element, true);
                  }

                  if (valid) {
                    var data = {};
                    data[property] = model.viewModel.record[property];
                    await model.viewModel.onPatch(data);
                  }
                }
              },
            });

            // bind keydown (first unbind to prevent duplicates)
            $document.unbind("keydown");
            $document.bind("keydown", async function (event) {
              // deactivate hot-keys when inside a number, text or textbox
              if (
                !$scope.model.renderModel.showHidden ||
                ["number", "text", "textarea"].includes(event.target.type)
              )
                return;

              var action = null;
              if (
                $scope.isComplete &&
                !$scope.model.renderModel.working &&
                !$scope.model.renderModel.hotKeyDisabled
              ) {
                if (["ShiftLeft", "ShiftRight"].includes(event.code)) {
                  $scope.model.renderModel.upperDigitsActivated = true;
                  $scope.$apply();
                }
              }
            });

            // bind keyup (first unbind to prevent duplicates)
            $document.unbind("keyup");
            $document.bind("keyup", async function (event) {
              // deactivate hot-keys when inside a number, text or textbox
              if (
                !$scope.model.renderModel.showHidden ||
                ["number", "text", "textarea"].includes(event.target.type)
              )
                return;

              var action = null;
              if (
                $scope.isComplete &&
                !$scope.model.renderModel.working &&
                !$scope.model.renderModel.hotKeyDisabled
              ) {
                if (["ShiftLeft", "ShiftRight"].includes(event.code)) {
                  $scope.model.renderModel.upperDigitsActivated = false;
                  $scope.$apply();
                } else {
                  if ("Minus" == event.code || "NumpadSubtract" == event.code) {
                    // proceed to the previous page when the minus key is pushed (keyboard or numpad)
                    if (null != $scope.model.viewModel.record.previous_id)
                      action = "prevPage";
                  } else if (
                    "Equal" == event.code ||
                    "NumpadAdd" == event.code
                  ) {
                    // proceed to the next page when the plus key is pushed (keyboard "=" key or numpad)
                    if (
                      angular.isUndefined(
                        $scope.model.viewModel.record.next_id
                      ) ||
                      null != $scope.model.viewModel.record.next_id
                    )
                      action = "nextPage";
                  } else if ("BracketLeft" == event.code) {
                    // focus on the previous question when the open square bracket key is pushed (keyboard "[")
                    action = "prevQuestion";
                  } else if ("BracketRight" == event.code) {
                    // focus on the next question when the close square bracket key is pushed (keyboard "]")
                    action = "nextQuestion";
                  } else {
                    var match = event.code.match(/^Digit([0-9])$/);
                    if (match) {
                      action = parseInt(match[1]);
                      if (0 == action) action += 10; // zero comes after 1-9 on the keyboard
                      if (event.shiftKey) action += 10; // shift moves things up to the next set of numbers
                    }
                  }

                  if (null != action) {
                    event.stopPropagation();
                    if (["prevPage", "nextPage"].includes(action)) {
                      // move to the prev or next page
                      await Promise.all(
                        $scope.model.renderModel.writePromiseList
                      );
                      (await "prevPage") == action
                        ? $scope.model.renderModel.backup()
                        : $scope.model.renderModel.proceed();
                      $scope.$apply();
                    } else if (
                      ["prevQuestion", "nextQuestion"].includes(action)
                    ) {
                      // move to the prev or next question
                      $scope.model.renderModel.focusQuestion(
                        "prevQuestion" == action
                      );
                      $scope.$apply();
                    } else if (null != action) {
                      await $scope.model.renderModel.onDigitHotKey(action);
                      $scope.$apply();
                    }
                  }
                }
              }
            });

            try {
              await $scope.model.renderModel.onReady();

              CnSession.setBreadcrumbTrail([
                {
                  title: $scope.model.renderModel.data.qnaire_name,
                  go: async function () {
                    await $state.go("qnaire.view", {
                      identifier: $scope.model.renderModel.data.qnaire_id,
                    });
                  },
                },
                {
                  title: $scope.model.renderModel.data.respondent_name
                    ? $scope.model.renderModel.data.respondent_name
                    : "Preview",
                },
              ]);
            } finally {
              $scope.isComplete = true;
            }
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnPageRenderFactory", [
      "CnModalConfirmFactory",
      "CnModalMessageFactory",
      "CnModalDatetimeFactory",
      "CnModalInputFactory",
      "CnModalTextFactory",
      "CnModalPreStageFactory",
      "CnSession",
      "CnHttpFactory",
      "CnTranslationHelper",
      "CnAudioRecordingFactory",
      "CnParticipantModelFactory",
      "CnAddressModelFactory",
      "$state",
      "$timeout",
      "$interval",
      "$sce",
      function (
        CnModalConfirmFactory,
        CnModalMessageFactory,
        CnModalDatetimeFactory,
        CnModalInputFactory,
        CnModalTextFactory,
        CnModalPreStageFactory,
        CnSession,
        CnHttpFactory,
        CnTranslationHelper,
        CnAudioRecordingFactory,
        CnParticipantModelFactory,
        CnAddressModelFactory,
        $state,
        $timeout,
        $interval,
        $sce
      ) {
        var object = function (parentModel) {
          // private helper functions
          function formatDate(date) {
            var m = getDate(date);
            return m ? m.format("dddd, MMMM Do YYYY") : null;
          }

          function formatTime(time) {
            var m = getTime(time);
            return m ? m.format(CnSession.user.use12hourClock ? "h:mm a" : "HH:mm") : null;
          }

          function isDkna(value) {
            return angular.isObject(value) && true === value.dkna;
          }

          function isRefuse(value) {
            return angular.isObject(value) && true === value.refuse;
          }

          function isDknaOrRefuse(value) {
            return (
              angular.isObject(value) &&
              (true === value.dkna || true === value.refuse)
            );
          }

          function getDate(date) {
            if ("now" == date) date = moment().format("YYYY-MM-DD");
            return date && !angular.isObject(date)
              ? moment(new Date(date))
              : null;
          }

          function getTime(time,tz) {
            // if there isn't a date then add one so that moment will read it correctly
            const match = null == time ? null : time.match(/^([0-9]+):([0-9]+)$/);
            if( null != match ) {
              m = angular.isDefined(tz) ? moment().tz(tz) : moment();
              m.hour(parseInt(match[1]));
              m.minute(parseInt(match[2]));
              m.second(0);
              time = moment(m).format();
            } else if ("now" == time) {
              m = angular.isDefined(tz) ? moment().tz(tz) : moment();
              time = time.format();
            }

            return time && !angular.isObject(time)
              ? moment(new Date(time))
              : null;
          }

          function getAttributeNames(precondition) {
            // scan the precondition for active attributes (also include the showhidden constant)
            var list = [];
            if (angular.isString(precondition)) {
              var matches = precondition.match(/@[^@ ]+@|\bshowhidden\b/g);
              if (null != matches && 0 < matches.length)
                list = matches.map((m) => m.replace(/@/g, ""));
            }
            return list;
          }

          function isTooSmall(type, minimum, value) {
            return (
              null != minimum &&
              null != value &&
              (
                (
                  "number" == type &&
                  !angular.isObject(value) &&
                  value < minimum
                ) || (
                  "number with unit" == type &&
                  angular.isObject(value) &&
                  angular.isDefined(value.value) &&
                  null != value.value &&
                  value.value < minimum
                )
              )
            );
          }

          function isTooLarge(type, maximum, value) {
            return (
              null != maximum &&
              null != value &&
              (
                (
                  "number" == type &&
                  !angular.isObject(value) &&
                  value > maximum
                ) || (
                  "number with unit" == type &&
                  angular.isObject(value) &&
                  angular.isDefined(value.value) &&
                  null != value.value &&
                  value.value > maximum
                )
              )
            );
          }

          function getUnitListEnum(unitList, languageList, baseLang) {
            if (null == unitList) return null;

            function getName (input, lang, baseLang) {
              let nameObj = input;

              // if a string is provided then convert it to an object
              if (angular.isString(nameObj)) {
                nameObj = {};
                nameObj[baseLang] = input;
              }

              // get the name for the appropriate language, or the base language as a fall-back
              return (
                angular.isDefined(nameObj[lang]) ? nameObj[lang] :
                angular.isDefined(nameObj[baseLang]) ? nameObj[baseLang] : null
              );
            }

            // convert the unit_list property to an array used by the select element
            const data = JSON.parse(unitList);

            let unitListEnum = {};
            languageList.forEach( language => {
              // first add the "choose" unselected option
              unitListEnum[language.code] = [{
                value: null,
                name: CnTranslationHelper.lookupData.misc.choose[language.code]
              }];

              if (angular.isArray(data)) {
                data.forEach( item => {
                  if (angular.isString(item)) {
                    // if only a string is provided then use it as the key and value for all languages
                    unitListEnum[language.code].push({ value: item, name: item });
                  } else if (angular.isObject(item)) {
                    for( const key in item ) {
                      const name = getName(item[key], language.code, baseLang);
                      if (null != name) unitListEnum[language.code].push({ value: key, name: name });
                    }
                  }
                });
              } else if (angular.isObject(data)) {
                for( const key in data ) {
                  const name = getName(data[key], language.code, baseLang);
                  if (null != name) unitListEnum[language.code].push({ value: key, name: name });
                }
              }
            });

            return unitListEnum;
          }

          async function focusElement(id) {
            // keep trying until the element exists (10 tries max)
            var promise = await $interval(
              () => {
                var element = document.getElementById(id);
                if (null != element) {
                  element.focus();
                  $interval.cancel(promise);
                }
              },
              50,
              10
            );
            return promise;
          }

          angular.extend(this, {
            parentModel: parentModel,
            participantModel: CnParticipantModelFactory.root,
            addressModel: CnAddressModelFactory.root,
            prevModuleList: [],
            nextModuleList: [],
            working: false,
            progress: 0,
            previewMode: "respondent" != parentModel.getSubjectFromState(),
            data: {
              token: null,
              scanned_token: null,
              response_id: null,
              participant_id: null,
              address_id: null,
              qnaire_id: null,
              qnaire_name: null,
              start_datetime: null,
              end_datetime: null,
              comments: null,
              checked_in: null,
              stage_id: null,
              page_id: null,
              stage_selection: null,
              base_language: null,
              stages: null,
              closed: null,
              submitted: null,
              respondent_name: null,
              introductionList: null,
              conclusionList: null,
              closedList: null,
              title: null,
            },
            responseStageList: null,
            consentList: null,
            consentEnumList: [
              { value: null, name: "(empty)", disabled: true },
              { value: true, name: "Yes", disabled: false },
              { value: false, name: "No", disabled: false },
            ],
            deviationTypeList: null,
            qnaireReportList: null,
            languageList: null,
            showHidden: false,
            alternateId: null,
            username: null,
            focusQuestionId: null,
            hotKeyDisabled: false,
            upperDigitsActivated: false,
            activeAttributeList: [],
            questionList: [],
            optionListById: {},
            currentLanguage: null,
            writePromiseList: [],
            promiseIndex: 0,

            reset: function () {
              angular.extend(this, {
                questionList: [],
                activeAttributeList: [],
                prevModuleList: [],
                nextModuleList: [],
                focusQuestionId: null,
                devicePromiseList: [],
              });
            },

            cancelDevicePromises: function() {
              if( angular.isDefined( this.devicePromiseList ) ) {
                this.devicePromiseList.forEach( promise => $interval.cancel(promise.promise) );
                this.devicePromiseList = [];
              }
            },

            addDevicePromise: function(question) {
              if ('device' == question.type && 'in progress' == question.device_status ) {
                const promise = $interval(
                  async () => {
                    if( question.device_uuid ) {
                      // update question answer and device details (status/UUID)
                      const response = await CnHttpFactory.instance({
                        path: "answer/" + ["token="+$state.params.token, "question_id="+question.id].join(";"),
                        data: {
                          select: {
                            column: [
                              'value',
                              'files_received',
                              { table: 'answer_device', column: 'uuid' },
                              { table: 'answer_device', column: 'status' },
                            ],
                          },
                        },
                      }).get();

                      const newValue = angular.fromJson( response.data.value );
                      if( newValue != question.value ) {
                        question.value = angular.fromJson(response.data.value);
                        question.files_received = response.data.files_received;
                        var complete = this.questionIsComplete(question);
                        question.incomplete = false === complete ? true : true === complete ? false : complete;
                        question.backupValue = angular.copy(response.data.value);
                      }
                      question.device_status = response.data.status;
                      question.device_uuid = response.data.uuid;

                      if( 'in progress' != question.device_status ) $interval.cancel(promise);
                    }
                  },
                  4000, // update every 4 seconds
                );
                this.devicePromiseList.push({id: question.id, promise: promise});
              }
            },

            setConsent: async function (consent) {
              if (null == consent.consent_id) {
                await CnHttpFactory.instance({
                  path: "consent",
                  data: {
                    participant_id: this.data.participant_id,
                    consent_type_id: consent.consent_type_id,
                    accept: consent.accept,
                    written: false,
                    datetime: moment().format(),
                    note:
                      'Added by Pine during "' +
                      this.data.qnaire_name +
                      '" interview with token "' +
                      $state.params.token +
                      '".',
                  },
                }).post();
              } else {
                await CnHttpFactory.instance({
                  path: "consent/" + consent.consent_id,
                  data: { accept: consent.accept },
                }).patch();
              }
            },

            checkToken: function () {
              // only check the regex if the token has been provided
              if(this.data.scanned_token && this.data.token_regex) {
                try {
                  const re = new RegExp(this.data.token_regex);
                  if(!re.test(this.data.scanned_token)) {
                    CnModalMessageFactory.instance({
                      title: "Invalid Token Format",
                      message:
                        'The token you have provided, "' + this.data.scanned_token +
                        '", does not match the correct format.  Please check that you have entered it correctly.',
                      error: true,
                    }).show();
                    this.data.scanned_token = null;
                  }
                } catch(err) {
                  if (this.data.debug) {
                    CnModalMessageFactory.instance({
                      title: "Invalid Token Regex",
                      message:
                        'WARNING: The questionnaire\'s token regex, "' + this.data.token_regex +
                        '", is not a valid regular expression so it will be ignored.',
                      error: true,
                    }).show();
                  }
                }
              }
            },

            reopen: async function () {
              await CnHttpFactory.instance({
                path: "respondent/token=" + $state.params.token + "?action=reopen",
              }).patch();
              await this.parentModel.reloadState(true);
            },

            setCheckIn: async function (checkedIn) {
              // update the token if we've changed it
              var updatedToken = null;
              if (this.data.token_check && checkedIn && this.data.token != this.data.scanned_token) {
                var self = this;
                await CnHttpFactory.instance({
                  path: "respondent/token=" + $state.params.token,
                  data: { token: this.data.scanned_token },
                  onError: function (error) {
                    if (409 == error.status) {
                      CnModalMessageFactory.instance({
                        title: "Interview ID already exists",
                        message:
                          'You cannot use the Interview ID "' +
                          self.data.scanned_token +
                          '" since it already exists.  ' +
                          "Please double check that you have entered it correctly or try a different ID.",
                        error: true,
                      }).show();
                    } else CnModalMessageFactory.httpError(error);
                  },
                }).patch();
                updatedToken = this.data.scanned_token;
              }

              // mark the response as checked in
              await CnHttpFactory.instance({
                path: "response/" + this.data.response_id,
                data: { checked_in: checkedIn },
              }).patch();

              if (null == updatedToken) {
                // the token hasn't changed so just reload the state
                await this.parentModel.reloadState(true);
              } else {
                // the token has changed so we have to change the URL
                await $state.go("respondent.run", { token: updatedToken });
              }
            },

            downloadReport: async function () {
              await CnHttpFactory.instance({
                path: ["response", this.data.response_id].join("/"),
                format: "pdf",
              }).file();
            },

            text: function (address) {
              return CnTranslationHelper.translate(
                address,
                this.currentLanguage
              );
            },

            checkForIncompleteQuestions: function () {
              return this.getVisibleQuestionList().some(
                (question) => question.incomplete
              );
            },

            getVisibleQuestionList: function () {
              return this.questionList.filter((question) =>
                this.evaluate(question.precondition)
              );
            },

            getVisibleOptionList: function (question) {
              return angular.isDefined( question.optionList ) ?
                question.optionList.filter((option) => this.evaluate(option.precondition)) : [];
            },

            getFocusableQuestionList: function () {
              return this.getVisibleQuestionList().filter(
                (question) => "comment" != question.type
              );
            },

            getHotKey: function (question, item) {
              var key = null;

              if (this.focusQuestionId == question.id) {
                if ("boolean" == question.type) {
                  if ("dkna" == item) {
                    key = 3;
                  } else if ("refuse" == item) {
                    key = question.dkna_allowed ? 4 : 3;
                  } else {
                    var bool = item;
                    key = bool ? 1 : 2;
                  }
                } else if ("list" == question.type) {
                  var optionList = this.getVisibleOptionList(question);
                  if ("dkna" == item) {
                    key = optionList.length + 1;
                  } else if ("refuse" == item) {
                    key =
                      optionList.length + (question.dkna_allowed ? 1 : 0) + 1;
                  } else {
                    var optionId = item;
                    var index = optionList.findIndexByProperty("id", optionId);
                    if (null != index) key = index + 1;
                  }
                } else {
                  if ("dkna" == item) {
                    key = 2;
                  } else if ("refuse" == item) {
                    key = (question.dkna_allowed ? 1 : 0) + 2;
                  } else {
                    key = 1;
                  }
                }

                // if upper digits are activated then move down by 10
                if (this.upperDigitsActivated) key -= 10;

                // change 10 to 0 and only allow numbers in [0,9]
                key = 10 == key ? 0 : 1 <= key && key <= 9 ? key : null;
              }

              // enclose with square brackets
              return null == key ? "" : "[" + key + "]";
            },

            isReportAvailable: function () {
              return null != this.qnaireReportList &&
                     this.qnaireReportList.includes(this.currentLanguage);
            },

            reportProblem: async function () {
              var response = await CnModalTextFactory.instance({
                title: this.text("misc.reportProblem.promptTitle"),
                message: this.data.problemPromptList[this.currentLanguage],
                html: true,
                size: "lg",
                minLength: 1,
              }).show();

              if (false !== response) {
                var modal = CnModalMessageFactory.instance({
                  title: this.text("misc.reportProblem.waitTitle"),
                  message: this.text("misc.reportProblem.waitMessage"),
                  block: true,
                });
                modal.show();

                try {
                  await CnHttpFactory.instance({
                    path: ["response", this.data.response_id, "problem_report"].join("/"),
                    data: { description: response },
                  }).post();

                  CnModalMessageFactory.instance({
                    title: this.text("misc.reportProblem.promptTitle"),
                    message: this.data.problemConfirmList[this.currentLanguage],
                    html: true,
                  }).show();
                } finally {
                  modal.close();
                }
              }
            },

            getModulePrompt: function () {
              return null != this.parentModel.viewModel.record.module_prompts
                ? $sce.trustAsHtml(this.parentModel.viewModel.record.module_prompts[this.currentLanguage])
                : "";
            },

            getModulePopup: function () {
              return null != this.parentModel.viewModel.record.module_popups
                ? this.parentModel.viewModel.record.module_popups[this.currentLanguage]
                : "";
            },

            getPagePrompt: function () {
              return null != this.parentModel.viewModel.record.prompts &&
                null != this.parentModel.viewModel.record.popups
                ? $sce.trustAsHtml(
                    (
                      this.parentModel.viewModel.record.popups[this.currentLanguage] ?
                        '<b class="invert">ⓘ</b> ' : ""
                    ) + this.parentModel.viewModel.record.prompts[this.currentLanguage]
                  )
                : "";
            },

            getPagePopup: function () {
              return null != this.parentModel.viewModel.record.popups
                ? this.parentModel.viewModel.record.popups[this.currentLanguage]
                : "";
            },

            getQuestionPrompt: function (question) {
              return $sce.trustAsHtml(
                (question.popups[this.currentLanguage] ||
                question.minimum ||
                question.maximum
                  ? '<b class="invert">ⓘ</b> '
                  : "") + question.prompts[this.currentLanguage]
              );
            },

            getQuestionPopup: function (question) {
              return (
                question.popups[this.currentLanguage] +
                ((question.minimum || question.maximum ? " [" : "") +
                  (question.minimum
                    ? "Min: " + this.evaluateLimit(question.minimum)
                    : "") +
                  (question.minimum && question.maximum ? ", " : "") +
                  (question.maximum
                    ? "Max: " + this.evaluateLimit(question.maximum)
                    : "") +
                  (question.minimum || question.maximum ? "]" : ""))
              );
            },

            getOptionPrompt: function (question, option) {
              return $sce.trustAsHtml(
                this.getHotKey(question, option.id) +
                  // only non-multiple answer options get a checkmark icon
                  (option.multiple_answers
                    ? ""
                    : ' <i class="glyphicon ' +
                      (angular.isDefined(question.answer) && question.answer.optionList[option.id].selected
                        ? "glyphicon-check"
                        : "glyphicon-unchecked") +
                      '"></i> ') +
                  (option.popups[this.currentLanguage] ||
                  null != option.minimum ||
                  null != option.maximum
                    ? '<b class="invert">ⓘ</b> '
                    : "") +
                  option.prompts[this.currentLanguage] +
                  // only multiple answers get a plus icon
                  (option.multiple_answers
                    ? '<i class="glyphicon glyphicon-plus"></i>'
                    : "")
              );
            },

            getOptionPopup: function (option) {
              return (
                option.popups[this.currentLanguage] +
                ((option.minimum || option.maximum ? " [" : "") +
                  (option.minimum
                    ? "Min: " + this.evaluateLimit(option.minimum)
                    : "") +
                  (option.minimum && option.maximum ? ", " : "") +
                  (option.maximum
                    ? "Max: " + this.evaluateLimit(option.maximum)
                    : "") +
                  (option.minimum || option.maximum ? "]" : ""))
              );
            },

            getLookupValues: async function(question, viewValue) {
              question.isLoading = true;

              let retVal = undefined;
              try {
                const response = await CnHttpFactory.instance({
                  path: ["lookup",question.lookup.id,"lookup_item"].join("/"),
                  data: {
                    select: {
                      column: [
                        "identifier",
                        {
                          column: "CONCAT( lookup_item.identifier, ': ', lookup_item.description )",
                          alias: "description",
                          table_prefix: false,
                        },
                      ],
                    },
                    modifier: {
                      where: [{
                        column: "lookup_item.identifier",
                        operator: "like",
                        value: "%" + viewValue + "%"
                      },{
                        column: "lookup_item.description",
                        operator: "like",
                        value: "%" + viewValue + "%",
                        or: true
                      }],
                      order: { 'identifier': true },
                    },
                  }
                }).get();
                retVal = response.data;
              } finally {
                question.isLoading = false;
              }

              return retVal;
            },

            onReady: async function () {
              this.previewMode = "respondent" != parentModel.getSubjectFromState();

              // We show hidden stuff when previewing or when there is a state parameter asking for it
              this.showHidden = this.previewMode
                ? true
                : angular.isDefined($state.params.show_hidden)
                ? $state.params.show_hidden
                : false;

              this.alternateId = angular.isDefined($state.params.alternate_id)
                ? $state.params.alternate_id
                : null;

              this.username = angular.isDefined($state.params.username)
                ? $state.params.username
                : null;

              if (!this.previewMode) {
                angular.extend(this, {
                  responseStageList: null,
                  consentList: null,
                  deviationTypeList: null,
                });

                // check for the respondent using the token
                var params = "?assert_response=1";
                if (this.showHidden) params += "&show_hidden=1";

                var response = await CnHttpFactory.instance({
                  path: "respondent/token=" + $state.params.token + params,
                  data: {
                    select: {
                      column: [
                        "token",
                        "participant_id",
                        "qnaire_id",
                        "start_datetime",
                        "end_datetime",
                        "introduction_list",
                        "conclusion_list",
                        "closed_list",
                        "problem_prompt_list",
                        "problem_confirm_list",
                        "phone_list",
                        { table: "participant", column: "first_name" },
                        { table: "participant", column: "last_name" },
                        { table: "qnaire", column: "debug" },
                        { table: "qnaire", column: "problem_report" },
                        { table: "qnaire", column: "stages" },
                        { table: "qnaire", column: "closed" },
                        { table: "qnaire", column: "name", alias: "qnaire_name", },
                        { table: "qnaire", column: "token_regex", },
                        { table: "qnaire", column: "token_check", },
                        { table: "response", column: "id", alias: "response_id", },
                        { table: "response", column: "checked_in" },
                        { table: "response", column: "page_id" },
                        { table: "response", column: "stage_selection" },
                        { table: "response", column: "submitted" },
                        { table: "response", column: "comments" },
                        { table: "response_stage", column: "id", alias: "response_stage_id", },
                        { table: "response_stage", column: "stage_id" },
                        { table: "language", column: "code", alias: "base_language", },
                      ],
                    },
                  },
                  onError: function (error) {
                    $state.go("error." + error.status, error);
                  },
                }).get();

                this.data = response.data;

                // set the scanned token only if the token is non-generic
                this.data.scanned_token =
                  null ==
                  this.data.token.match(
                    /^[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/
                  )
                    ? this.data.token
                    : null;

                // get the stage list if there is one:
                //   not ready or not applicable: nothing
                //   ready: launch, skip
                //   active: nothing (it will never show in the list)
                //   paused: resume, skip, reset
                //   skipped: reset
                //   parent skipped: nothing
                //   completed: re-open, reset
                if (this.data.stages) {
                  var [
                    responseStageResponse,
                    consentResponse,
                    deviationTypeResponse,
                    qnaireReportResponse,
                    participantResponse,
                    addressResponse,
                  ] = await Promise.all([
                    CnHttpFactory.instance({
                      path: ["response", this.data.response_id, "response_stage"].join("/"),
                      data: {
                        select: {
                          column: [
                            "id",
                            "status",
                            "start_datetime",
                            "end_datetime",
                            "deviation_type_id",
                            "deviation_comments",
                            "comments",
                            { table: "stage", column: "rank" },
                            { table: "stage", column: "name" },
                            {
                              table: "deviation_type",
                              column: "name",
                              alias: "deviation",
                            },
                          ],
                        },
                        modifier: { order: "stage.rank" },
                      },
                    }).query(),

                    CnHttpFactory.instance({
                      path: ["respondent", "token=" + $state.params.token, "consent"].join("/"),
                      data: {
                        select: {
                          column: [
                            {
                              table: "consent",
                              column: "id",
                              alias: "consent_id",
                            },
                            { table: "consent", column: "accept" },
                            {
                              table: "consent_type",
                              column: "id",
                              alias: "consent_type_id",
                            },
                            {
                              table: "consent_type",
                              column: "name",
                              alias: "consent_type",
                            },
                            {
                              table: "role_has_consent_type",
                              column: "consent_type_id",
                              alias: "access",
                            },
                          ],
                        },
                      },
                    }).query(),

                    CnHttpFactory.instance({
                      path: ["qnaire", this.data.qnaire_id, "deviation_type"].join("/"),
                      data: { modifier: { order: "deviation_type.name" } },
                    }).query(),

                    CnHttpFactory.instance({
                      path: ["qnaire", this.data.qnaire_id, "qnaire_report"].join("/"),
                      data: { select: { column: [{ table: 'language', column: 'code', alias: 'lang' }], }, },
                    }).query(),

                    this.participantModel.viewModel.onView(true),

                    this.addressModel.viewModel.onView(true),
                  ]);

                  this.responseStageList = responseStageResponse.data;
                  if (0 == this.responseStageList.length)
                    throw new Error(
                      "Questionnaire has not stages, unable to proceed."
                    );

                  // set each response stage's possible operations
                  this.responseStageList.forEach((responseStage) => {
                    responseStage.operations = [];

                    if ( !["not ready", "not applicable", "parent skipped", "skipped"]
                            .includes(responseStage.status)) {
                      var self = this;
                      responseStage.operations.push({
                        name: "launch",
                        title:
                          "completed" == responseStage.status
                            ? "Re-Open"
                            : "paused" == responseStage.status
                            ? "Resume"
                            : "Launch",
                        // the launch operation may be an order deviation if another stage before this one is ready or paused
                        getDeviation: function () {
                          return self.responseStageList
                            .filter((rs) => rs.rank < responseStage.rank)
                            .some((rs) =>
                              ["ready", "paused"].includes(rs.status)
                            )
                            ? "order"
                            : null;
                        },
                      });
                    }

                    if (
                      ["paused", "ready"].includes(responseStage.status) &&
                      responseStage.rank != this.responseStageList.length
                    ) {
                      responseStage.operations.push({
                        name: "skip",
                        title: "Skip",
                        // the skip operation is always a deviation
                        getDeviation: function () {
                          return "skip";
                        },
                      });
                    }

                    if (
                      !["not ready", "not applicable", "parent skipped", "ready"].includes(
                        responseStage.status
                      )
                    ) {
                      responseStage.operations.push({
                        name: "reset",
                        title: "Reset",
                        // the reset operation is never a deviation
                        getDeviation: function () {
                          return null;
                        },
                      });
                    }
                  });

                  this.consentList = consentResponse.data;

                  // convert the access column (it will be null if the role doesn't have access)
                  this.consentList.forEach(
                    (consent) => (consent.access = null != consent.access)
                  );

                  this.deviationTypeList = deviationTypeResponse.data;

                  this.qnaireReportList = qnaireReportResponse.data.map(qnaireReport => qnaireReport.lang);

                  // enum lists use value, so set the value to the deviation type's ID
                  this.deviationTypeList.forEach((deviationType) => {
                    deviationType.value = deviationType.id;
                  });

                  // setup the participant and address input lists
                  this.participantInputList = [
                    {
                      key: "honorific",
                      title: "Honorific",
                      type: "string",
                      help:
                        "English examples: Mr. Mrs. Miss Ms. Dr. Prof. Br. Sr. Fr. Rev. Pr. " +
                        "French examples: M. Mme Dr Dre Prof. F. Sr P. Révérend Pasteur Pasteure Me",
                    },
                    {
                      key: "first_name",
                      title: "First Name",
                      type: "string",
                    },
                    {
                      key: "other_name",
                      title: "Other/Nickname",
                      type: "string",
                    },
                    {
                      key: "last_name",
                      title: "Last Name",
                      type: "string",
                    },
                    {
                      key: "date_of_birth",
                      title: "Date of Birth",
                      type: "dob",
                      isConstant: true,
                      max: "now",
                    },
                    {
                      key: "sex",
                      title: "Sex at Birth",
                      type: "enum",
                      isConstant: true,
                      enumList:
                        this.participantModel.metadata.columnList.sex.enumList,
                    },
                    {
                      key: "current_sex",
                      title: "Current Sex",
                      type: "enum",
                      enumList:
                        this.participantModel.metadata.columnList.current_sex
                          .enumList,
                    },
                    {
                      key: "email",
                      title: "Email",
                      type: "string",
                      format: "email",
                      help: "Must be in the format &quot;account@domain.name&quot;.",
                    },
                  ];

                  this.addressInputList = [
                    {
                      key: "address1",
                      title: "Address Line 1",
                      type: "string",
                    },
                    {
                      key: "address2",
                      title: "Address Line 2",
                      type: "string",
                    },
                    {
                      key: "city",
                      title: "City",
                      type: "string",
                    },
                    {
                      key: "region_id",
                      title: "Region",
                      type: "enum",
                      isConstant: true,
                      enumList:
                        this.addressModel.metadata.columnList.region_id
                          .enumList,
                      help: "The region cannot be changed directly, instead it is automatically updated based on the postcode.",
                    },
                    {
                      key: "postcode",
                      title: "Postcode",
                      type: "string",
                    },
                  ];
                }

                // parse the intro, conclusion, close and problem descriptions
                angular.extend(this.data, {
                  introductionList: CnTranslationHelper.parseDescriptions(
                    this.data.introduction_list,
                    this.showHidden
                  ),
                  conclusionList: CnTranslationHelper.parseDescriptions(
                    this.data.conclusion_list,
                    this.showHidden
                  ),
                  closedList: CnTranslationHelper.parseDescriptions(
                    this.data.closed_list,
                    this.showHidden
                  ),
                  problemPromptList: CnTranslationHelper.parseDescriptions(
                    this.data.problem_prompt_list,
                    this.showHidden
                  ),
                  problemConfirmList: CnTranslationHelper.parseDescriptions(
                    this.data.problem_confirm_list,
                    this.showHidden
                  ),
                  phoneList: this.data.phone_list.split("`").map(item => {
                    const parts = item.split(":");
                    return { type: parts[0], number: parts[1] };
                  }),
                });
                
                for( const lang in this.data.problemPromptList ) {
                  if( 0 == this.data.problemPromptList[lang].length ) {
                    this.data.problemPromptList[lang] =
                      CnTranslationHelper.translate( "misc.reportProblem.promptMessage", lang );
                  }
                }
                
                for( const lang in this.data.problemConfirmList ) {
                  if( 0 == this.data.problemConfirmList[lang].length ) {
                    this.data.problemConfirmList[lang] =
                      CnTranslationHelper.translate( "misc.reportProblem.submitted", lang );
                  }
                }
              }

              if (this.previewMode || null != this.data.page_id) {
                this.reset();

                // We must view the page before getting the questions since viewing a new page will also create the answers
                // to all questions on that page (which the question list needs)
                await this.parentModel.viewModel.onView(true);

                angular.extend(this.data, {
                  page_id: this.parentModel.viewModel.record.id,
                  qnaire_id: this.parentModel.viewModel.record.qnaire_id,
                  qnaire_name: this.parentModel.viewModel.record.qnaire_name,
                  base_language:
                    this.parentModel.viewModel.record.base_language,
                  respondent_name: this.parentModel.viewModel.record.respondent_name,
                });

                this.progress = Math.round(
                  100 *
                    (angular.isDefined(
                      this.parentModel.viewModel.record.stage_pages
                    )
                      ? this.parentModel.viewModel.record.stage_page /
                        this.parentModel.viewModel.record.stage_pages
                      : this.parentModel.viewModel.record.qnaire_page /
                        this.parentModel.viewModel.record.total_pages)
                );

                const [questionResponse, languageResponse] = await Promise.all([
                  CnHttpFactory.instance({
                    path: this.parentModel.getServiceResourceBasePath() + "/question",
                    data: {
                      select: {
                        column: [
                          "id",
                          "rank",
                          "name",
                          "type",
                          "mandatory",
                          "dkna_allowed",
                          "refuse_allowed",
                          "unit_list",
                          "minimum",
                          "maximum",
                          "precondition",
                          "prompts",
                          "popups",
                          "device_id",
                          { table: "device", column: "name", alias: "device" },
                          "lookup_id",
                          { table: "lookup", column: "name", alias: "lookup" },
                        ],
                      },
                      modifier: { order: "question.rank" },
                    },
                  }).query(),

                  await CnHttpFactory.instance({
                    path: ["qnaire", this.data.qnaire_id, "language"].join("/"),
                    data: { select: { column: ["id", "code", "name"] } },
                  }).query(),
                ]);

                if (null == this.currentLanguage)
                  this.currentLanguage = this.data.base_language;

                this.languageList = languageResponse.data;
                this.questionList = questionResponse.data;

                // set the current language to the first (visible) question's language
                if (
                  0 < this.questionList.length &&
                  angular.isDefined(this.questionList[0].language)
                ) {
                  this.questionList.some((question) => {
                    // questions which aren't visible will have a null language
                    if (null != question.language) {
                      this.currentLanguage = question.language;
                      return true;
                    }
                  });

                  cenozoApp.setLang(this.currentLanguage);
                }

                // if in debug mode then get a list of all modules before and after the current
                if (this.parentModel.viewModel.record.debug) {
                  var column = ["id", "rank", "name"];
                  var modifier = { order: "module.rank" };
                  if (this.data.stages)
                    modifier.where = {
                      column: "stage.id",
                      operator: "=",
                      value: this.data.stage_id,
                    };
                  var response = await CnHttpFactory.instance({
                    path: [ "qnaire", this.parentModel.viewModel.record.qnaire_id, "module", ].join("/"),
                    data: {
                      select: { column: ["id", "rank", "name"] },
                      modifier: modifier,
                    },
                  }).query();

                  var foundCurrentModule = false;
                  response.data.forEach((module) => {
                    if (
                      !foundCurrentModule &&
                      module.id == this.parentModel.viewModel.record.module_id
                    ) {
                      foundCurrentModule = true;
                    } else {
                      if (foundCurrentModule) this.nextModuleList.push(module);
                      else this.prevModuleList.push(module);
                    }
                  });
                }

                var activeAttributeList = [];
                await Promise.all(
                  this.questionList.reduce((list, question, questionIndex) => {
                    question.incomplete = false;
                    question.value = angular.fromJson(question.value);
                    question.backupValue = angular.copy(question.value);
                    activeAttributeList = activeAttributeList.concat(
                      getAttributeNames(question.precondition)
                    );

                    // if the question is a list type then get the options
                    if ("list" == question.type) {
                      list.push(
                        (async () => {
                          var response = await CnHttpFactory.instance({
                            path: ["question", question.id, "question_option"].join("/") + (
                              !this.previewMode ? "?token=" + $state.params.token : ""
                            ),
                            data: {
                              select: {
                                column: [
                                  "id",
                                  "question_id",
                                  "name",
                                  "exclusive",
                                  "extra",
                                  "multiple_answers",
                                  "unit_list",
                                  "minimum",
                                  "maximum",
                                  "precondition",
                                  "prompts",
                                  "popups",
                                ],
                              },
                              modifier: { order: "question_option.rank" },
                            },
                          }).query();

                          question.optionList = response.data;
                          question.optionList.forEach((option) => {
                            activeAttributeList = activeAttributeList.concat(
                              getAttributeNames(option.precondition)
                            );
                            option.rawPrompts = option.prompts;
                            option.prompts =
                              CnTranslationHelper.parseDescriptions(
                                this.evaluateDescription(option.rawPrompts)
                              );
                            option.rawPopups = option.popups;
                            option.popups =
                              CnTranslationHelper.parseDescriptions(
                                this.evaluateDescription(option.rawPopups)
                              );
                            this.optionListById[option.id] = option;

                            if ("number with unit" == option.extra) {
                              option.unitListEnum = getUnitListEnum(
                                option.unit_list,
                                this.languageList,
                                this.parentModel.viewModel.record.base_language
                              );
                            }
                          });
                        })()
                      );
                    } else if ("audio" == question.type) {
                      // setup the audio recording for this question
                      question.audio = CnAudioRecordingFactory.instance({
                        timeLimit: 0 < question.maximum ? question.maximum : 60,
                        onComplete: (recorder, blob) => {
                          question.file = blob;
                          question.objectURL = window.URL.createObjectURL(question.file);
                          this.setAnswer(question, true);
                        },
                        onTimeout: (recorder) => {
                          CnModalMessageFactory.instance({
                            title: this.text("misc.maxRecordingTimeTitle"),
                            message: this.text("misc.maxRecordingTimeMessage"),
                          }).show();
                        },
                      });

                      question.objectURL = null;
                      if (null != question.file) {
                        // Convert the base64 encoded file provided by the server to a blob, then create
                        // an object URL for the <audio> tag's src attribute.  We can't use the base64 value
                        // directly since Angular will insert "unsafe" in the string (for security).
                        question.file = cenozo.convertBase64ToBlob(question.file, "audio/wav");
                        question.objectURL = window.URL.createObjectURL(question.file);
                      }
                    } else if ("lookup" == question.type) {
                      question.lookup = {
                        "id": question.lookup_id,
                        "name": question.lookup,
                        "isLoading": false,
                      };
                      delete question.lookup_id;
                    } else if ("number with unit" == question.type) {
                      question.unitListEnum = getUnitListEnum(
                        question.unit_list,
                        this.languageList,
                        this.parentModel.viewModel.record.base_language
                      );
                    }
                    return list;
                  }, [])
                );

                this.questionList.forEach((question) => {
                  // parse descriptions
                  question.rawPrompts = question.prompts;
                  question.prompts = CnTranslationHelper.parseDescriptions(
                    this.evaluateDescription(question.rawPrompts)
                  );
                  question.rawPopups = question.popups;
                  question.popups = CnTranslationHelper.parseDescriptions(
                    this.evaluateDescription(question.rawPopups)
                  );
                  this.convertValueToModel(question);

                  // convert audio maximum times
                  if ('audio' == question.type) {
                    question.maximumAsTime = question.maximum
                                           ? moment.utc(question.maximum*1000).format("mm:ss")
                                           : null;
                  }

                  // start listening for changes to device status (only applies to in progress devices)
                  this.addDevicePromise(question);
                });

                // sort active attribute and make a unique list
                this.activeAttributeList = activeAttributeList
                  .sort()
                  .filter(
                    (attribute, index, array) =>
                      index === array.indexOf(attribute)
                  )
                  .map((attribute) => ({ name: attribute, value: null }));
              } else {
                if (!this.data.stage_selection && null == this.data.page_id)
                  await this.reset();

                var response = await CnHttpFactory.instance({
                  path: ["qnaire", this.data.qnaire_id, "language"].join("/"),
                  data: { select: { column: ["id", "code", "name"] } },
                }).query();
                this.languageList = response.data;

                if (null == this.currentLanguage)
                  this.currentLanguage = this.data.base_language;
              }

              // finally, now that we know the language set the title
              this.data.title = this.data.submitted
                ? "Conclusion"
                : null != this.data.page_id
                ? ""
                : this.data.stage_selection
                ? [
                    "Interview",
                    $state.params.token,
                    this.data.checked_in ? "Stage Selection" : "Check-In",
                  ].join(" ")
                : "Introduction";
            },

            // Used to maintain a semaphore of queries so that they are all executed in sequence without any bumping the queue
            runQuery: async (fn) => {
              await Promise.all(this.writePromiseList);

              var response = null;
              var newIndex = this.promiseIndex++;
              try {
                response = fn();
                response.index = newIndex;
              } finally {
                // remove the promise from the write promise list
                var index = this.writePromiseList.findIndexByProperty(
                  "index",
                  newIndex
                );
                if (null != index) this.writePromiseList.splice(index, 1);
              }

              await response;
            },

            convertValueToModel: function (question) {
              // get the full variable name
              question.variable_name =
                question.name +
                (this.parentModel.viewModel.record.variable_suffix
                  ? "_" + this.parentModel.viewModel.record.variable_suffix
                  : "");

              if ("boolean" == question.type) {
                question.answer = {
                  yes: true === question.value,
                  no: false === question.value,
                };
              } else if ("list" == question.type) {
                var selectedOptions = angular.isArray(question.value)
                  ? question.value
                  : [];
                question.answer = {
                  optionList: question.optionList.reduce((list, option) => {
                    var optionIndex = searchOptionList(
                      selectedOptions,
                      option.id
                    );
                    list[option.id] = option.multiple_answers
                      ? { valueList: [], formattedValueList: [] }
                      : { selected: null != optionIndex };

                    if (option.extra) {
                      if (null != optionIndex) {
                        let value = selectedOptions[optionIndex].value;

                        if ("number with unit" == option.extra) {
                          if( !angular.isObject( value ) ) value = { value: null, unit: null };
                        }

                        list[option.id].valueList = option.multiple_answers ? value : [value];
                        list[option.id].formattedValueList = null;
                        if( "date" == option.extra ) {
                          list[option.id].formattedValueList = option.multiple_answers
                            ? formatDate(selectedOptions[optionIndex].value)
                            : [formatDate(selectedOptions[optionIndex].value)];
                        } else if( "time" == option.extra ) {
                          list[option.id].formattedValueList = option.multiple_answers
                            ? formatTime(selectedOptions[optionIndex].value)
                            : [formatTime(selectedOptions[optionIndex].value)];
                        }
                      } else {
                        list[option.id].valueList = option.multiple_answers
                          ? []
                          : [null];
                        list[option.id].formattedValueList =
                          option.multiple_answers ? [] : [null];
                      }
                    }

                    return list;
                  }, {}),
                };
              } else if ("number with unit" == question.type) {
                question.answer = angular.isObject( question.value )
                                ? question.value
                                : { value: null, unit: null };
              } else if ("lookup" == question.type) {
                if( angular.isObject( question.value ) ) {
                  question.answer = {
                    value: question.value.identifier,
                    formattedValue: question.value.description,
                  };
                } else if( angular.isString( question.value ) ) {
                  // the value is the identifier only
                  question.answer = {
                    value: question.value,
                    formattedValue: question.value,
                  };

                  // now go get the formatted value from the server using the identifier
                  async function setFormattedValue( question ) {
                    const response = await CnHttpFactory.instance({
                      path: "lookup_item/lookup_id=" + question.lookup.id + ";identifier=" + question.value
                    }).get();
                    question.answer.formattedValue = response.data.identifier + ": " + response.data.description;

                    // track the formatted value (it gets used if the user backs out of a change)
                    question.answer.backupFormattedValue = question.answer.formattedValue;
                  }
                  setFormattedValue( question );
                } else {
                  question.answer = {
                    value: null,
                    formattedValue: null,
                  };
                }

                // track the formatted value (it gets used if the user backs out of a change)
                question.answer.backupFormattedValue = question.answer.formattedValue;
              } else {
                question.answer = {
                  value:
                    angular.isString(question.value) ||
                    angular.isNumber(question.value)
                      ? question.value
                      : null,
                  formattedValue: null
                };
                if( "date" == question.type ) {
                  question.answer.formattedValue = formatDate(question.value);
                } else if( "time" == question.type ) {
                  question.answer.formattedValue = formatTime(question.value);
                };

                if ("device" == question.type) {
                  // if the question's value is dkna or refuse then the device status/uuid has been reset
                  if( isDkna(question.value) || isRefuse(question.value) ) {
                    question.device_status = null;
                    question.device_uuid = null;
                  }
                }
              }

              question.answer.dkna = isDkna(question.value);
              question.answer.refuse = isRefuse(question.value);
            },

            // Returns true if complete, false if not and the option ID if an option's extra data is missing
            questionIsComplete: function (question) {
              // comments are always complete
              if ("comment" == question.type) return true;

              // hidden questions are always complete
              if (!this.evaluate(question.precondition)) return true;

              // null values are never complete
              if (null == question.value) return false;

              // dkna/refuse questions are always complete
              if (isDknaOrRefuse(question.value)) return true;

              if ("list" == question.type) {
                // get the list of all preconditions for all options belonging to this question
                var preconditionListById = question.optionList.reduce(
                  (object, option) => {
                    object[option.id] = option.precondition;
                    return object;
                  },
                  {}
                );

                // make sure that any selected item with extra data has provided that data
                for (var index = 0; index < question.value.length; index++) {
                  var selectedOption = question.value[index];
                  var selectedOptionId = angular.isObject(selectedOption)
                    ? selectedOption.id
                    : selectedOption;

                  if (angular.isObject(selectedOption)) {
                    const extra = question.optionList.findByProperty( 'id', selectedOptionId ).extra;
                    if ("number with unit" == extra) {
                      // for number with unit both the value and unit need to be filled out
                      if (!angular.isObject(selectedOption.value) ||
                          angular.isUndefined(selectedOption.value.value) ||
                          null == selectedOption.value.value ||
                          angular.isUndefined(selectedOption.value.unit) ||
                          null == selectedOption.value.unit) {
                        return selectedOption.id;
                      }
                    } else {
                      // for other extra types...
                      if( null == selectedOption.value ) {
                        // if the value is null then the extra value is missing
                        return selectedOption.id;
                      } else if( angular.isArray(selectedOption.value) && 0 == selectedOption.value.length ) {
                        // and if there is an empty array then just mark the question as incomplete
                        return false;
                      }
                    }
                  }
                }

                // make sure there is at least one selected option
                for (var index = 0; index < question.value.length; index++) {
                  var selectedOption = question.value[index];
                  var selectedOptionId = angular.isObject(selectedOption)
                    ? selectedOption.id
                    : selectedOption;

                  if (this.evaluate(preconditionListById[selectedOptionId])) {
                    if (angular.isObject(selectedOption)) {
                      if (angular.isArray(selectedOption.value)) {
                        // make sure there is at least one option value
                        for (
                          var valueIndex = 0;
                          valueIndex < selectedOption.value.length;
                          valueIndex++
                        )
                          if (null != selectedOption.value[valueIndex])
                            return true;
                      } else if (null != selectedOption.value) return true;
                    } else if (null != selectedOption) return true;
                  }
                }

                return false;
              } else if ("number with unit" == question.type) {
                // both the value and unit must be filled out
                return (
                  angular.isObject(question.value) &&
                  angular.isDefined(question.value.value) && null != question.value.value &&
                  angular.isDefined(question.value.unit) && null != question.value.unit
                );
              }

              return true;
            },

            evaluateDescription: function (description) {
              return this.evaluate(description, "description");
            },
            evaluateLimit: function (limit) {
              return this.evaluate(limit, "limit");
            },

            /**
             * Type can be one of 'precondition', 'limit', or 'description' (default is precondition)
             */
            evaluate: function (expression, type) {
              if (angular.isUndefined(type)) type = "precondition";

              // handle empty expressions
              if (null == expression)
                return "precondition" == type
                  ? true
                  : "limit" == type
                  ? null
                  : "";

              if ("limit" == type) {
                expression = expression.replace(
                  /\bcurrent_year\b/,
                  moment().format("YYYY")
                );
                expression = expression.replace(
                  /\bcurrent_month\b/,
                  moment().format("MM")
                );
                expression = expression.replace(
                  /\bcurrent_day\b/,
                  moment().format("DD")
                );
              }

              // preconditions which are boolean expressions are already evaluated
              if (
                ("precondition" == type && true == expression) ||
                false == expression
              )
                return expression;

              // replace any attributes
              if (this.previewMode) {
                this.activeAttributeList.forEach((attribute) => {
                  var qualifier = "showhidden" == attribute.name ? "\\b" : "@";
                  var re = new RegExp(qualifier + attribute.name + qualifier);
                  var value = attribute.value;
                  if (null == value) {
                    // do nothing
                  } else if ("" == value) {
                    value = null;
                  } else if ("true" == value) {
                    value = true;
                  } else if ("false" == value) {
                    value = false;
                  } else {
                    var num = parseFloat(value);
                    if (num == value) value = num;
                    else value = '"' + value + '"';
                  }

                  expression = expression.replace(re, value);
                });

                // replace any remaining expressions
                expression = expression.replace(
                  /@[^@ ]+@/g,
                  "precondition" == type ? "null" : "limit" == type ? null : ""
                );
              }

              // everything else needs to be evaluated
              var matches = expression.match(/\$[^$ ]+\$/g);
              if (null != matches)
                matches.forEach((match) => {
                  var parts = match.slice(1, -1).toLowerCase().split(".");
                  var fnName = 1 < parts.length ? parts[1] : null;

                  var subparts = parts[0].toLowerCase().split(":");
                  var questionName = subparts[0];
                  var optionName = null;
                  if (1 < subparts.length) {
                    if ("count()" == subparts[1]) fnName = "count()";
                    else optionName = subparts[1];
                  } else if (
                    null != fnName &&
                    "extra(" == fnName.substr(0, 6)
                  ) {
                    optionName = fnName.match(/extra\(([^)]+)\)/)[1];
                    fnName = "extra()";
                  }

                  // find the referenced question
                  var matchedQuestion = null;
                  this.questionList.some((q) => {
                    if (questionName == q.name.toLowerCase()) {
                      matchedQuestion = q;
                      return true;
                    }
                  });

                  var compiled = "null";
                  if (null != matchedQuestion) {
                    if ("empty()" == fnName) {
                      compiled =
                        null == matchedQuestion.value ? "true" : "false";
                    } else if ("dkna()" == fnName) {
                      compiled = isDkna(matchedQuestion.value)
                        ? "true"
                        : "false";
                    } else if ("refuse()" == fnName) {
                      compiled = isRefuse(matchedQuestion.value)
                        ? "true"
                        : "false";
                    } else if ("count()" == fnName) {
                      compiled = angular.isArray(matchedQuestion.value)
                        ? matchedQuestion.value.length
                        : 0;
                    } else if ("boolean" == matchedQuestion.type) {
                      if (true === matchedQuestion.value) compiled = "true";
                      else if (false === matchedQuestion.value)
                        compiled = "false";
                    } else if ("number" == matchedQuestion.type) {
                      if (angular.isNumber(matchedQuestion.value))
                        compiled = matchedQuestion.value;
                    } else if ("date" == matchedQuestion.type) {
                      if (angular.isString(matchedQuestion.value))
                        compiled = matchedQuestion.value;
                    } else if ("string" == matchedQuestion.type) {
                      if (angular.isString(matchedQuestion.value))
                        compiled =
                          "'" +
                          matchedQuestion.value.replace(/'/g, "\\'") +
                          "'";
                    } else if ("list" == matchedQuestion.type) {
                      if (null == optionName) {
                        // print the description of all selected options
                        compiled =
                          angular.isObject(matchedQuestion.value) &&
                          angular.isDefined(matchedQuestion.value.refuse)
                            ? this.text("misc.refuse")
                            : angular.isObject(matchedQuestion.value) &&
                              angular.isDefined(matchedQuestion.value.dkna)
                            ? this.text("misc.dkna")
                            : angular.isArray(matchedQuestion.value)
                            ? matchedQuestion.value
                                .map(
                                  (option) =>
                                    matchedQuestion.optionList.findByProperty(
                                      "id",
                                      angular.isObject(option)
                                        ? option.id
                                        : option
                                    ).prompts[this.currentLanguage] +
                                    (angular.isObject(option)
                                      ? " " + option.value
                                      : "")
                                )
                                .join(", ")
                            : "";
                      } else {
                        // find the referenced option
                        var matchedOption = null;
                        if (angular.isArray(matchedQuestion.optionList)) {
                          matchedQuestion.optionList.some((o) => {
                            if (optionName == o.name.toLowerCase()) {
                              matchedOption = o;
                              return true;
                            }
                          });
                        }

                        if (
                          null != matchedOption &&
                          angular.isArray(matchedQuestion.value)
                        ) {
                          if (null == matchedOption.extra) {
                            compiled = matchedQuestion.value.includes(
                              matchedOption.id
                            )
                              ? "true"
                              : "false";
                          } else {
                            var answer = matchedQuestion.value.findByProperty(
                              "id",
                              matchedOption.id
                            );
                            if (!angular.isObject(answer)) {
                              compiled = "extra()" == fnName ? "null" : "false";
                            } else {
                              if ("extra()" == fnName) {
                                // if the answer is an array join all non-null values together into a comma-separated list
                                var value = angular.isArray(answer.value)
                                  ? answer.value
                                      .filter((a) => null != a)
                                      .join(", ")
                                  : answer.value;
                                compiled =
                                  "number" == matchedOption.extra
                                    ? value
                                    : '"' + value.replace('"', '"') + '"';
                              } else if (matchedOption.multiple_answers) {
                                // make sure at least one of the answers isn't null
                                compiled = answer.value.some((v) => v != null)
                                  ? "true"
                                  : "false";
                              } else if ("extra()" == fnName) {
                                compiled =
                                  "number" == matchedOption.extra
                                    ? answer.value
                                    : '"' +
                                      answer.value.replace('"', '"') +
                                      '"';
                              } else {
                                compiled =
                                  null != answer.value ? "true" : "false";
                              }
                            }
                          }
                        }
                      }
                    }
                  }

                  expression = expression.replace(match, compiled);
                });

              if ("precondition" != type) return expression;

              // create a function which can be used to evaluate the compiled precondition without calling eval()
              function evaluateExpression(precondition) {
                return Function('"use strict"; return ' + precondition + ";")();
              }
              return evaluateExpression(expression);
            },

            setLanguage: async function () {
              cenozoApp.setLang(this.currentLanguage);
              if (!this.previewMode && null != this.currentLanguage) {
                await this.runQuery(async () => {
                  await CnHttpFactory.instance({
                    path: this.parentModel.getServiceResourceBasePath().replace("page/", "respondent/") +
                      "?action=set_language&code=" + this.currentLanguage,
                  }).patch();
                });
              }
            },

            startRecording: async function (question) {
              var proceed = true;
              if (question.answer.formattedValue) {
                var response = await CnModalConfirmFactory.instance({
                  title: this.text("misc.pleaseConfirm"),
                  message: this.text("misc.reRecordConfirm"),
                }).show();
                proceed = response;
              }

              if (proceed) {
                await question.audio.start();
              }
            },

            stopRecording: async function (question) {
              question.audio.stop();
            },

            getDevicePrompt: function (question) {
              let prompt = this.text( 'misc.launch' ) + " " + question.device;
              if( 'in progress' == question.device_status ) {
                prompt = this.text( 'misc.abort' ) + " " + question.device;
              } else if( 'completed' == question.device_status ) {
                prompt = this.text( 'misc.reLaunch' ) + " " + question.device + " (";

                const dataReceived =
                  !isDkna(question.value) &&
                  !isRefuse(question.value) &&
                  null != question.value;
                const filesReceived = angular.isDefined( question.files_received ) ? question.files_received : 0
                if(dataReceived && 0 < filesReceived) {
                  prompt +=
                    this.text( 'misc.dataAndFileReceived' ).
                      replaceAll( '<FILES>', filesReceived ).
                      replaceAll( '<PLURAL>', 1 == filesReceived ? "" : "s" );
                } else if (dataReceived && 0 == filesReceived) {
                  prompt += this.text( 'misc.dataReceived' );
                } else if (!dataReceived && 0 < filesReceived) {
                  prompt +=
                    this.text( 'misc.fileReceived' ).
                      replaceAll( '<FILES>', filesReceived ).
                      replaceAll( '<PLURAL>', 1 == filesReceived ? "" : "s" );
                } else {
                  prompt += this.text( 'misc.noDataReceived' );
                }
                prompt += ")";
              }

              return prompt;
            },

            launchDevice: async function (question) {
              try {
                this.working = true;
                var modal = CnModalMessageFactory.instance({
                  title: this.text("misc.deviceWaitTitle"),
                  message: this.text("misc.deviceWaitMessage"),
                  block: true,
                });
                modal.show();

                // Launch the device and set the status and uuid, set the answer value to null
                // and watch for updates from the device
                var response = await CnHttpFactory.instance({
                  path: "answer/" + question.answer_id + "?action=launch_device",
                }).patch();
                question.device_status = response.data.status;
                question.device_uuid = response.data.uuid;
                question.value = null;
                this.addDevicePromise(question);
              } finally {
                this.convertValueToModel(question);
                modal.close();
                this.working = false;
              }
            },

            abortDevice: async function (question) {
              try {
                this.working = true;
                var response = await CnHttpFactory.instance({
                  path: "answer_device/uuid=" + question.device_uuid,
                  data: { "status": "cancelled" },
                }).patch();
                question.device_status = null;
                question.device_uuid = null;
                const promiseIndex = this.devicePromiseList.findIndexByProperty("id", question.id);
                if(null != promiseIndex) {
                  $interval.cancel(this.devicePromiseList[promiseIndex].promise);
                  this.devicePromiseList.splice(promiseIndex, 1);
                }
              } finally {
                this.working = false;
              }
            },

            typeaheadChanged: function( question ) {
              // don't do anything if setAnswer is in progress
              if(!this.setAnswerInProgress) {
                if(null === question.answer.formattedValue) {
                  // the formatted value will be null if the user cleared the typeahead
                  this.setAnswer(question, null);
                } else {
                  // If the formattedValue isn't null (it may be undefined) then put back the backup value.
                  // If we don't do this then the UI may show a different value to what's on the server.
                  question.answer.formattedValue = question.answer.backupFormattedValue;
                }
              }
            },

            setAnswerInProgress: false, // track whether setAnswer is in progress
            setAnswer: async function (question, value, noCompleteCheck) {
              this.setAnswerInProgress = true;
              if (angular.isUndefined(noCompleteCheck)) noCompleteCheck = false;

              // if the question's type is a number then make sure it falls within the min/max values
              const minimum = this.evaluateLimit(question.minimum);
              const maximum = this.evaluateLimit(question.maximum);
              const tooSmall = isTooSmall(question.type, minimum, value);
              const tooLarge = isTooLarge(question.type, maximum, value);

              if (tooSmall || tooLarge) {
                // When the number is out of bounds then alert the user
                await this.runQuery( async () => {
                  await CnModalMessageFactory.instance({
                    title: this.text(tooSmall ? "misc.minimumTitle" : "misc.maximumTitle"),
                    message: this.text("misc.limitMessage") + " " + (
                      null == maximum ?
                      this.text("misc.equalOrGreater") + " " + minimum + "." :
                      null == minimum ?
                      this.text("misc.equalOrLess") + " " + maximum + "." :
                      [ this.text("misc.between"), minimum, this.text("misc.and"), maximum + ".", ].join(" ")
                    ),
                  }).show();

                  question.value = angular.copy(question.backupValue);
                  this.convertValueToModel(question);
                });
              } else {
                // No out of bounds detected, so proceed with setting the value
                await this.runQuery( async () => {
                  // Note that we need to treat entering text values a bit differently than other question types.
                  // Some participants may wish to fill in a value after they have already selected dkna or refuse.
                  // When entering their text they may then immediatly click the selected dkna/refuse button to
                  // cancel its selection.  To prevent this button press from immediately clearing their text anwer
                  // we must briefly ignore the answer for this question being set to null.
                  if ("text" == question.type) {
                    if (angular.isString(value)) {
                      var ignore = null;
                      if (isDkna(question.value)) ignore = "ignoreDkna";
                      else if (isRefuse(question.value)) ignore = "ignoreRefuse";

                      if (null != ignore) question[ignore] = true;
                      $timeout(() => { if (null != ignore) delete question[ignore]; }, 500);
                    } else if (
                      ( question.ignoreDkna && (null === value || isDkna(value)) ) ||
                      ( question.ignoreRefuse && (null === value || isRefuse(value)) )
                    ) {
                      // we may have tried setting dkna or refuse when it should be ignored, so change it in the model
                      this.convertValueToModel(question);
                      return;
                    }
                  }

                  try {
                    this.working = true;
                    if ("" === value) value = null;

                    if (!this.previewMode) {
                      // communicate with the server (if we're working with a respondent)
                      var self = this;

                      if ("audio" == question.type) {
                        if (true !== value) {
                          // if setting an audio answer to anything other than true, delete the file
                          question.file = '';
                          question.objectURL = null;
                        }

                        await CnHttpFactory.instance({
                          path: "answer/" + question.answer_id + "?filename=audio.wav",
                          data: question.file,
                          format: "wav",
                          onError: function (error) {
                            question.value = angular.copy(question.backupValue);
                            self.convertValueToModel(question);
                          },
                        }).patch();
                      }

                      let path = "answer/" + question.answer_id;

                      if( null != this.username ) path += "?username=" + this.username;
                      let data = {
                        value: angular.toJson(
                          // lookups store the selected item's identifier as the answer
                          "lookup" == question.type &&
                          null != value &&
                          angular.isObject( value ) &&
                          angular.isDefined( value.identifier ) ? value.identifier : value
                        )
                      };

                      if( null != this.alternateId ) data.alternate_id = this.alternateId;

                      // set the answer's value on the server
                      await CnHttpFactory.instance({
                        path: path,
                        data: data,
                        onError: function (error) {
                          question.value = angular.copy(question.backupValue);
                          self.convertValueToModel(question);
                        },
                      }).patch();
                    }

                    question.value = value;
                    question.backupValue = angular.copy(question.value);
                    this.convertValueToModel(question);

                    // now blank out answers to questions which are no longer visible
                    // (this is done automatically on the server side)
                    this.questionList.forEach((q) => {
                      if (!this.evaluate(q.precondition)) {
                        if (null != q.value) {
                          q.value = null;
                          this.convertValueToModel(q);
                        }
                      } else {
                        // re-evaluate descriptions as they may have changed based on the new answer
                        q.prompts = CnTranslationHelper.parseDescriptions(
                          this.evaluateDescription(q.rawPrompts)
                        );
                        q.popups = CnTranslationHelper.parseDescriptions(
                          this.evaluateDescription(q.rawPopups)
                        );

                        if ("list" == q.type) {
                          q.optionList.forEach((o) => {
                            o.prompts = CnTranslationHelper.parseDescriptions(
                              this.evaluateDescription(o.rawPrompts)
                            );
                            o.popups = CnTranslationHelper.parseDescriptions(
                              this.evaluateDescription(o.rawPopups)
                            );
                          });
                        }

                        if ("list" == q.type) {
                          q.optionList.forEach((o) => {
                            o.prompts = CnTranslationHelper.parseDescriptions(
                              this.evaluateDescription(o.rawPrompts)
                            );
                            o.popups = CnTranslationHelper.parseDescriptions(
                              this.evaluateDescription(o.rawPopups)
                            );
                          });
                        }

                        // q is visible, now check its options (assuming we haven't selected dkna/refused)
                        if (
                          "list" == q.type &&
                          !isDknaOrRefuse(q.value)
                        ) {
                          var visibleOptionList =
                            this.getVisibleOptionList(q);
                          q.optionList.forEach((o) => {
                            if (
                              null ==
                              visibleOptionList.findByProperty("id", o.id)
                            ) {
                              // o isn't visible so make sure it isn't selected
                              if (angular.isArray(q.value)) {
                                var i = searchOptionList(q.value, o.id);
                                if (null != i) {
                                  q.value.splice(i, 1);
                                  if (0 == q.value.length) q.value = null;
                                  this.convertValueToModel(q);
                                }
                              }
                            }
                          });
                        }
                      }
                    });

                    if (!noCompleteCheck) {
                      var complete = this.questionIsComplete(question);
                      question.incomplete = false === complete ?  true :
                        true === complete ?  false : complete;
                    }
                  } finally {
                    this.working = false;
                  }
                });
              }
              this.setAnswerInProgress = false;
            },

            getValueForNewOption: function (question, option) {
              var data = option.extra
                ? { id: option.id, value: option.multiple_answers ? [] : null }
                : option.id;
              var value = [];
              if (option.exclusive) {
                value.push(data);
              } else {
                // get the current value array, remove exclusive options, add the new option and sort
                if (angular.isArray(question.value)) value = question.value;
                value = value.filter(
                  (o) =>
                    !this.optionListById[angular.isObject(o) ? o.id : o]
                      .exclusive
                );
                if (null == searchOptionList(value, option.id))
                  value.push(data);
                value.sort(function (a, b) {
                  return (
                    (angular.isObject(a) ? a.id : a) -
                    (angular.isObject(b) ? b.id : b)
                  );
                });
              }

              return value;
            },

            addOption: async function (question, option) {
              await this.setAnswer(
                question,
                this.getValueForNewOption(question, option)
              );

              // if the option has extra data then focus its associated input
              if (null != option.extra) {
                await focusElement("option" + option.id + "value0");
              }
            },

            removeOption: async function (question, option) {
              // get the current value array and remove the option from it
              var value = angular.isArray(question.value) ? question.value : [];
              var optionIndex = searchOptionList(value, option.id);
              if (null != optionIndex) value.splice(optionIndex, 1);
              if (0 == value.length) value = null;

              await this.setAnswer(question, value);
            },

            addAnswerValue: async function (question, option) {
              var value = angular.isArray(question.value) ? question.value : [];
              var optionIndex = searchOptionList(value, option.id);
              if (null == optionIndex) {
                value = this.getValueForNewOption(question, option);
                optionIndex = searchOptionList(value, option.id);
              }

              var valueIndex = value[optionIndex].value.indexOf(null);
              if (-1 == valueIndex)
                valueIndex = value[optionIndex].value.push(null) - 1;
              await this.setAnswer(question, value, true);
              await focusElement("option" + option.id + "value" + valueIndex);
            },

            removeAnswerValue: async function (question, option, valueIndex) {
              var value = question.value;
              var optionIndex = searchOptionList(value, option.id);
              value[optionIndex].value.splice(valueIndex, 1);
              if (0 == value[optionIndex].value.length)
                value.splice(optionIndex, 1);
              if (0 == value.length) value = null;

              await this.setAnswer(question, value, true);
            },

            selectDateForQuestionOrOption: async function (question, option, valueIndex, value) {
              try {
                this.hotKeyDisabled = true;

                var response = await CnModalDatetimeFactory.instance({
                  title: null,
                  locale: this.currentLanguage,
                  date: value,
                  pickerType: "date",
                  minDate: getDate(this.evaluateLimit(null != option ? option.minimum : question.minimum)),
                  maxDate: getDate(this.evaluateLimit(null != option ? option.maximum : question.maximum)),
                  emptyAllowed: true,
                }).show();

                if (false !== response) {
                  if (null != option) {
                    await this.setAnswerValue(
                      question,
                      option,
                      valueIndex,
                      null == response ? null : response.replace(/T.*/, "")
                    );
                  } else {
                    await this.setAnswer(
                      question,
                      null == response ? null : response.replace(/T.*/, "")
                    );
                  }
                }
              } finally {
                this.hotKeyDisabled = false;
              }
            },

            selectDateForOption: async function (question, option, valueIndex, answerValue) {
              this.selectDateForQuestionOrOption(question, option, valueIndex, answerValue);
            },

            selectDate: async function (question, value) {
              this.selectDateForQuestionOrOption(question, null, null, value);
            },

            selectTimeForQuestionOrOption: async function (question, option, valueIndex, value) {
              try {
                this.hotKeyDisabled = true;

                // assume a default of 12:00
                if (value == null) value = "12:00";

                var response = await CnModalDatetimeFactory.instance({
                  title: null,
                  locale: this.currentLanguage,
                  // return the time in the user's timezone
                  date: getTime(value, CnSession.user.timezone),
                  pickerType: "time",
                  emptyAllowed: true,
                }).show();

                if (false !== response) {
                  if( null != response ) {
                    // convert the time from UTC to the user's timezone
                    const match = response.match(/^([0-9]+):([0-9]+)$/);
                    let m = moment();
                    m.hour(parseInt(match[1]));
                    m.minute(parseInt(match[2]));
                    m.second(0);
                    m.tz(CnSession.user.timezone);
                    response = moment(m).format("HH:mm");
                  }

                  await this.setAnswer( question, response );
                  if (null != option) {
                    await this.setAnswerValue( question, option, valueIndex, response );
                  } else {
                    await this.setAnswer( question, response );
                  }
                }
              } finally {
                this.hotKeyDisabled = false;
              }
            },

            selectTimeForOption: async function (question, option, valueIndex, answerValue) {
              this.selectTimeForQuestionOrOption(question, option, valueIndex, answerValue);
            },

            selectTime: async function (question, value) {
              this.selectTimeForQuestionOrOption(question, null, null, value);
            },

            setAnswerValue: async function (
              question,
              option,
              valueIndex,
              answerValue
            ) {
              // if the question option's extra type is a number then make sure it falls within the min/max values
              const minimum = this.evaluateLimit(option.minimum);
              const maximum = this.evaluateLimit(option.maximum);
              const tooSmall = isTooSmall(option.extra, minimum, answerValue);
              const tooLarge = isTooLarge(option.extra, maximum, answerValue);

              if (tooSmall || tooLarge) {
                await this.runQuery(async () => {
                  await CnModalMessageFactory.instance({
                    title: this.text(
                      tooSmall ? "misc.minimumTitle" : "misc.maximumTitle"
                    ),
                    message:
                      this.text("misc.limitMessage") +
                      " " +
                      (null == maximum
                        ? this.text("misc.equalOrGreater") + " " + minimum + "."
                        : null == minimum
                        ? this.text("misc.equalOrLess") + " " + maximum + "."
                        : [
                            this.text("misc.between"),
                            minimum,
                            this.text("misc.and"),
                            maximum + ".",
                          ].join(" ")),
                  }).show();

                  // put the old value back
                  var element = document.getElementById(
                    "option" + option.id + "value" + valueIndex
                  );
                  element.value =
                    question.answer.optionList[option.id].valueList[valueIndex];
                });
              } else {
                var value = question.value;
                var optionIndex = searchOptionList(value, option.id);
                if (null != optionIndex) {
                  if (option.multiple_answers) {
                    if (
                      null == answerValue ||
                      ( angular.isString(answerValue) && 0 == answerValue.trim().length )
                    ) {
                      // if the value is blank then remove it
                      value[optionIndex].value.splice(valueIndex, 1);
                    } else {
                      // does the value already exist?
                      var existingValueIndex = value[optionIndex].value.indexOf(answerValue);
                      if (0 <= existingValueIndex) {
                        // don't add the answer, instead focus on the existing one and highlight it
                        document.getElementById(
                          "option" + option.id + "value" + valueIndex
                        ).value = null;
                        var element = document.getElementById(
                          "option" + option.id + "value" + existingValueIndex
                        );
                        element.focus();
                        element.select();
                      } else {
                        value[optionIndex].value[valueIndex] = answerValue;
                      }
                    }
                  } else {
                    value[optionIndex].value = "" !== answerValue ? answerValue : null;
                    if ("date" == option.extra) {
                      question.answer.optionList[option.id].formattedValueList[valueIndex] =
                        formatDate(value[optionIndex].value);
                    } else if ("time" == option.extra) {
                      question.answer.optionList[option.id].formattedValueList[valueIndex] =
                        formatTime(value[optionIndex].value);
                    } else if ("number with unit") {
                      if (angular.isUndefined(value[optionIndex].value.value)) {
                        value[optionIndex].value.value = null;
                      }
                      if (angular.isUndefined(value[optionIndex].value.unit)) {
                        value[optionIndex].value.unit = null;
                      }
                    }
                  }
                }

                await this.setAnswer(question, value);
              }
            },

            viewPage: async function () {
              await $state.go(
                "page.view",
                {
                  identifier: this.parentModel.viewModel.record.getIdentifier(),
                },
                { reload: true }
              );
            },

            showDisplayResponseButton: function () {
              return this.parentModel.isRole( "interviewer", "administrator" );
            },

            transitionToDisplayResponse: function () {
              $state.go(
                "response.display",
                { identifier: this.data.response_id },
              );
            },

            setResponseComments: async function () {
              try {
                this.working = true;
                await this.runQuery(async () => {
                  await CnHttpFactory.instance({
                    path: "response/" + this.data.response_id,
                    data: { comments: this.data.comments },
                  }).patch();
                });
              } finally {
                this.working = false;
              }
            },

            showStageComments: async function (responseStageId) {
              // if no ID is provided then assume the currently active one
              if (!responseStageId)
                responseStageId = this.data.response_stage_id;
              var responseStage = this.responseStageList.findByProperty(
                "id",
                responseStageId
              );
              var response = await CnModalTextFactory.instance({
                title: responseStage.name + " Comments",
                message:
                  "Please provide any relevant comments about this stage:",
                text: responseStage.comments,
                size: "lg",
              }).show();

              if (false !== response) {
                try {
                  this.working = true;
                  await this.runQuery(async () => {
                    await CnHttpFactory.instance({
                      path: "response_stage/" + responseStageId,
                      data: { comments: response },
                    }).patch();
                    responseStage.comments = response;
                  });
                } finally {
                  this.working = false;
                }
              }
            },

            isStageOperationEnabled: function (responseStageId, operationName) {
              var enabled = true;
              if (["skip", "launch"].includes(operationName)) {
                // if there are no deviation types for the skip or launch operation then disable that operation
                var deviation = this.responseStageList
                  .findByProperty("id", responseStageId)
                  .operations.findByProperty("name", operationName)
                  .getDeviation();
                enabled =
                  null == deviation ||
                  0 <
                    this.deviationTypeList.filter((dt) => deviation == dt.type)
                      .length;
              }
              return enabled;
            },

            runStageOperation: async function (responseStageId, operationName) {
              // if no ID is provided then assume the currently active one
              if (!responseStageId)
                responseStageId = this.data.response_stage_id;
              var responseStage = this.responseStageList.findByProperty(
                "id",
                responseStageId
              );

              if (!["launch", "pause", "skip", "reset"].includes(operationName))
                throw new Error(
                  'Tried to run invalid stage operation "' + operationName + '"'
                );

              // determine if the stage is already open and which user it is associated with
              var responseStageResponse = await CnHttpFactory.instance({
                path: "response_stage/" + responseStageId,
                data: {
                  select: {
                    column: [
                      "status",
                      "user_id",
                      { table: "user", column: "first_name" },
                      { table: "user", column: "last_name" },
                    ],
                  },
                },
              }).get();

              var data = responseStageResponse.data;
              var warning = null;

              if (
                ["not ready", "not applicable", "parent skipped"].includes(data.status) ||
                ("skipped" == data.status && "reset" != operationName) ||
                ("ready" == data.status && "reset" == operationName) ||
                ("completed" == data.status && "skip" == operationName)
              ) {
                // the stage's current status and requested operation don't make sense, reload the page
                await CnModalMessageFactory.instance({
                  title: "Please Note",
                  message:
                    "The interview has changed and must be reloaded before you can proceed. " +
                    "Once you close this message the interview data will automatically be refreshed.",
                  error: true,
                }).show();
                await this.onReady();
                return;
              } else if ("active" == data.status) {
                warning =
                  "<b>WARNING</b>: This stage is already active " +
                  (data.user_id != CnSession.user.id
                    ? "by another user (" +
                      data.first_name +
                      " " +
                      data.last_name +
                      ")"
                    : "under your account.");
              } else if (
                "paused" == data.status ||
                ("completed" == data.status && "launch" == operationName)
              ) {
                // warn if this is a new user
                if (data.user_id != CnSession.user.id)
                  warning =
                    "<b>WARNING</b>: This stage was paused by another user (" +
                    data.first_name +
                    " " +
                    data.last_name +
                    ")";
              }

              var patchData = null;
              var proceed = true;
              if ("reset" == operationName) {
                var response = await CnModalConfirmFactory.instance({
                  message:
                    "Are you sure you wish to reset this stage?<br><br>" +
                    '<b class="text-danger">Note that by proceeding all data collected during the stage will be deleted.' +
                    (warning ? "<br><br>" + warning : "") +
                    "</b>",
                  html: true,
                }).show();
                proceed = response;
              } else if (["skip", "launch"].includes(operationName)) {
                proceed = true;

                // check if we have to ask for the reason for deviation
                var deviation = responseStage.operations
                  .findByProperty("name", operationName)
                  .getDeviation();

                // only run the pre-stage check if there is a deviation we we have to check the token
                if(deviation || this.data.token_check) {
                  proceed = false;
                  // now show the pre-stage dialog
                  var response = await CnModalPreStageFactory.instance({
                    title: responseStage.name + ": " + operationName.ucWords(),
                    warning: warning,
                    deviationTypeList: deviation
                      ? this.deviationTypeList.filter((dt) => deviation == dt.type)
                      : null,
                    validToken: $state.params.token,
                    // if we're not checking the token then set it now so the user doesn't have to
                    token: this.data.token_check ? null : $state.params.token,
                    tokenReadOnly: !this.data.token_check,
                    deviationTypeId: responseStage.deviation_type_id,
                    deviationComments: responseStage.deviation_comments,
                    comments: responseStage.comments,
                  }).show();

                  if (null != response) {
                    patchData = response;
                    proceed = true;
                  }
                }
              }

              if (proceed) {
                try {
                  this.working = true;
                  await this.runQuery(async () => {
                    var httpObj = { path: "response_stage/" + responseStageId + "?action=" + operationName, };
                    if (null != patchData) {
                      httpObj.data = patchData;
                      // update the client with any changes
                      angular.extend(responseStage, patchData);
                    }

                    // update the server with any changes
                    await CnHttpFactory.instance(httpObj).patch();
                    await this.parentModel.reloadState(true);
                  });
                } finally {
                  this.working = false;
                }
              }
            },

            proceed: async function () {
              const record = this.parentModel.viewModel.record;
              if (this.previewMode) {
                await $state.go(
                  "page.render",
                  { identifier: record.next_id },
                  { reload: true }
                );
              } else {
                var modal = CnModalMessageFactory.instance({
                  title: this.text("misc.submitWaitTitle"),
                  message: this.text("misc.submitWaitMessage"),
                  block: true,
                });

                try {
                  this.working = true;

                  // check to make sure that all questions are complete, and highlight any which aren't
                  var mayProceed = true;
                  this.questionList.some((question) => {
                    var complete = this.questionIsComplete(question);
                    question.incomplete =
                      false === complete
                        ? true
                        : true === complete
                        ? false
                        : complete;
                    if (question.incomplete) {
                      mayProceed = false;
                      return true;
                    }
                  });

                  if (mayProceed) {
                    // proceed to the respondent's next valid page
                    await this.runQuery(async () => {
                      if (null === record.next_id) modal.show(); // show a wait dialog when submitting the qnaire

                      let path = "respondent/token=" + $state.params.token + "?action=proceed";
                      if( null != this.username ) path += "&username=" + this.username;
                      await CnHttpFactory.instance({ path: path }).patch();
                      await this.parentModel.reloadState(true);
                    });
                  }
                } finally {
                  if (null === record.next_id) modal.close(); // close the wait dialog when submitted the qnaire
                  this.working = false;
                }
              }
            },

            backup: async function () {
              if (this.previewMode) {
                await $state.go(
                  "page.render",
                  { identifier: this.parentModel.viewModel.record.previous_id },
                  { reload: true }
                );
              } else {
                try {
                  // back up to the respondent's previous page
                  this.working = true;
                  await this.runQuery(async () => {
                    await CnHttpFactory.instance({
                      path: "respondent/token=" + $state.params.token + "?action=backup",
                    }).patch();
                    await this.parentModel.reloadState(true);
                  });
                } finally {
                  this.working = false;
                }
              }
            },

            jump: async function (moduleId) {
              try {
                // jump to the first page in the provided module
                this.working = true;
                await this.runQuery(async () => {
                  await CnHttpFactory.instance({
                    path: "respondent/token=" + $state.params.token + "?action=jump&module_id=" + moduleId,
                  }).patch();
                  await this.parentModel.reloadState(true);
                });
              } finally {
                this.working = false;
              }
            },

            fastForwardStage: async function() {
              try {
                // jump to the first page in the stage
                this.working = true;
                await this.runQuery(async () => {
                  await CnHttpFactory.instance({
                    path: "respondent/token=" + $state.params.token + "?action=fast_forward_stage",
                  }).patch();
                  await this.parentModel.reloadState(true);
                });
              } finally {
                this.working = false;
              }
            },

            rewindStage: async function() {
              try {
                // jump to the last answered page in the stage
                this.working = true;
                await this.runQuery(async () => {
                  await CnHttpFactory.instance({
                    path: "respondent/token=" + $state.params.token + "?action=rewind_stage",
                  }).patch();
                  await this.parentModel.reloadState(true);
                });
              } finally {
                this.working = false;
              }
            },

            onDigitHotKey: async function (digit) {
              var question = this.questionList.findByProperty(
                "id",
                this.focusQuestionId
              );
              if (null == question) return;

              var value = undefined;
              if ("boolean" == question.type) {
                if (1 == digit) {
                  value = question.answer.yes ? null : true;
                } else if (2 == digit) {
                  value = question.answer.no ? null : false;
                } else if (3 == digit) {
                  value = "dkna_refuse_1";
                } else if (4 == digit) {
                  value = "dkna_refuse_2";
                }
              } else if ("list" == question.type) {
                var optionList = this.getVisibleOptionList(question);

                if (digit <= optionList.length) {
                  // add or remove the option, depending on whether it is currently selected
                  value = optionList[digit - 1];
                } else {
                  if (digit == optionList.length + 1) {
                    value = "dkna_refuse_1";
                  } else if (digit == optionList.length + 2) {
                    value = "dkna_refuse_2";
                  }
                }
              } else {
                if (1 == digit) {
                  if ("date" == question.type) {
                    // show the date selection modal
                    await this.selectDate(question, question.answer.value);
                  } else if ("time" == question.type) {
                    // show the time selection modal
                    await this.selectTime(question, question.answer.value);
                  } else {
                    // simply focus on the question's input box
                    await focusElement("question" + question.id);
                  }
                } else if (2 == digit) {
                  value = "dkna_refuse_1";
                } else if (3 == digit) {
                  value = "dkna_refuse_2";
                }
              }

              // if value is set the update the answer
              if (angular.isDefined(value)) {
                var setAnswerTo = undefined;
                if (angular.isObject(value)) {
                  // we've selected an option
                  var option = value;
                  if (option.multiple_answers) {
                    await this.addAnswerValue(question, option);
                  } else if (question.answer.optionList[option.id].selected) {
                    await this.removeOption(question, option);
                  } else {
                    await this.addOption(question, option);
                  }
                } else if (angular.isString(value)) {
                  // we've either selected the first or second dkna/refuse option
                  var match = value.match(/^dkna_refuse_([0-9])/);
                  if (match) {
                    if (1 == match[1]) {
                      // the first dkna/refuse option
                      if (question.dkna_allowed)
                        setAnswerTo = question.answer.dkna
                          ? null
                          : { dkna: true };
                      else if (question.refuse_allowed)
                        setAnswerTo = question.answer.refuse
                          ? null
                          : { refuse: true };
                    } else if (2 == match[1]) {
                      // the second dkna/refuse option
                      if (question.dkna_allowed && question.refuse_allowed)
                        setAnswerTo = question.answer.refuse
                          ? null
                          : { refuse: true };
                    }
                  }
                } else {
                  // we've set a boolean value
                  setAnswerTo = value;
                }

                if (angular.isDefined(setAnswerTo))
                  await this.setAnswer(question, setAnswerTo);
              }
            },

            // set prev to true to focus on previous question, otherwise focus on the next one
            focusQuestion: function (prev) {
              if (angular.isUndefined(prev)) prev = true;

              var requestedIndex = null;
              var questionList = this.getFocusableQuestionList();
              if (null == this.focusQuestionId) {
                // focus on the last/first question if no question is currently selected
                if (0 < questionList.length)
                  requestedIndex = prev ? questionList.length - 1 : 0;
              } else {
                // try to focus on the prev/next question, if there is one
                var index = questionList.findIndexByProperty(
                  "id",
                  this.focusQuestionId
                );
                var requestedIndex = prev ? index - 1 : index + 1;
              }

              if (angular.isDefined(questionList[requestedIndex])) {
                var question = questionList[requestedIndex];
                this.focusQuestionId = question.id;

                // scroll so that the bottom of the div is visible
                var element = document.getElementById(
                  "baseQuestion" + question.id
                );
                if (null != element) {
                  element.scrollTop += 100;
                  element.scrollIntoView(false);
                }
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

    // extend the add factory created by calling initQnairePartModule()
    cenozo.providers.decorator("CnPageAddFactory", [
      "$delegate",
      "CnSession",
      function ($delegate, CnSession) {
        var instance = $delegate.instance;
        $delegate.instance = function (parentModel) {
          var object = instance(parentModel);

          // see if the form has a record in the data-entry module
          object.baseOnNewFn = object.onNew;
          angular.extend(object, {
            onNew: async function (record) {
              await this.baseOnNewFn(record);

              // set the default page max time
              if (angular.isUndefined(record.max_time))
                record.max_time = CnSession.setting.defaultPageMaxTime;
            },
          });

          return object;
        };

        return $delegate;
      },
    ]);

    // extend the view factory created by calling initQnairePartModule()
    cenozo.providers.decorator("CnPageViewFactory", [
      "$delegate",
      "$filter",
      "CnTranslationHelper",
      function ($delegate, $filter, CnTranslationHelper) {
        var instance = $delegate.instance;
        $delegate.instance = function (parentModel, root) {
          var object = instance(parentModel, root);

          // see if the form has a record in the data-entry module
          angular.extend(object, {
            onView: async function (force) {
              await this.$$onView(force);
              this.record.average_time = $filter("cnSeconds")(
                Math.round(this.record.average_time)
              );
              this.record.prompts = CnTranslationHelper.parseDescriptions(
                this.record.prompts
              );
              this.record.popups = CnTranslationHelper.parseDescriptions(
                this.record.popups
              );
              this.record.module_prompts =
                CnTranslationHelper.parseDescriptions(
                  this.record.module_prompts
                );
              this.record.module_popups = CnTranslationHelper.parseDescriptions(
                this.record.module_popups
              );
            },
          });

          return object;
        };

        return $delegate;
      },
    ]);

    // extend the base model factory created by calling initQnairePartModule()
    cenozo.providers.decorator("CnPageModelFactory", [
      "$delegate",
      "CnPageRenderFactory",
      "$state",
      function ($delegate, CnPageRenderFactory, $state) {
        function extendModelObject(object) {
          angular.extend(object, {
            renderModel: CnPageRenderFactory.instance(object),

            getServiceResourceBasePath: function (resource) {
              // when we're looking at a respondent use its token to figure out which page to load
              return "respondent" == this.getSubjectFromState()
                ? "page/token=" + $state.params.token
                : this.$$getServiceResourcePath(resource);
            },

            getServiceResourcePath: function (resource) {
              return this.getServiceResourceBasePath(resource);
            },

            getServiceCollectionPath: function (ignoreParent) {
              var path = this.$$getServiceCollectionPath(ignoreParent);
              if ("respondent" == this.getSubjectFromState())
                path = path.replace(
                  "respondent/undefined",
                  "module/token=" + $state.params.token
                );
              return path;
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
