define([
    'core/notification',
    'core/modal_factory',
    'core/ajax',
    'jquery',
], function (Notification, ModalFactory, Ajax, $) {
    return {
        // eslint-disable-next-line no-unused-vars
        init: function (quizid, coursemodule) {
            const originalConfirm = Notification.confirm;

            Notification.confirm = function (title, message, yesLabel, noLabel, yesCallback, noCallback) {
                const isaiquizDeletion = typeof message === 'string' &&
                    message.startsWith('Are you sure that you want to delete the Assign Quiz');

                if (!isaiquizDeletion) {
                    return originalConfirm(title, message, yesLabel, noLabel, yesCallback, noCallback);
                }

                const wrappedYes = function () {
                    ModalFactory.create({
                        title: 'One last check!',
                        body: 'Do you also want to delete the generated questions on this quiz?',
                        type: ModalFactory.types.SAVE_CANCEL
                    }).then(function (secondModal) {
                        secondModal.show();

                        const modalRoot = secondModal.getRoot();
                        const saveBtn = modalRoot.find('.modal-footer .btn-primary');
                        saveBtn.text('Yes, delete questions');

                        const cancelBtn = modalRoot.find('.modal-footer .btn-secondary');
                        cancelBtn.text('No, keep them');

                        saveBtn.on('click', function () {
                            secondModal.hide();
                            if (typeof yesCallback === 'function') {
                                yesCallback();
                            }
                        });

                        cancelBtn.on('click', function () {
                            secondModal.hide();

                            // 🔁 Call AJAX to preserve questions
                            Ajax.call([{
                                methodname: 'mod_aiquiz_preserve_questions',
                                args: {
                                    quizid: quizid
                                },
                                fail: Notification.exception
                            }]);

                            // 🌀 Find and animate the activity deletion
                            const activitySelector = `[id="module-${coursemodule}"]`; // coursemodule must be passed into `init`
                            const activityElement = document.querySelector(activitySelector);

                            if (activityElement) {
                                activityElement.classList.add('dimmed'); // optional visual cue
                                $(activityElement).fadeOut(150, function () {
                                    this.remove(); // remove from DOM after fade-out
                                });
                            }
                        });


                    }).catch(Notification.exception);
                };

                const wrappedNo = function() {
                    if (typeof noCallback === 'function') {
                        noCallback();
                    }
                };

                return originalConfirm(title, message, yesLabel, noLabel, wrappedYes, wrappedNo);
            };
        }
    };
});
