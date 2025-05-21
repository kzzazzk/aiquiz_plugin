<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     mod_aiquiz
 * @category    string
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'AI Quiz';
$string['modulename'] = 'AI Quiz';
$string['modulenameplural'] = 'AI Quizzes';
$string['modulename_help'] = 'The AI Quiz plugin enables teachers to create personalized quizzes with AI-generated questions based on uploaded documents in a certain section. It includes all standard quiz features plus:

* A long-text input for specifying required knowledge
* AI-generated quizzes based on uploaded content
* AI-generated feedback on quiz performance

Quizzes may be used

* As personalized course exams
* As practice tests tailored to student submissions
* To provide immediate and specific feedback on performance
* For customized self-assessment';
$string['activitynamename'] = 'Name';
$string['aiquizfieldset'] = 'Settings';
$string['assignmenttiming'] = 'Availability';
$string['assignmentname'] = 'Task name';
$string['assigninstructions'] = 'Assignment submission instructions';
$string['assigninstructions_help'] = 'The actions you would like the student to complete for this assignment. This is only shown on the submission page where a student edits and submits their assignment.';
$string['activityname'] = 'Activity name';
$string['quiztiming'] = 'Timing';
$string['dynamic'] = 'Dynamic';
$string['static'] = 'Static';
$string['activitydescription'] = 'Required knowledge';
$string['availablefrom'] = 'Available from {$a->open}';
$string['availableuntil'] = 'Available until {$a->close}';
$string['availablefromuntilassign'] = 'Submission Phase: <br>
&ensp;&ensp;<b>Opened:</b> {$a->open}<br>
&ensp;&ensp;<b>Due:</b> {$a->due}';
$string['availablefromuntilquiz'] = 'Quiz Phase: <br>
&ensp;&ensp;<b>Opened:</b> {$a->timeopen}<br>
&ensp;&ensp;<b>Close:</b> {$a->timeclose}';
$string['phase_switch_task'] = 'Switch phases in AI Quiz plugin';
$string['visible_after_allowsubmissionfromdate'] = 'Makes AI Quiz visible after the admission date has passed only if "Always show description" is disabled.';
$string['mingrade'] = 'Minimum grade';
$string['maxgrade'] = 'Maximum grade';
$string['submissionphasedescription'] = 'Submission phase description';
$string['quizphasedescription'] = 'Quiz phase description';
$string['description'] = 'Description';
$string['requiredknowledge_help'] = 'Describe in detail what knowledge is required for the users to properly meet with the standards of grading.';
$string['activityeditor_help'] = 'The actions you would like the student to complete for this assignment. This is only shown on the submission page where a student edits and submits their assignment.';
$string['aiassignconfigtitle'] = 'Assignment Configuration';
$string['aiquizconfigtitle'] = 'AI Quiz Configuration';
$string['coursemoduleconfigtitle'] = 'Other course module settings';
$string['basicsettings'] = 'Basic settings';
$string['pluginadministration'] = 'AI Quiz administration';
$string['apikey_desc'] = 'In this field, you can enter the OpenAI API key. This key is used to authenticate your requests and ensure that you have access to the AI features. Make sure to keep this key secure and do not share it with anyone else.';
$string['apikey'] = 'OpenAI API Key';
$string['apikeyinvalid'] = 'The provided API key is invalid. Please check your key and try again.';
$string['apikeyempty'] = 'The provided API key is empty. Please check your key and try again.';
$string['apikeyempty_course_view'] = 'The API key you set in the AI Quiz plugin settings is empty. Please check your key and try again.';
$string['apikeyinvalid_course_view'] = 'The API key you set in the AI Quiz plugin settings is invalid. Please check your key and try again.';
$string['questiongenmodel'] = 'OpenAI Question Generation Model:';
$string['questiongenmodeldescription'] = 'In this field, you can enter your preferred model for the question generation task';
$string['feedbackgenmodel'] = 'OpenAI Feedback Generation Model:';
$string['feedbackgenmodeldescription'] = 'In this field, you can enter your preferred model for the feedback generation  task';
$string['openaisettingsdescription'] = 'In this section, you can configure the OpenAI API key and the models used for question and feedback generation. The API key is required to access the OpenAI services, and the models determine how the AI generates questions and feedback based on the provided content.';
$string['openaisettings'] = 'OpenAI Settings';
$string['quiz_gen_assistant_id'] = 'Quiz Question Generator';
$string['feedback_gen_assistant_id'] = 'Quiz Feedback Generator';
$string['submission_successful'] = 'Your quiz attempt has been submitted, please wait for the feedback to be generated!';
$string['submitallandfinish'] = 'Once you submit your answers, you won’t be able to change them. Feedback will appear shortly after.';
$string['delete'] = 'Delete';
$string['cancel'] = 'Cancel';
$string['confirmclose'] = 'Once you submit your answers, you won’t be able to change them.
';
$string['submission_confirmation_unanswered'] = 'Questions without a response: {$a}';
$string['questionhdr'] = 'Question';
$string['numberofquestions'] = 'Number of Questions';
$string['questioncorrectvalue'] = 'Value of correctly answered question';
$string['questionincorrectvalue'] = 'Value of incorrectly answered question';
$string['questiongradecorrect_help'] = 'This is the number of points awarded for each correctly answered question in the quiz. It determines how much a correct response contributes to the total score.';
$string['questiongradeincorrect_help'] = 'This is the number of points deducted for each incorrectly answered question. Typically set to 0, but you can assign negative values for penalties.';
$string['numberofquestions_help'] = 'This is the number of questions that will be generated for the quiz. It is defaulted at 10 because it is generally a good number for a quiz and it is has been tested to not take too long, but you can change it to any number you want.';
$string['numberofquestions'] = 'Number of Questions';

