cenozoApp.defineModule({
  name: "device",
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
        singular: "device",
        plural: "devices",
        possessive: "device's",
      },
      columnList: {
        name: {
          title: "Name",
          column: "device.name",
        },
        url: {
          title: "URL",
          column: "device.url",
        },
        emulate: {
          title: "Emulate",
          column: "device.emulate",
          type: "boolean",
        },
      },
      defaultOrder: {
        column: "device.name",
        reverse: false,
      },
    });

    module.addInputGroup("", {
      name: {
        title: "Name",
        type: "string",
      },
      url: {
        title: "URL",
        type: "string",
      },
      emulate: {
        title: "Emulate Mode",
        type: "boolean",
      },
    });

    module.addExtraOperation("view", {
      title: "Check Status",
      operation: async function ($state, model) {
        try {
          this.working = true;
          await model.viewModel.getDeviceStatus();
        } finally {
          this.working = false;
        }
      },
      isDisabled: function ($state, model) {
        return this.working;
      },
    });

    /* ############################################################################################## */
    cenozo.providers.factory("CnDeviceViewFactory", [
      "CnBaseViewFactory",
      "CnHttpFactory",
      "CnModalMessageFactory",
      function (CnBaseViewFactory, CnHttpFactory, CnModalMessageFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root, 'answer_device');

          this.getDeviceStatus = async function () {
            var modal = CnModalMessageFactory.instance({
              title: "Device Status",
              message:
                "Please wait while communicating with the device.",
              block: true,
            });

            modal.show();
            var response = await CnHttpFactory.instance({
              path: "device/" + this.record.id + "?action=status",
            }).get();
            modal.close();

            const status = angular.fromJson(response.data);
            let message = "";
            if( null == status || '' == status ) {
              message = "ERROR: There was no response from the device.";
            } else {
              message = Object.keys(status).reduce( (str, key) => {
                str += "<li>" + key.ucWords() + ": " + status[key] + "</li>";
                return str;
              }, "The device responded with the following parameters:<br/><ul>" ) + "</ul>";
            }
            await CnModalMessageFactory.instance({
              title: "Device Status " + ( null == status ? "(Offline)" : "(Online)" ),
              html: true,
              message: message,
              error: null == status,
            }).show();
          };
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);
  },
});
