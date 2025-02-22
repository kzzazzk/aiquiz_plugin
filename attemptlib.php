<?php

require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/assignquiz/accessmanager.php');
require_once($CFG->dirroot . '/mod/assignquiz/classes/structure.php');

use mod_assignquiz\assignquiz_structure;
class aiquiz extends quiz
{
    public static function create($quizid, $userid = null) {
        global $DB;

        $quiz = aiquiz_access_manager::load_quiz_and_settings($quizid);
        $courseid = $DB->get_field('assignquiz', 'course', array('id' => $quiz->id), MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assignquiz', $quiz->id, $course->id, false, MUST_EXIST);

        // Update quiz with override information.
        if ($userid) {
            $quiz = quiz_update_effective_access($quiz, $userid);
        }

        return new aiquiz($quiz, $cm, $course);
    }

    public function get_structure() {
        return \mod_assignquiz\assignquiz_structure::create_for_quiz($this);
    }
}