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
 * Prints an instance of mod_assignquiz.
 *
 * @package     mod_assignquiz
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $PAGE, $DB, $OUTPUT, $USER;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/mod/quiz/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/format/lib.php');
require_once($CFG->dirroot.'/mod/assignquiz/lib.php');
require_once($CFG->dirroot.'/mod/assignquiz/locallib.php');

// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
$a = optional_param('a', 0, PARAM_INT);



if ($id) {
    $cm = get_coursemodule_from_id('assignquiz', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('assignquiz', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('assignquiz', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('assignquiz', $moduleinstance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:view', $context);

$canattempt = has_capability('mod/quiz:attempt', $context);
$canreviewmine = has_capability('mod/quiz:reviewmyattempts', $context);
$canpreview = has_capability('mod/quiz:preview', $context);

$event = \mod_assignquiz\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $context
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('assignquiz', $moduleinstance);
$event->trigger();

$timenow = time();
$quizid = $DB->get_field('assignquiz', 'id', array('id' => $cm->instance), MUST_EXIST);
$quizobj = aiquiz::create($quizid, $USER->id);
$accessmanager = new aiquiz_access_manager($quizobj, $timenow,
    has_capability('mod/quiz:ignoretimelimits', $context, null, false));
$quiz = $quizobj->get_quiz();


$PAGE->set_url('/mod/assignquiz/view.php', array('id' => $cm->id));

$viewobj = new mod_quiz_view_object();
$viewobj->accessmanager = $accessmanager;
$viewobj->canreviewmine = $canreviewmine || $canpreview;

$attempts = assignquiz_get_user_attempts($quiz->id, $USER->id, 'finished', true);
$lastfinishedattempt = end($attempts);
$unfinished = false;
$unfinishedattemptid = null;
if ($unfinishedattempt = assignquiz_get_user_attempt_unfinished($quiz->id, $USER->id)) {
    $attempts[] = $unfinishedattempt;

    // If the attempt is now overdue, deal with that - and pass isonline = false.
    // We want the student notified in this case.
    $quizobj->create_attempt_object($unfinishedattempt)->handle_if_time_expired(time(), false);

    $unfinished = $unfinishedattempt->state == quiz_attempt::IN_PROGRESS ||
        $unfinishedattempt->state == quiz_attempt::OVERDUE;
    if (!$unfinished) {
        $lastfinishedattempt = $unfinishedattempt;
    }
    $unfinishedattemptid = $unfinishedattempt->id;
    $unfinishedattempt = null; // To make it clear we do not use this again.
}
$numattempts = count($attempts);
$viewobj->attempts = $attempts;
$viewobj->attemptobjs = array();
foreach ($attempts as $attempt) {
    $viewobj->attemptobjs[] = new aiquiz_attempt($attempt, $quiz, $cm, $course, false);
}

if (!$canpreview) {
    $mygrade = assignquiz_get_best_grade($quiz, $USER->id);
} else if ($lastfinishedattempt) {
    // Users who can preview the quiz don't get a proper grade, so work out a
    // plausible value to display instead, so the page looks right.
    $mygrade = quiz_rescale_grade($lastfinishedattempt->sumgrades, $quiz, false);
} else {
    $mygrade = null;
}

$mygradeoverridden = false;
$gradebookfeedback = '';

$item = null;

$grading_info = grade_get_grades($course->id, 'mod', 'assignquiz', $quiz->id, $USER->id);
if (!empty($grading_info->items)) {
    $item = $grading_info->items[0];
    if (isset($item->grades[$USER->id])) {
        $grade = $item->grades[$USER->id];

        if ($grade->overridden) {
            $mygrade = $grade->grade + 0; // Convert to number.
            $mygradeoverridden = true;
        }
        if (!empty($grade->str_feedback)) {
            $gradebookfeedback = $grade->str_feedback;
        }
    }
}
$title = $course->shortname . ': ' . format_string($quiz->name);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));

if (html_is_blank($quiz->intro)) {
    $PAGE->activityheader->set_description('');
}
$PAGE->add_body_class('limitedwidth');
$output = $PAGE->get_renderer('mod_assignquiz');

if ($attempts) {
    // Work out which columns we need, taking account what data is available in each attempt.
    list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts);

    $viewobj->attemptcolumn  = $quiz->attempts != 1;

    $viewobj->gradecolumn    = $someoptions->marks >= question_display_options::MARK_AND_MAX &&
        quiz_has_grades($quiz);
    $viewobj->markcolumn     = $viewobj->gradecolumn && ($quiz->grade != $quiz->sumgrades);
    $viewobj->overallstats   = $lastfinishedattempt && $alloptions->marks >= question_display_options::MARK_AND_MAX;

    $viewobj->feedbackcolumn = assignquiz_has_feedback($quiz) && $alloptions->overallfeedback;
}

$viewobj->timenow = $timenow;
$viewobj->numattempts = $numattempts;
$viewobj->mygrade = $mygrade;
$viewobj->moreattempts = $unfinished ||
    !$accessmanager->is_finished($numattempts, $lastfinishedattempt);
$viewobj->mygradeoverridden = $mygradeoverridden;
$viewobj->gradebookfeedback = $gradebookfeedback;
$viewobj->lastfinishedattempt = $lastfinishedattempt;
$viewobj->canedit = has_capability('mod/quiz:manage', $context);
$viewobj->editurl = new moodle_url('/mod/assignquiz/edit.php', array('cmid' => $cm->id));
$viewobj->backtocourseurl = new moodle_url('/course/view.php', array('id' => $course->id));
$viewobj->startattempturl = $quizobj->start_attempt_url();


if ($accessmanager->is_preflight_check_required($unfinishedattemptid)) {
    $viewobj->preflightcheckform = $accessmanager->get_preflight_check_form(
        $viewobj->startattempturl, $unfinishedattemptid);
}
$viewobj->popuprequired = $accessmanager->attempt_must_be_in_popup();
$viewobj->popupoptions = $accessmanager->get_popup_options();

// Display information about this quiz.
$viewobj->infomessages = $viewobj->accessmanager->describe_rules();
if ($quiz->attempts != 1) {
    $viewobj->infomessages[] = get_string('gradingmethod', 'quiz',
        quiz_get_grading_option_name($quiz->grademethod));
}

// Inform user of the grade to pass if non-zero.
if ($item && grade_floats_different($item->gradepass, 0)) {
    $a = new stdClass();
    $a->grade = quiz_format_grade($quiz, $item->gradepass);
    $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
    $viewobj->infomessages[] = get_string('gradetopassoutof', 'quiz', $a);
}

// Determine wheter a start attempt button should be displayed.
$viewobj->quizhasquestions = $quizobj->has_questions();
$viewobj->preventmessages = array();
if (!$viewobj->quizhasquestions) {
    $viewobj->buttontext = '';

} else {
    if ($unfinished) {
        if ($canpreview) {
            $viewobj->buttontext = get_string('continuepreview', 'quiz');
        } else if ($canattempt) {
            $viewobj->buttontext = get_string('continueattemptquiz', 'quiz');
        }
    } else {
        if ($canpreview) {
            $viewobj->buttontext = get_string('previewquizstart', 'quiz');
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_new_attempt(
                $viewobj->numattempts, $viewobj->lastfinishedattempt);
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            } else if ($viewobj->numattempts == 0) {
                $viewobj->buttontext = get_string('attemptquiz', 'quiz');
            } else {
                $viewobj->buttontext = get_string('reattemptquiz', 'quiz');
            }
        }
    }

    // Users who can preview the quiz should be able to see all messages for not being able to access the quiz.
    if ($canpreview) {
        $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
    } else if ($viewobj->buttontext) {
        // If, so far, we think a button should be printed, so check if they will be allowed to access it.
        if (!$viewobj->moreattempts) {
            $viewobj->buttontext = '';
        } else if ($canattempt) {
            $viewobj->preventmessages = $viewobj->accessmanager->prevent_access();
            if ($viewobj->preventmessages) {
                $viewobj->buttontext = '';
            }
        }
    }
}

$viewobj->showbacktocourse = ($viewobj->buttontext === '' &&
    course_get_format($course)->has_view_page());

echo $OUTPUT->header();

if (isguestuser()) {
    // Guests can't do a quiz, so offer them a choice of logging in or going back.
    echo $output->view_page_guest($course, $quiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else if (!isguestuser() && !($canattempt || $canpreview
        || $viewobj->canreviewmine)) {
    // If they are not enrolled in this course in a good enough role, tell them to enrol.
    echo $output->view_page_notenrolled($course, $quiz, $cm, $context, $viewobj->infomessages, $viewobj);
} else {
    echo $output->view_page($course, $quiz, $cm, $context, $viewobj);
}

echo $OUTPUT->footer();
