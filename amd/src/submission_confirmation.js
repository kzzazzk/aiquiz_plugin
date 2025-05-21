// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A javascript module to handle submission confirmation for quiz.
 *
 * @module    mod_aiquiz/submission_confirmation
 * @copyright 2022 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     4.1
 */

import {saveCancelPromise} from 'core/notification';
import Prefetch from 'core/prefetch';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

const SELECTOR = {
    attemptSubmitButton: '.path-mod-aiquiz .btn-finishattempt button',
    attemptSubmitForm: 'form#frm-finishattempt',
};

const TEMPLATES = {
    submissionConfirmation: 'mod_aiquiz/submission_confirmation',
};

/**
 * Shows a loading overlay while the submission is in progress.
 */
const showLoadingScreen = () => {
    if (document.getElementById('loadingScreen')) {
        return;
    }

    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loadingScreen';
    loadingDiv.style.display = 'flex';
    loadingDiv.innerHTML = `
        <div class="loading-content">
            <div class="spinner"></div>
            <h4>Loading...</h4>
            <p>Please wait while the feedback is being generated</p>
        </div>
    `;

    const style = document.createElement('style');
    document.head.appendChild(style);
    document.body.appendChild(loadingDiv);
};

/**
 * Register events for attempt submit button.
 * @param {int} unAnsweredQuestions Total number of un-answered questions
 */
const registerEventListeners = (unAnsweredQuestions) => {
    const submitAction = document.querySelector(SELECTOR.attemptSubmitButton);
    if (submitAction) {
        submitAction.addEventListener('click', async(e) => {
            e.preventDefault();
            try {
                await saveCancelPromise(
                    getString('submission_confirmation', 'quiz'),
                    Templates.render(TEMPLATES.submissionConfirmation, {
                        hasunanswered: unAnsweredQuestions > 0,
                        totalunanswered: unAnsweredQuestions
                    }),
                    getString('submitallandfinish', 'quiz')
                );

                showLoadingScreen();

                submitAction.closest(SELECTOR.attemptSubmitForm).submit();
                console.log("Form will be submitted"); // eslint-disable-line no-console

                Notification.addNotification({
                    message: await getString('submission_successful', 'mod_aiquiz'),
                    type: 'info',
                });

            } catch {
                console.log("Cancel submission"); // eslint-disable-line no-console
                return;
            }
        });
    }
};

/**
 * Initialises.
 * @param {int} unAnsweredQuestions Total number of unanswered questions
 */
export const init = (unAnsweredQuestions) => {
    Prefetch.prefetchStrings('core', ['submit']);
    Prefetch.prefetchStrings('core_admin', ['confirmation']);
    Prefetch.prefetchStrings('quiz', ['submitallandfinish', 'submission_confirmation']);
    Prefetch.prefetchStrings('mod_aiquiz', ['submission_successful']);
    Prefetch.prefetchTemplate(TEMPLATES.submissionConfirmation);
    registerEventListeners(unAnsweredQuestions);
};
