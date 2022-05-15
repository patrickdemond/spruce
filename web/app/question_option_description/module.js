cenozoApp.defineModule({
  name: "question_option_description",
  models: ["list", "view"],
  create: (module) => {
    cenozoApp.initDescriptionModule(module, "question_option");

    /* ############################################################################################## */
    cenozo.providers.factory("CnQuestionOptionDescriptionViewFactory", [
      "CnBaseViewFactory",
      "CnBaseDescriptionViewFactory",
      function (CnBaseViewFactory, CnBaseDescriptionViewFactory) {
        var object = function (parentModel, root) {
          CnBaseViewFactory.construct(this, parentModel, root);
          CnBaseDescriptionViewFactory.construct(this, "question_option");
        };
        return {
          instance: function (parentModel, root) {
            return new object(parentModel, root);
          },
        };
      },
    ]);

    /* ############################################################################################## */
    cenozo.providers.factory("CnQuestionOptionDescriptionModelFactory", [
      "CnBaseModelFactory",
      "CnQuestionOptionDescriptionListFactory",
      "CnQuestionOptionDescriptionViewFactory",
      function (
        CnBaseModelFactory,
        CnQuestionOptionDescriptionListFactory,
        CnQuestionOptionDescriptionViewFactory
      ) {
        var object = function (root) {
          CnBaseModelFactory.construct(this, module);
          this.listModel =
            CnQuestionOptionDescriptionListFactory.instance(this);
          this.viewModel = CnQuestionOptionDescriptionViewFactory.instance(
            this,
            root
          );
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
