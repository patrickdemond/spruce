cenozoApp.defineModule({
  name: "indicator",
  models: ["add", "list", "view"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: 'lookup',
          column: 'lookup.name'
        }
      },
      name: {
        singular: "indicator",
        plural: "indicators",
        possessive: "indicator's",
      },
      columnList: {
        name: {
          title: "Name",
        },
        lookup_item_count: {
          title: "Lookup Data Count",
        },
      },
      defaultOrder: {
        column: "name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      lookup: {
        column: "lookup.name",
        title: "Lookup",
        type: "string",
        isExcluded: 'add',
        isConstant: true,
      },
      name: {
        title: "Name",
        type: "string",
        format: "identifier",
      },
    });
  },
});
