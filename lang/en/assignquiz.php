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
 * @package     mod_assignquiz
 * @category    string
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Assign Quiz';
$string['modulename'] = 'Assign Quiz';
$string['modulenameplural'] = 'Assign Quizzes';
$string['modulename_help'] = 'The AssignQuiz plugin enables teachers to create personalized quizzes with AI-generated questions based on student-uploaded content. It includes all standard quiz features plus:

* A long-text input for specifying required knowledge
* Custom dates for practice submission and quiz opening
* File upload option for students before practice submission deadline
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
$string['phase_switch_task'] = 'Switch phases in AssignQuiz plugin';
$string['visible_after_allowsubmissionfromdate'] = 'Makes AssignQuiz visible after the admission date has passed only if "Always show description" is disabled.';
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
$string['confirmclose'] = 'Once you submit your answers, you won’t be able to change them.
';
$string['submission_confirmation_unanswered'] = 'Questions without a response: {$a}';
