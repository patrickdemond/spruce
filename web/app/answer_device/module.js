cenozoApp.defineModule({
  name: "answer_device",
  models: ["list"],
  create: (module) => {
    angular.extend(module, {
      identifier: {
        parent: {
          subject: "device",
          column: "device.id",
        },
      },
      name: {
        singular: "device status",
        plural: "device statuses",
        possessive: "device status'",
      },
      columnList: {
        questionnaire: {
          title: 'Questionnaire',
          column: 'qnaire.name',
        },
        token: {
          title: 'Token',
          column: 'respondent.token',
        },
        device: {
          title: 'Device',
          column: 'device.name',
        },
        uuid: {
          title: 'UUID',
        },
        status: {
          title: 'Status',
        },
        start_datetime: {
          title: 'Start Date & Time',
          type: 'datetime',
        },
        end_datetime: {
          title: 'End Date & Time',
          type: 'datetime',
        },
      },
      defaultOrder: {
        column: "answer_device.start_datetime",
        reverse: true,
      },
    });
  },
});
