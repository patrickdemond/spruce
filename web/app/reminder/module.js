cenozoApp.defineModule({
  name: "reminder",
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
        singular: "reminder",
        plural: "reminders",
        possessive: "reminder's",
      },
      columnList: {
        delay_offset: {
          title: "Offset",
          column: "reminder.delay_offset",
        },
        delay_unit: {
          title: "Unit",
          column: "reminder.delay_unit",
        },
      },
      defaultOrder: {
        column: "reminder.id",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      delay_offset: {
        title: "Offset",
        type: "string",
        format: "integer",
      },
      delay_unit: {
        title: "Unit",
        type: "enum",
      },
    });
  },
});
