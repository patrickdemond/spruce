cenozoApp.defineModule({
  name: "lookup_item",
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
        singular: "lookup item",
        plural: "lookup items",
        possessive: "lookup item's",
      },
      columnList: {
        identifier: {
          title: 'Identifier',
        },
        name: {
          column: "lookup_item.name",
          title: "Name",
        },
        indicator_list: {
          title: "Indicator List",
        },
        description: {
          column: "lookup_item.description",
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
        format: "identifier",
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
