"use strict";

var cenozo = angular.module("cenozo");

cenozo.controller("HeaderCtrl", [
  "$scope",
  "CnBaseHeader",
  async function ($scope, CnBaseHeader) {
    // copy all properties from the base header
    await CnBaseHeader.construct($scope);
  },
]);

/* ############################################################################################## */
cenozo.directive("cnDescriptionHelp", [
  function () {
    return {
      templateUrl: cenozoApp.getFileUrl("pine", "description_help.tpl.html"),
      restrict: "E",
      scope: { model: "=" },
    };
  },
]);

/* ############################################################################################## */
cenozo.directive("cnDescriptionPatch", [
  function () {
    return {
      templateUrl: cenozoApp.getFileUrl("pine", "description_patch.tpl.html"),
      restrict: "E",
      scope: { model: "=" },
    };
  },
]);

/* ############################################################################################## */
cenozo.directive("cnQnairePartPatch", [
  function () {
    return {
      templateUrl: cenozoApp.getFileUrl("pine", "qnaire_part_patch.tpl.html"),
      restrict: "E",
      scope: {
        model: "=",
        subject: "@",
      },
      controller: function ($scope) {
        if ("module" == $scope.subject) $scope.childSubject = "page";
        else if ("page" == $scope.subject) $scope.childSubject = "question";
        else if ("question" == $scope.subject) $scope.childSubject = "question_option";
        else if ("device" == $scope.subject) $scope.childSubject = "device_data";
        else if ("qnaire_report" == $scope.subject) $scope.childSubject = "qnaire_report_data";
        else $scope.childSubject = null;
      },
    };
  },
]);

/* ############################################################################################## */
cenozoApp.initQnairePartModule = function (module, type) {
  var columnList = {
    rank: { column: type+".rank", title: "Rank", type: "rank" },
    name: { column: type+".name", title: "Name" },
  };

  var childType = null;
  if ("module" == type) {
    childType = "page";
    columnList.page_count = { title: "Pages" };
  } else if ("page" == type) {
    childType = "question";
    columnList.question_count = { title: "Questions" };
  } else if ("question" == type) {
    childType = "question_option";
    columnList.question_option_count = { title: "Question Options" };
  }
  columnList.precondition = {
    column: type + '.precondition',
    title: "Precondition",
  };

  angular.extend(module, {
    identifier: {},
    name: {
      singular: type.replace(/_/g, " "),
      plural: type.replace(/_/g, " ") + "",
      possessive: type.replace(/ /g, " ") + "'s",
    },
    columnList: columnList,
    defaultOrder: {
      column: "rank",
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
      format: "module" == type ? "identifier" : "question_option" == type ? "alpha_num" : "",
      regex: ["page","question"].includes(type) ? "^[a-zA-Z_][a-zA-Z0-9_]*$" : "",
    },
    precondition: {
      title: "Precondition",
      type: "text",
      help:
        "An expression which restricts whether or not to show this " +
        type.replace(/_/g, " ") +
        ".",
    },
    description: {
      title: "Description",
      type: "text",
      help: "The description in the questionnaire's language.",
      isExcluded: "view",
    },
    readonly: { column: "qnaire.readonly", type: "hidden" },
  });

  module.addInput("", "previous_id", { isExcluded: true });
  module.addInput("", "next_id", { isExcluded: true });

  module.addExtraOperation("view", {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: "btn-info",
    operation: function ($state, model) {
      model.viewModel.viewPrevious();
    },
    isDisabled: function ($state, model) {
      return (
        model.viewModel.navigating || null == model.viewModel.record.previous_id
      );
    },
  });

  module.addExtraOperation("view", {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: "btn-info",
    operation: function ($state, model) {
      model.viewModel.viewNext();
    },
    isDisabled: function ($state, model) {
      return (
        model.viewModel.navigating || null == model.viewModel.record.next_id
      );
    },
  });

  module.addExtraOperation("view", {
    title: "Move/Copy",
    operation: async function ($state, model) {
      await $state.go(type + ".clone", {
        identifier: model.viewModel.record.getIdentifier(),
      });
    },
  });

  var typeCamel = type.snakeToCamel().ucWords();

  /* ############################################################################################## */
  cenozo.providers.directive("cn" + typeCamel + "View", [
    "Cn" + typeCamel + "ModelFactory",
    "$document",
    "$transitions",
    function (CnModelFactory, $document, $transitions) {
      return {
        templateUrl: module.getFileUrl("view.tpl.html"),
        restrict: "E",
        scope: { model: "=?" },
        controller: function ($scope) {
          if (angular.isUndefined($scope.model))
            $scope.model = CnModelFactory.root;

          // bind keyup (first unbind to prevent duplicates)
          $document.unbind("keyup");
          $document.bind("keyup", function (event) {
            // don't process hotkeys when we're focussed on input-based UI elements
            if (
              !["input", "select", "textarea"].includes(event.target.localName)
            ) {
              event.stopPropagation();
              if (37 == event.which) {
                if (null != $scope.model.viewModel.record.previous_id)
                  $scope.model.viewModel.viewPrevious();
              } else if (39 == event.which) {
                if (null != $scope.model.viewModel.record.next_id)
                  $scope.model.viewModel.viewNext();
              }
            }
          });
          $transitions.onExit(
            {},
            function (transition) {
              $document.unbind("keyup");
            },
            { invokeLimit: 1 }
          );
        },
      };
    },
  ]);

  /* ############################################################################################## */
  cenozo.providers.factory("Cn" + typeCamel + "AddFactory", [
    "CnBaseAddFactory",
    "CnHttpFactory",
    function (CnBaseAddFactory, CnHttpFactory) {
      var object = function (parentModel) {
        CnBaseAddFactory.construct(this, parentModel);

        // get the parent's name for the breadcrumb trail
        angular.extend(this, {
          // transition to viewing the new record instead of the default functionality
          transitionOnSave: function (record) {
            parentModel.transitionToViewState(record);
          },

          onNew: async function (record) {
            await this.$$onNew(record);

            // get the parent page's name
            this.parentName = null;
            var parentIdentifier = parentModel.getParentIdentifier();
            if (angular.isDefined(parentIdentifier.subject)) {
              var response = await CnHttpFactory.instance({
                path:
                  parentIdentifier.subject + "/" + parentIdentifier.identifier,
                data: { select: { column: "name" } },
              }).get();

              this.parentName = response.data.name;
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
  cenozo.providers.factory("Cn" + typeCamel + "ViewFactory", [
    "CnBaseViewFactory",
    "CnBaseQnairePartViewFactory",
    function (CnBaseViewFactory, CnBaseQnairePartViewFactory) {
      var object = function (parentModel, root) {
        CnBaseViewFactory.construct(this, parentModel, root, childType);
        CnBaseQnairePartViewFactory.construct(this, type);
      };
      return {
        instance: function (parentModel, root) {
          return new object(parentModel, root);
        },
      };
    },
  ]);

  /* ############################################################################################## */
  cenozo.providers.factory("Cn" + typeCamel + "ModelFactory", [
    "CnBaseModelFactory",
    "Cn" + typeCamel + "AddFactory",
    "Cn" + typeCamel + "ListFactory",
    "Cn" + typeCamel + "ViewFactory",
    "CnHttpFactory",
    function (
      CnBaseModelFactory,
      CnAddFactory,
      CnListFactory,
      CnViewFactory,
      CnHttpFactory
    ) {
      var object = function (root) {
        CnBaseModelFactory.construct(this, module);
        this.addModel = CnAddFactory.instance(this);
        this.listModel = CnListFactory.instance(this);
        this.viewModel = CnViewFactory.instance(this, root);

        this.getBreadcrumbParentTitle = function () {
          return "view" == this.getActionFromState()
            ? this.viewModel.record.parent_name
            : this.addModel.parentName;
        };

        // extend getMetadata
        this.getMetadata = async function () {
          await this.$$getMetadata();

          // setup non-record description input
          var response = await CnHttpFactory.instance({
            path: type + "_description",
          }).head();

          var columnList = angular.fromJson(response.headers("Columns"));
          columnList.value.required = "1" == columnList.value.required;
          if (angular.isUndefined(this.metadata.columnList.description))
            this.metadata.columnList.description = {};
          angular.extend(
            this.metadata.columnList.description,
            columnList.value
          );
        };

        // extend getEditEnabled and getDeleteEnabled based on the parent qnaire readonly column
        this.getEditEnabled = function () {
          return !this.viewModel.record.readonly && this.$$getEditEnabled();
        };
        this.getDeleteEnabled = function () {
          return !this.viewModel.record.readonly && this.$$getDeleteEnabled();
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
};

/* ############################################################################################## */
cenozoApp.initDescriptionModule = function (module, type) {
  angular.extend(module, {
    identifier: {
      parent: {
        subject: type,
        column: type + ".id",
      },
    },
    name: {
      singular: "description",
      plural: "descriptions",
      possessive: "description's",
    },
    columnList: {
      language: {
        column: "language.code",
        title: "Language",
      },
      type: {
        title: "Type",
      },
      value: {
        title: "Value",
        align: "left",
      },
    },
    defaultOrder: {
      column: "language.code",
      reverse: false,
    },
  });

  module.addInputGroup("", {
    language: {
      title: "Language",
      column: "language.code",
      type: "string",
      isConstant: true,
    },
    type: {
      title: "Type",
      type: "enum",
      isConstant: true,
    },
    value: {
      title: "Value",
      type: "text",
    },

    previous_description_id: { isExcluded: true },
    next_description_id: { isExcluded: true },
    readonly: { column: "qnaire.readonly", type: "hidden" },
  });

  module.addExtraOperation("view", {
    title: '<i class="glyphicon glyphicon-chevron-left"></i>',
    classes: "btn-info",
    operation: async function ($state, model) {
      await model.viewModel.viewPreviousDescription();
    },
    isDisabled: function ($state, model) {
      return (
        model.viewModel.navigating ||
        null == model.viewModel.record.previous_description_id
      );
    },
  });

  module.addExtraOperation("view", {
    title: '<i class="glyphicon glyphicon-chevron-right"></i>',
    classes: "btn-info",
    operation: async function ($state, model) {
      await model.viewModel.viewNextDescription();
    },
    isDisabled: function ($state, model) {
      return (
        model.viewModel.navigating ||
        null == model.viewModel.record.next_description_id
      );
    },
  });
};

/* ############################################################################################## */
cenozo.factory("CnBaseQnairePartViewFactory", [
  "$state",
  function ($state) {
    return {
      construct: function (object, type) {
        angular.extend(object, {
          navigating: false,
          viewPrevious: async function () {
            if (!this.navigating && this.record.previous_id) {
              try {
                this.navigating = true;
                await $state.go(
                  type + ".view",
                  { identifier: this.record.previous_id },
                  { reload: true }
                );
              } finally {
                object.navigating = false;
              }
            }
          },
          viewNext: async function () {
            if (!this.navigating && this.record.next_id) {
              try {
                this.navigating = true;
                await $state.go(
                  type + ".view",
                  { identifier: this.record.next_id },
                  { reload: true }
                );
              } finally {
                object.navigating = false;
              }
            }
          },
        });
      },
    };
  },
]);

/* ############################################################################################## */
cenozo.factory("CnBaseDescriptionViewFactory", [
  "$state",
  function ($state) {
    return {
      construct: function (object, type) {
        angular.extend(object, {
          navigating: false,
          viewPreviousDescription: async function () {
            if (!this.navigating && this.record.previous_description_id) {
              try {
                this.navigating = true;
                await $state.go(
                  type + "_description.view",
                  { identifier: this.record.previous_description_id },
                  { reload: true }
                );
              } finally {
                this.navigating = false;
              }
            }
          },
          viewNextDescription: async function () {
            if (!this.navigating && this.record.next_description_id) {
              try {
                this.navigating = true;
                await $state.go(
                  type + "_description.view",
                  { identifier: this.record.next_description_id },
                  { reload: true }
                );
              } finally {
                this.navigating = false;
              }
            }
          },
        });
      },
    };
  },
]);

/* ############################################################################################## */
cenozo.directive("cnQnaireNavigator", [
  "CnHttpFactory",
  "$state",
  function (CnHttpFactory, $state) {
    return {
      templateUrl: cenozoApp.getFileUrl("pine", "qnaire_navigator.tpl.html"),
      restrict: "E",
      controller: async function ($scope) {
        // used to navigate to another qnaire part (either root or description)
        async function viewQnairePart(subject, id) {
          var keys = null;
          if (subject + "_description.view" == $state.current.name) {
            var languageMatch = $state.params.identifier.match(/language_id=([0-9]+)/);
            var typeMatch = $state.params.identifier.match(/type=([a-z]+)/);
            if (null == languageMatch || null == typeMatch) {
              var response = await CnHttpFactory.instance({
                path: subject + "_description/" + $state.params.identifier,
                data: { select: { column: ["language_id", "type"] } },
              }).get();

              keys = response.data;
            } else {
              keys = {
                language_id: languageMatch[1],
                type: typeMatch[1],
              };
            }
          }

          // if we are returned description keys then use them to navigate to the sister description
          const action = "question" == subject ? $state.current.name.split(".").pop() : "view";
          const identifier = null != keys ?
            [subject + "_id=" + id, "language_id=" + keys.language_id, "type=" + keys.type].join(";") : id;
          await $state.go(
            (null != keys ? subject + "_description" : subject) + "." + action,
            { identifier: identifier },
            { reload: true }
          );
        }

        angular.extend($scope, {
          loading: true,
          subject: $state.current.name.split(".")[0],
          currentQnaire: null,
          currentModule: null,
          currentPage: null,
          currentQuestion: null,
          qnaireList: [],
          moduleList: [],
          pageList: [],
          questionList: [],

          viewQnaire: async function (id) {
            await $state.go("qnaire.view", { identifier: id }, { reload: true });
          },
          viewModule: async function (id) {
            await viewQnairePart("module", id);
          },
          viewPage: async function (id) {
            await viewQnairePart("page", id);
          },
          viewQuestion: async function (id) {
            await viewQnairePart("question", id);
          },
        });

        // fill in the qnaire, module, page and question data
        var columnList = [
          { table: "qnaire", column: "id", alias: "qnaire_id" },
          { table: "qnaire", column: "name", alias: "qnaire_name" },
        ];

        var moduleDetails = false;
        var pageDetails = false;
        var questionDetails = false;

        if (["question", "question_description"].includes($scope.subject)) {
          moduleDetails = true;
          pageDetails = true;
          questionDetails = true;
        } else if (["page", "page_description"].includes($scope.subject)) {
          moduleDetails = true;
          pageDetails = true;
        } else if (["module", "module_description"].includes($scope.subject)) {
          moduleDetails = true;
        } else if (["qnaire", "qnaire_description"].includes($scope.subject)) {
        }

        // if we're looking at a module, page or question then get the module's details
        if (moduleDetails) {
          columnList.push(
            { table: "module", column: "id", alias: "module_id" },
            { table: "module", column: "rank", alias: "module_rank" },
            { table: "module", column: "name", alias: "module_name" }
          );
        }

        // if we're looking at a page or question then get the page's details
        if (pageDetails) {
          columnList.push(
            { table: "page", column: "id", alias: "page_id" },
            { table: "page", column: "rank", alias: "page_rank" },
            { table: "page", column: "name", alias: "page_name" }
          );
        }

        // if we're looking at a question then get the question's details
        if (questionDetails) {
          columnList.push(
            { table: "question", column: "id", alias: "question_id" },
            { table: "question", column: "rank", alias: "question_rank" },
            { table: "question", column: "name", alias: "question_name" }
          );
        }

        var response = await CnHttpFactory.instance({
          path: $scope.subject + "/" + $state.params.identifier,
          data: { select: { column: columnList } },
        }).get();

        $scope.currentQnaire = {
          id: response.data.qnaire_id ? response.data.qnaire_id : response.data.id,
          name: response.data.qnaire_name ? response.data.qnaire_name : response.data.name,
        };

        if (moduleDetails) {
          $scope.currentModule = {
            id: response.data.module_id,
            rank: response.data.module_rank,
            name: response.data.module_name,
          };
        }

        if (pageDetails) {
          $scope.currentPage = {
            id: response.data.page_id,
            rank: response.data.page_rank,
            name: response.data.page_name,
          };
        }

        if (questionDetails) {
          $scope.currentQuestion = {
            id: response.data.question_id,
            rank: response.data.question_rank,
            name: response.data.question_name,
          };
        }

        // get the list of qnaires, modules, pages and questions (depending on what we're looking at)
        var response = await CnHttpFactory.instance({
          path: "qnaire",
          data: {
            select: { column: ["id", "name"] },
            modifier: { order: "name", limit: 1000 },
          },
        }).query();
        $scope.qnaireList = response.data;

        var response = await CnHttpFactory.instance({
          path: ["qnaire", $scope.currentQnaire.id, "module"].join("/"),
          data: {
            select: { column: ["id", "rank", "name"] },
            modifier: { order: "rank", limit: 1000 },
          },
        }).query();
        $scope.moduleList = response.data;

        if ($scope.currentModule) {
          var response = await CnHttpFactory.instance({
            path: ["module", $scope.currentModule.id, "page"].join("/"),
            data: {
              select: { column: ["id", "rank", "name"] },
              modifier: { order: "rank", limit: 1000 },
            },
          }).query();
          $scope.pageList = response.data;
        }

        if ($scope.currentPage) {
          var response = await CnHttpFactory.instance({
            path: ["page", $scope.currentPage.id, "question"].join("/"),
            data: {
              select: { column: ["id", "rank", "name"] },
              modifier: { order: "rank", limit: 1000 },
            },
          }).query();
          $scope.questionList = response.data;
        }
      },
    };
  },
]);

/* ############################################################################################## */
cenozo.service("CnTranslationHelper", [
  "$filter",
  function ($filter) {
    return {
      translate: function (address, language) {
        var addressParts = address.split(".");

        function get(array, index) {
          if (angular.isUndefined(index)) index = 0;
          var part = addressParts[index];
          return angular.isUndefined(array[part])
            ? "ERROR"
            : angular.isDefined(array[part][language])
            ? array[part][language]
            : angular.isDefined(array[part].en)
            ? array[part].en
            : get(array[part], index + 1);
        }

        return get(this.lookupData);
      },
      lookupData: {
        hotKey: {
          title: {
            en: "Hot-key Hints",
            fr: "Raccourcis clavier",
          },
          show: {
            en: "Show Hot-key Hints",
            fr: "Afficher les raccourcis clavier",
          },
          hide: {
            en: "Hide Hot-key Hints",
            fr: "Masquer les raccourcis clavier",
          },
          body: {
            en:
              '<li>Navigation "-" and "+" hot-keys (keyboard or numpad) moves page backward and forward, respectively</li>' +
              '<li>Navigation "[" and "]" hot-keys moves focused question backward and forward, respectively (When page is first loaded, no question is focused)</li>' +
              '<li>Focused question is highlighted in pale yellow and numeric options identified by a number in square brackets (e.g.: "[1]")</li>' +
              '<li>Numeric hot-keys "1" through "9" and "0" (numpad not included) will do the following based on the question type which is currently focused:</li>' +
              "<ul>" +
              "<li>List: the option will be toggled (off to on, or on to off) - if extra details are required then the input box will automatically be given focus</li>" +
              "<li>Number/String/Text: the input box is focused</li>" +
              "<li>Date/Time: the date/time picker will show</li>" +
              "</ul>" +
              "<li>Holding down the SHIFT key will allow selection of items 11 through 20</li>" +
              "<li>When an input box has focus the enter key must be pressed to leave focus and re-activate hot-keys</li>" +
              "<li>All hot-keys are deactivated when no question is focused, or when focus is in an input box</li>" +
              "<li>Hot-keys are disabled when questionnaire is launched via a web-link (directly by the participant and not through Sabretooth or Beartooth)</li>",
            fr:
              "<li>Les touches de raccourci « - » et « + » (clavier ou pavé numérique) permettent de passer respectivement à la page précédente et à la page suivante.</li>" +
              "<li>Les touches de raccourci « [ » et « ] » permettent de se déplacer à la question mise en évidence précédente et suivante, respectivement. (Lorsque la page est chargée pour la première fois, aucune question n’est mise en évidence.)</li>" +
              "<li>Une question mise en évidence est surlignée en jaune pâle et les raccourcis numériques sont identifiés par un nombre entre crochets, p. ex. [1].</li>" +
              "<li>Les touches de raccourci numériques « 1 » à « 9 » et « 0 » (pavé numérique non inclus) effectueront les opérations suivantes en fonction du type de question actif :" +
              "<ul>" +
              "<li>Liste : basculer entre les options (désactivée à activée ou activée à désactivée). Si de l’information supplémentaire est requise, la zone de saisie sera automatiquement mise en évidence</li>" +
              "<li>Nombre/chaîne/texte : la zone de saisie sera mise en évidence</li>" +
              "<li>Date : le sélecteur de date s’affichera</li>" +
              "</ul>" +
              "</li>" +
              "<li>Maintenez la touche Majuscule enfoncée pour permettre la sélection des éléments 11 à 20.</li>" +
              "<li>Lorsqu’une zone de saisie est mise en évidence, vous devrez enfoncer la touche Entrée pour quitter la mise en évidence et réactiver les touches de raccourci.</li>" +
              "<li>Tous les raccourcis clavier sont désactivés lorsqu’aucune question n’est mise en évidence ou lorsqu’une zone de saisie est mise en évidence.</li>" +
              "<li>Les raccourcis clavier sont désactivés lorsque le questionnaire est lancé via un lien Web (directement par le participant et non via Sabretooth ou Beartooth).</li>",
          },
        },
        misc: {
          yes: { en: "Yes", fr: "Oui" },
          no: { en: "No", fr: "Non" },
          dkna: {
            en: "Don't Know / No Answer",
            fr: "Ne sais pas / pas de réponse",
          },
          refuse: { en: "Refused", fr: "Refus" },
          preferNotToAnswer: {
            en: "Prefer not to answer",
            fr: "Préfère ne pas répondre",
          },
          choose: { en: "(choose)", fr: "(choisir)" },
          begin: { en: "Begin", fr: "Commencer" },
          next: { en: "Next", fr: "Suivant" },
          previous: { en: "Previous", fr: "Précédent" },
          pause: { en: "Pause", fr: "Interrompre" },
          submit: { en: "Submit", fr: "Envoyer" },
          characters: { en: "characters", fr: "caractères" },
          submitWaitTitle: { en: "Please Wait", fr: "Veuillez patienter" },
          submitWaitMessage: {
            en: "Please wait while the questionnaire is submitted.",
            fr: "Veuillez patienter pendant que nous soumettons le questionnaire.",
          },
          deviceWaitTitle: { en: "Please Wait", fr: "Veuillez patienter" },
          deviceWaitMessage: {
            en: "Please wait while communicating with the device.",
            fr: "Veuillez patienter pendant que nous communiquons avec l’appareil.",
          },
          pleaseConfirm: { en: "Please confirm", fr: "Veuillez confirmer" },
          minimumTitle: {
            en: "Value is too small",
            fr: "La valeur est trop petite",
          },
          maximumTitle: {
            en: "Value is too large",
            fr: "La valeur est trop grande",
          },
          limitMessage: {
            en: "Please provide an answer that is",
            fr: "Veuillez fournir une réponse",
          },
          equalOrGreater: {
            en: "equal to or greater than",
            fr: "égale ou supérieure à",
          },
          equalOrLess: {
            en: "equal to or less than",
            fr: "égale ou inférieure à",
          },
          between: { en: "between", fr: "comprise entre" },
          and: { en: "and", fr: "et" },
          record: { en: "Record", fr: "Enregistrer" },
          reRecord: { en: "Re-record", fr: "Réenregistrer" },
          stop: { en: "Stop", fr: "Arrêter" },
          reRecordConfirm: {
            en: "Are you sure you wish to replace the existing recording?",
            fr: "Êtes-vous certain(e) de vouloir remplacer l’enregistrement existant?",
          },
          maxRecordingTimeTitle: {
            en: "Recording Stopped",
            fr: "Enregistrement arrêté",
          },
          maxRecordingTimeMessage: {
            en: "The maximum time allowed for this recording has been reached.",
            fr: "La durée maximale autorisée pour cet enregistrement a été atteinte.",
          },
          qnaireClosed: {
            en: "Questionnaire Closed",
            fr: "Période de réponse terminée",
          },
          mustCompleteAll: {
            en: "You must complete all questions before you can proceed.",
            fr: "Vous devez répondre à toutes les questions avant de continuer.",
          },
          launch: {
            en: "Launch",
            fr: "Lancer",
          },
          reLaunch: {
            en: "Re-Launch",
            fr: "Relancer",
          },
          abort: {
            en: "Abort",
            fr: "Abandonner",
          },
          inProgress: {
            en: "In Progress",
            fr: "en cours",
          },
          dataAndFileReceived: {
            en: "data and <FILES> file<PLURAL> received",
            fr: "données et <FILES> fichier<PLURAL> reçus",
          },
          dataReceived: {
            en: "data received",
            fr: "données reçues",
          },
          fileReceived: {
            en: "<FILES> file<PLURAL> received",
            fr: "<FILES> fichier<PLURAL> reçu<PLURAL>",
          },
          noDataReceived: {
            en: "no data received",
            fr: "aucune donnée reçue",
          },
          addSignature: {
            en: "Click to add signature",
            fr: "Cliquez pour ajouter une signature",
          },
          removeSignature: {
            en: "Remove signature",
            fr: "Supprimer la signature",
          },
          displayResponses: {
            en: "Display Responses",
            fr: "Afficher les réponses",
          },
          download: {
            en: "Download",
            fr: "Télécharger"
          },
          outOfSync: {
            title: {
              en: "Out of Sync",
              fr: "Désynchronisation",
            },
            description: {
              en: "Your browser appears to be out of sync.  This means that changes have been made to the interview since the last time your web page was loaded.  After you close this message your web page will reload in order to bring your browser up to date.",
              fr: "Votre navigateur semble être désynchronisé. Des modifications ont été apportées à l’entrevue depuis la dernière fois que vous avez ouvert cette page. Quand vous fermerez ce message, la page s’actualisera pour mettre le navigateur à jour.",
            },
          },
          reportProblem: {
            promptTitle: {
              en: "Report Problem",
              fr: "Signaler un problème",
            },
            promptMessage: {
              en:
                "Please describe the problem(s) that you are having with the questionnaire with as much " +
                "detail as possible.  If it is a technical problem, please also describe the type of device " +
                "and web browser you are using.",
              fr: "Veuillez fournir une description la plus détaillée possible du ou des problèmes que vous rencontrez avec le questionnaire. S’il s’agit d’un problème technique, veuillez également fournir le type d’appareil et le navigateur Web que vous utilisez.",
            },
            waitTitle: { en: "Please Wait", fr: "Veuillez patienter" },
            waitMessage: {
              en: "Please wait while your report is submitted.",
              fr: "Veuillez patienter pendant l’envoi de votre rapport.",
            },
            submitted: {
              en:
                "Thank you for reporting your problem.  If you are unable to proceed with the " +
                "questionnaire please close your browser window, we will contact you once the problem has " +
                "been resolved.",
              fr: "Merci d’avoir signalé votre problème. Si vous n’êtes pas capable de répondre au questionnaire, veuillez fermer la fenêtre de votre navigateur. Nous vous contacterons une fois le problème résolu.",
            },
          },
        },
      },
      // used by services below to convert a list of descriptions into an object
      parseDescriptions: function (descriptionList, showHidden) {
        var code = null;
        if (!angular.isString(descriptionList)) descriptionList = "";
        return descriptionList.split("`").reduce((list, part) => {
          if (angular.isDefined(showHidden)) {
            // Replace newlines with \\n so we can search across multiple lines without using
            // the newer "s" RegExp option (Firefox doesn't support until 2020)
            part = part.replace(/\n/g, "\\n");

            // replace hidden and reverse-hidden codes
            part = showHidden
              ? part.replace(/{{!.*!}}/g, "").replace(/{{/g, "").replace(/}}/g, "")
              : part.replace(/{{!/g, "").replace(/!}}/g, "").replace(/{{.*}}/g, "");

            // convert the \\n back into newlines
            part = part.replace(/\\n/g, "\n");
          }

          if (null == code) {
            code = part;
          } else {
            list[code] =
              null == part.match(/<[a-zA-Z]+>/)
                ? $filter("cnNewlines")(part)
                : part;
            code = null;
          }
          return list;
        }, {});
      },
    };
  },
]);

/* ############################################################################################## */
cenozo.factory("CnQnairePartCloneFactory", [
  "CnHttpFactory",
  "CnModalMessageFactory",
  "$filter",
  "$state",
  function (CnHttpFactory, CnModalMessageFactory, $filter, $state) {
    var object = function (type) {
      var parentType =
        "module" == type
          ? "qnaire"
          : "page" == type
          ? "module"
          : "question" == type
          ? "page"
          : "question";

      angular.extend(this, {
        type: type,
        parentType: parentType,
        parentIdName: parentType.replace(" ", "_").snakeToCamel() + "Id",
        typeName: type.replace(/_/g, " ").ucWords(),
        parentTypeName:
          "qnaire" == parentType
            ? "questionnaire"
            : parentType.replace(/_/g, " ").ucWords(),
        sourceId: $state.params.identifier,
        sourceName: null,
        sourceParentId: null,
        working: false,
        operation: "move",
        data: {
          qnaireId: null,
          moduleId: null,
          pageId: null,
          questionId: null,
          rank: null,
          name: null,
        },
        qnaireList: [],
        moduleList: [],
        pageList: [],
        questionList: [],
        rankList: [],
        formatError: false,
        nameConflict: false,

        resetData: function (subject) {
          // reset data
          if (angular.isUndefined(subject)) this.data.qnaireId = null;
          if ([undefined, "qnaire"].includes(subject))
            this.data.moduleId = null;
          if ([undefined, "qnaire", "module"].includes(subject))
            this.data.pageId = null;
          if ([undefined, "qnaire", "module", "page"].includes(subject))
            this.data.questionId = null;
          this.data.rank = null;
          if (angular.isUndefined(subject)) this.data.name = null;
          this.formatError = false;
          this.nameConflict = false;

          // reset lists
          if ([undefined, "qnaire"].includes(subject)) this.moduleList = [];
          if ([undefined, "qnaire", "module"].includes(subject))
            this.pageList = [];
          if ([undefined, "qnaire", "module", "page"].includes(subject))
            this.questionList = [];
          if (
            [undefined, "qnaire", "module", "page", "question"].includes(
              subject
            )
          )
            this.rankList = [];
        },

        onLoad: async function () {
          this.resetData();

          var columnList = [
            "name",
            { table: "module", column: "qnaire_id" },
            { table: this.parentType, column: "name", alias: "parentName" },
          ];
          if (["page", "question", "question_option"].includes(this.type))
            columnList.push({ table: "page", column: "module_id" });
          if (["question", "question_option"].includes(this.type))
            columnList.push({ table: "question", column: "page_id" });
          if ("question_option" == this.type)
            columnList.push({
              table: "question_option",
              column: "question_id",
            });

          var response = await CnHttpFactory.instance({
            path: [this.type, this.sourceId].join("/"),
            data: { select: { column: columnList } },
          }).get();

          this.data.name = response.data.name;
          this.sourceName = response.data.name;
          this.parentSourceName = response.data.parentName;
          this.sourceParentId = response.data[this.parentType + "_id"];
          angular.extend(this.data, {
            qnaireId:
              "qnaire" == this.parentType ? null : response.data.qnaire_id,
            moduleId:
              "module" == this.parentType ? null : response.data.module_id,
            pageId: "page" == this.parentType ? null : response.data.page_id,
            questionId:
              "question" == this.parentType ? null : response.data.question_id,
          });

          await Promise.all([
            this.resetQnaireList(),
            this.setQnaire(true),
            this.setModule(true),
            this.setPage(true),
            this.setQuestion(true),
          ]);
        },

        setOperation: async function () {
          // update the parent list when the operation type changes
          if ("qnaire" == this.parentType) {
            await this.resetQnaireList();
          } else if ("module" == this.parentType) {
            await this.setQnaire(true);
          } else if ("page" == this.parentType) {
            await this.setModule(true);
          } else if ("question" == this.parentType) {
            await this.setPage(true);
          }
        },

        resetQnaireList: async function () {
          var response = await CnHttpFactory.instance({
            path: "qnaire",
            data: {
              select: { column: ["id", "name"] },
              modifier: { order: { name: false } },
            },
          }).query();

          this.qnaireList = response.data
            .filter(
              (item) =>
                "move" != this.operation ||
                "qnaire" != this.parentType ||
                this.sourceParentId != item.id
            )
            .map((item) => ({ value: item.id, name: item.name }));
          this.qnaireList.unshift({
            value: null,
            name: "(choose target questionnaire)",
          });
        },

        setQnaire: async function (noReset) {
          if (angular.isUndefined(noReset)) noReset = false;
          if (!noReset) this.resetData("qnaire");

          // either update the rank list or the module list depending on the type
          if ("module" == this.type) {
            await this.updateRankList();
          } else if (null == this.data.qnaireId) {
            this.moduleList = [];
          } else {
            var response = await CnHttpFactory.instance({
              path: ["qnaire", this.data.qnaireId, "module"].join("/"),
              data: {
                select: { column: ["id", "rank", "name"] },
                modifier: { order: { rank: false } },
              },
            }).query();

            this.moduleList = response.data
              .filter(
                (item) =>
                  "move" != this.operation ||
                  "module" != this.parentType ||
                  this.sourceParentId != item.id
              )
              .map((item) => ({
                value: item.id,
                name: item.rank + ". " + item.name,
              }));
            this.moduleList.unshift({
              value: null,
              name: "(choose target module)",
            });
          }
        },

        setModule: async function (noReset) {
          if (angular.isUndefined(noReset)) noReset = false;
          if (!noReset) this.resetData("module");

          // either update the rank list or the page list depending on the type
          if ("page" == this.type) {
            await this.updateRankList();
          } else if (null == this.data.moduleId) {
            this.pageList = [];
          } else {
            var response = await CnHttpFactory.instance({
              path: ["module", this.data.moduleId, "page"].join("/"),
              data: {
                select: { column: ["id", "rank", "name"] },
                modifier: { order: { rank: false } },
              },
            }).query();

            this.pageList = response.data
              .filter(
                (item) =>
                  "move" != this.operation ||
                  "page" != this.parentType ||
                  this.sourceParentId != item.id
              )
              .map((item) => ({
                value: item.id,
                name: item.rank + ". " + item.name,
              }));
            this.pageList.unshift({
              value: null,
              name: "(choose target page)",
            });
          }
        },

        setPage: async function (noReset) {
          if (angular.isUndefined(noReset)) noReset = false;
          if (!noReset) this.resetData("page");

          // either update the rank list or the question list depending on the type
          if ("question" == this.type) {
            await this.updateRankList();
          } else if (null == this.data.pageId) {
            this.questionList = [];
          } else {
            var response = await CnHttpFactory.instance({
              path: ["page", this.data.pageId, "question"].join("/"),
              data: {
                select: { column: ["id", "rank", "name"] },
                modifier: {
                  where: {
                    column: "question.type",
                    operator: "=",
                    value: "list",
                  },
                  order: { rank: false },
                },
              },
            }).query();

            this.questionList = response.data
              .filter(
                (item) =>
                  "move" != this.operation ||
                  "question" != this.parentType ||
                  this.sourceParentId != item.id
              )
              .map((item) => ({
                value: item.id,
                name: item.rank + ". " + item.name,
              }));
            this.questionList.unshift({
              value: null,
              name:
                0 == this.questionList.length
                  ? "(the selected page has no list type questions)"
                  : "(choose target list question)",
            });
          }
        },

        setQuestion: async function (noReset) {
          if (angular.isUndefined(noReset)) noReset = false;
          if (!noReset) await this.resetData("question");
          await this.updateRankList();
        },

        updateRankList: async function () {
          // if the parent hasn't been selected then the rank list should be empty
          if (null == this.data[this.parentIdName]) {
            this.rankList = [];
          } else {
            var response = await CnHttpFactory.instance({
              path: [
                this.parentType,
                this.data[this.parentIdName],
                this.type,
              ].join("/"),
              data: {
                select: {
                  column: {
                    column: "MAX( " + this.type + ".rank )",
                    alias: "max",
                    table_prefix: false,
                  },
                },
              },
            }).query();

            var maxRank =
              null == response.data[0].max
                ? 1
                : parseInt(response.data[0].max) + 1;
            this.rankList = [];
            for (var rank = 1; rank <= maxRank; rank++) {
              this.rankList.push({
                value: rank,
                name: $filter("cnOrdinal")(rank),
              });
            }
            this.rankList.unshift({
              value: null,
              name: "(choose target rank)",
            });
          }
        },

        isComplete: function () {
          return (
            !this.working &&
            null != this.data.rank &&
            null != this.data.qnaireId &&
            ("page" != this.type || null != this.data.moduleId) &&
            ("question" != this.type ||
              (null != this.data.moduleId && null != this.data.pageId)) &&
            ("question_option" != this.type ||
              (null != this.data.moduleId &&
                null != this.data.pageId &&
                null != this.data.questionId)) &&
            ("move" == this.operation ||
              (!this.nameConflict &&
                !this.formatError &&
                null != this.data.name))
          );
        },

        cancel: async function () {
          await $state.go(this.type + ".view", { identifier: this.sourceId });
        },

        save: async function () {
          var data = { rank: this.data.rank };
          data[this.parentType + "_id"] = this.data[this.parentIdName];

          if ("move" == this.operation) {
            try {
              this.working = true;
              await CnHttpFactory.instance({
                path: this.type + "/" + this.sourceId,
                data: data,
              }).patch();
              await $state.go(this.type + ".view", {
                identifier: this.sourceId,
              });
            } finally {
              this.working = false;
            }
          } else {
            // clone
            // make sure the name is valid
            var re = new RegExp(
              "question_option" == this.type
                ? "^[a-zA-Z0-9_]*$"
                : "^[a-zA-Z_][a-zA-Z0-9_]*$"
            );
            if (null == re.test(this.data.name)) {
              this.formatError = true;
            } else {
              // add the new name to the http data
              data.name = this.data.name;
              try {
                var self = this;
                this.working = true;
                var response = await CnHttpFactory.instance({
                  path: this.type + "?clone=" + this.sourceId,
                  data: data,
                  onError: function (error) {
                    if (409 == error.status) self.nameConflict = true;
                    else CnModalMessageFactory.httpError(error);
                  },
                }).post();

                await $state.go(this.type + ".view", {
                  identifier: response.data,
                });
              } finally {
                this.working = false;
              }
            }
          }
        },
      });
    };
    return {
      instance: function (type) {
        return new object(type);
      },
    };
  },
]);

/* ############################################################################################## */
cenozo.service("CnModalPreStageFactory", [
  "$uibModal",
  function ($uibModal) {
    var object = function (params) {
      angular.extend(this, {
        title: "",
        deviationTypeList: null,
        validToken: null,
        token: null,
        tokenReadOnly: false,
        deviationTypeId: null,
        deviationComments: null,
        comments: null,
      });
      angular.extend(this, params);

      if (null != this.deviationTypeList) {
        // add the unselected option to the deviation type list
        this.deviationTypeList.unshift({ value: null, name: "(Select one)" });

        // if the current deviation type isn't in the list then it's from a different type (order vs skip) so don't use it
        if (
          this.deviationTypeId &&
          null ==
            this.deviationTypeList.findByProperty("id", this.deviationTypeId)
        )
          this.deviationTypeId = null;
      }

      angular.extend(this, {
        show: function () {
          var self = this;
          return $uibModal.open({
            backdrop: "static",
            keyboard: !this.block,
            size: "lg",
            modalFade: true,
            templateUrl: cenozoApp.getFileUrl("pine", "modal-pre-stage.tpl.html"),
            controller: [
              "$scope",
              "$uibModalInstance",
              function ($scope, $uibModalInstance) {
                angular.extend($scope, {
                  model: self,
                  showDeviationComments: function () {
                    if (!$scope.model.deviationTypeId) return false;
                    var deviationType =
                      $scope.model.deviationTypeList.findByProperty(
                        "id",
                        $scope.model.deviationTypeId
                      );
                    return (
                      null != deviationType &&
                      "other" == deviationType.name.toLowerCase()
                    );
                  },
                  checkToken: function () {
                    const element = $scope.form.token;
                    if ($scope.model.validToken == $scope.model.token) {
                      // the token is valid
                      element.$error.mismatch = false;
                      element.$invalid = false;
                      $scope.form.$valid = true;
                    } else {
                      if ($scope.model.token) {
                        element.$error.mismatch = true;
                        element.$invalid = true;
                        $scope.form.$valid = false;
                      } else {
                        element.$error.mismatch = false;
                      }
                    }
                  },
                  ok: function () {
                    if (!$scope.form.$valid) {
                      // dirty all relevant inputs so we can find the problem
                      $scope.form.token.$dirty = true;
                      if (null != $scope.model.deviationTypeList)
                        $scope.form.deviationTypeId.$dirty = true;
                      if ($scope.showDeviationComments())
                        $scope.form.deviationComments.$dirty = true;
                    } else {
                      var response = { comments: $scope.model.comments };
                      if (null != $scope.model.deviationTypeList) {
                        response.deviation_type_id =
                          $scope.model.deviationTypeId;
                        response.deviation_comments =
                          $scope.showDeviationComments()
                            ? $scope.model.deviationComments
                            : null;
                      }
                      $uibModalInstance.close(response);
                    }
                  },
                  cancel: function () {
                    $uibModalInstance.close(null);
                  }
                });
              },
            ],
          }).result;
        },
      });
    };

    return {
      instance: function (params) {
        return new object(angular.isUndefined(params) ? {} : params);
      },
    };
  },
]);
