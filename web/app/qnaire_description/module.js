cenozoApp.defineModule({
  name: "qnaire_description",
  models: ["list", "view"],
  create: (module) => {
    cenozoApp.initDescriptionModule(module, "qnaire");

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireDescriptionViewFactory", [
      "CnBaseViewFactory",
      "CnBaseDescriptionViewFactory",
      function (CnBaseViewFactory, CnBaseDescriptionViewFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);
          CnBaseDescriptionViewFactory.construct(this, "qnaire");
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQnaireDescriptionModelFactory", [
      "CnBaseModelFactory",
      "CnQnaireDescriptionListFactory",
      "CnQnaireDescriptionViewFactory",
      function (
        CnBaseModelFactory,
        CnQnaireDescriptionListFactory,
        CnQnaireDescriptionViewFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.listModel = CnQnaireDescriptionListFactory.instance(this);
          this.viewModel = CnQnaireDescriptionViewFactory.instance(this, root);
          this.getEditEnabled = function () {
            return !this.viewModel.record.readonly && this.$$getEditEnabled();
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
