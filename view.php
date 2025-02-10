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

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/classes/event/course_module_viewed.php');
require_once($CFG->dirroot.'/mod/quiz/attemptlib.php');
require_once($CFG->dirroot.'/mod/quiz/accessmanager.php');
require_once(__DIR__.'/attemptlib.php');
require_once(__DIR__.'/accessmanager.php');;

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

$modulecontext = context_module::instance($cm->id);

$event = \mod_aiquiz\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('assignquiz', $moduleinstance);
$event->trigger();

$timenow = time();
$quizid = $DB->get_field('assignquiz', 'id', array('id' => $cm->instance), MUST_EXIST);
$quizobj = aiquiz::create($quizid, $USER->id);
$accessmanager = new aiquiz_access_manager($quizobj, $timenow,
    has_capability('mod/quiz:ignoretimelimits', $modulecontext, null, false));
$quiz = $quizobj->get_quiz();

// Trigger course_module_viewed event and completion.
quiz_view($quiz, $course, $cm, $modulecontext);

$PAGE->set_url('/mod/assignquiz/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
//quiz_view($quiz, $course, $cm, $context);


echo $OUTPUT->header();

echo $OUTPUT->footer();
