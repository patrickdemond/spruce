cenozoApp.defineModule({
  name: "lookup_data",
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
        singular: "lookup data",
        plural: "lookup data",
        possessive: "lookup data's",
      },
      columnList: {
        identifier: {
          title: 'Identifier',
        },
        name: {
          title: "Name",
        },
        indicator_list: {
          title: "Indicator List",
        },
        description: {
          title: "Description",
          align: "left",
        },
      },
      defaultOrder: {
        column: "identifier",
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
      identifier: {
        title: "Identifier",
        type: "string",
      },
      name: {
        title: "Name",
        type: "string",
      },
      description: {
        title: "Description",
        type: "text",
      },
    });
  },
});
