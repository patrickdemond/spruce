cenozoApp.defineModule({
  name: "qnaire_consent_type_confirm",
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
        singular: "consent confirm",
        plural: "consent confirms",
        possessive: "consent confirm's",
      },
      columnList: {
        consent_type: {
          title: "Consent Type",
          column: "consent_type.name",
        },
      },
      defaultOrder: {
        column: "consent_type.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      consent_type_id: {
        title: "Consent Type",
        type: "enum",
      },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireConsentTypeConfirmModelFactory", [
      "CnBaseModelFactory",
      "CnQnaireConsentTypeConfirmAddFactory",
      "CnQnaireConsentTypeConfirmListFactory",
      "CnQnaireConsentTypeConfirmViewFactory",
      "CnHttpFactory",
      function (
        CnBaseModelFactory,
        CnQnaireConsentTypeConfirmAddFactory,
        CnQnaireConsentTypeConfirmListFactory,
        CnQnaireConsentTypeConfirmViewFactory,
        CnHttpFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.addModel = CnQnaireConsentTypeConfirmAddFactory.instance(this);
          this.listModel = CnQnaireConsentTypeConfirmListFactory.instance(this);
          this.viewModel = CnQnaireConsentTypeConfirmViewFactory.instance(
            this,
            root
          );

          // extend getMetadata
          this.getMetadata = async function () {
            await this.$$getMetadata();

            var response = await CnHttpFactory.instance({
              path: "consent_type",
              data: {
                select: { column: ["id", "name"] },
                modifier: { order: "name", limit: 1000 },
              },
            }).query();

            this.metadata.columnList.consent_type_id.enumList =
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
