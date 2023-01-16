cenozoApp.defineModule({
  name: "qnaire_report_data",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "qnaire_report",
          column: "qnaire_report.id",
        },
      },
      name: {
        singular: "report data",
        plural: "report data",
        possessive: "report data's",
      },
      columnList: {
        name: {
          title: "Name",
          column: "qnaire_report_data.name",
        },
        code: {
          title: "Code",
          column: "qnaire_report_data.code",
        },
      },
      defaultOrder: {
        column: "qnaire_report_data.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
      },
      code: {
        title: "Code",
        type: "text",
      },
    });
  },
});
