cenozoApp.defineModule({
  name: "qnaire_alternate_consent_type_trigger",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "qnaire",
          column: "qnaire.name",
        },
      },
      name: {
        singular: "alternate consent trigger",
        plural: "alternate consent triggers",
        possessive: "alternate consent trigger's",
      },
      columnList: {
        alternate_consent_type: {
          title: "Consent Type",
          column: "alternate_consent_type.name",
        },
        question: {
          title: "Question",
          column: "question.name",
        },
        answer_value: {
          title: "Required Answer",
        },
        accept: {
          title: "Consent Accept",
          type: "boolean",
        },
      },
      defaultOrder: {
        column: "question.rank",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      alternate_consent_type_id: {
        title: "Consent Type",
        type: "enum",
      },
      question_id: {
        title: "Question",
        type: "lookup-typeahead",
        typeahead: {
          table: null, // filled out by the add and view factories below
          select: 'CONCAT( question.name, " (", question.type, ")" )',
          where: "question.name",
        },
        isExcluded: function ($state, model) {
          // don't include the question_id when we're adding from a question already
          return "question" == model.getSubjectFromState();
        },
      },
      answer_value: {
        title: "Required Answer",
        type: "string",
      },
      accept: {
        title: "Consent Accept",
        type: "boolean",
      },
      qnaire_id: { column: "qnaire.id", type: "hidden" },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireAlternateConsentTypeTriggerAddFactory", [
      "CnBaseAddFactory",
      function (CnBaseAddFactory) {
        var object = function (parentModel) {
          CnBaseAddFactory.construct(this, parentModel);

          this.onNew = async function (record) {
            await this.$$onNew(record);

            // update the question_id's typeahead table value (restrict to questions belonging to current qnaire only)
            var inputList =
              this.parentModel.module.inputGroupList.findByProperty(
                "title",
                ""
              ).inputList;
            inputList.question_id.typeahead.table = [
              "qnaire",
              this.parentModel.getParentIdentifier().identifier,
              "question",
            ].join("/");
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
    cenozo.providers.factory("CnQnaireAlternateConsentTypeTriggerViewFactory", [
      "CnBaseViewFactory",
      function (CnBaseViewFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);

          this.onView = async function (force) {
            await this.$$onView(force);

            // update the question_id's typeahead table value (restrict to questions belonging to current qnaire only)
            var inputList =
              this.parentModel.module.inputGroupList.findByProperty(
                "title",
                ""
              ).inputList;
            inputList.question_id.typeahead.table = [
              "qnaire",
              this.record.qnaire_id,
              "question",
            ].join("/");
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
    cenozo.providers.factory(
      "CnQnaireAlternateConsentTypeTriggerModelFactory",
      [
        "CnBaseModelFactory",
        "CnQnaireAlternateConsentTypeTriggerAddFactory",
        "CnQnaireAlternateConsentTypeTriggerListFactory",
        "CnQnaireAlternateConsentTypeTriggerViewFactory",
        "CnHttpFactory",
        function (
          CnBaseModelFactory,
          CnQnaireAlternateConsentTypeTriggerAddFactory,
          CnQnaireAlternateConsentTypeTriggerListFactory,
          CnQnaireAlternateConsentTypeTriggerViewFactory,
          CnHttpFactory
        ) {
          var object = function (root) {
            CnBaseModelFactory.construct(this, module);
            this.addModel =
              CnQnaireAlternateConsentTypeTriggerAddFactory.instance(this);
            this.listModel =
              CnQnaireAlternateConsentTypeTriggerListFactory.instance(this);
            this.viewModel =
              CnQnaireAlternateConsentTypeTriggerViewFactory.instance(
                this,
                root
              );

            // extend getMetadata
            this.getMetadata = async function () {
              await this.$$getMetadata();

              var response = await CnHttpFactory.instance({
                path: "alternate_consent_type",
                data: {
                  select: { column: ["id", "name"] },
                  modifier: { order: "name", limit: 1000 },
                },
              }).query();

              this.metadata.columnList.alternate_consent_type_id.enumList =
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
      ]
    );
  },
});
