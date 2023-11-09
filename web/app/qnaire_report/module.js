cenozoApp.defineModule({
  name: "qnaire_report",
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
        singular: "report",
        plural: "reports",
        possessive: "report's",
      },
      columnList: {
        language: {
          title: "Language",
          column: "language.name",
        },
      },
      defaultOrder: {
        column: "language.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      language_id: {
        title: "Language",
        type: "enum",
        isConstant: 'view',
      },
      data: {
        title: "PDF Template",
        type: "base64",
        mimeType: "application/pdf",
      },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireReportModelFactory", [
      "CnBaseModelFactory",
      "CnQnaireReportAddFactory",
      "CnQnaireReportListFactory",
      "CnQnaireReportViewFactory",
      "CnHttpFactory",
      function (
        CnBaseModelFactory,
        CnQnaireReportAddFactory,
        CnQnaireReportListFactory,
        CnQnaireReportViewFactory,
        CnHttpFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.addModel = CnQnaireReportAddFactory.instance(this);
          this.listModel = CnQnaireReportListFactory.instance(this);
          this.viewModel = CnQnaireReportViewFactory.instance(this, root);

          this.getMetadata = async function () {
            await this.$$getMetadata();

            var response = await CnHttpFactory.instance({
              path: "language",
              data: {
                select: { column: ["id", "name"] },
                modifier: {
                  where: { column: "active", operator: "=", value: true },
                  order: "name",
                  limit: 1000,
                },
              },
            }).query();

            this.metadata.columnList.language_id.enumList =
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
