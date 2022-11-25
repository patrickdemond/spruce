cenozoApp.defineModule({
  name: "embedded_file",
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
        singular: "embedded file",
        plural: "embedded files",
        possessive: "embedded file's",
      },
      columnList: {
        name: {
          title: "Name",
          column: "embedded_file.name",
        },
        mime_type: {
          title: "Mime Type",
        },
        size: {
          title: "Size",
          type: "size",
        },
      },
      defaultOrder: {
        column: "embedded_file.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
      },
      mime_type: {
        title: "Mime Type",
        type: "string",
        isExcluded: "add",
        isConstant: true,
      },
      size: {
        title: "File Size",
        type: "size",
        isExcluded: "add",
        isConstant: true,
      },
      data: {
        title: "Content",
        type: "base64",
        isConstant: true,
      },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnEmbeddedFileAddFactory", [
      "CnBaseAddFactory",
      function (CnBaseAddFactory) {
        var object = function (parentModel) {
          CnBaseAddFactory.construct(this, parentModel);
          this.configureFileInput("data");
        };
        return {
          instance: function (parentModel) {
            return new object(parentModel);
          },
        };
      },
    ]);
  },
});
