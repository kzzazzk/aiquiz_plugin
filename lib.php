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
 * Library of interface functions and constants.
 *
 * @package     mod_assignquiz
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
require_once($CFG->dirroot . '/mod/assignquiz/attemptlib.php');
function assignquiz_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;
        case FEATURE_PLAGIARISM:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the mod_assignquiz into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_assignquiz_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function assignquiz_add_instance($moduleinstance, $mform)
{
    global $DB, $USER;
    $moduleinstance->timemodified = time();

    quiz_process_options($moduleinstance);
    $assignquizid = $DB->insert_record('assignquiz', $moduleinstance);
    $moduleinstance->id = $assignquizid;
    assignquiz_after_add_or_update($moduleinstance);
    return $assignquizid;
}


/**
 * Updates an instance of the mod_assignquiz in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_assignquiz_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function assignquiz_update_instance($moduleinstance, $mform = null)
{

    global $DB;
    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    quiz_process_options($moduleinstance);
    $assignquiz = $DB->update_record('assignquiz', $moduleinstance);
    assignquiz_after_add_or_update($moduleinstance);
    return $assignquiz;
}
function assignquiz_after_add_or_update($assignquiz) {
    global $DB;
    $cmid = $assignquiz->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $assignquiz->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Save the feedback.
    $DB->delete_records('aiquiz_feedback', array('quizid' => $assignquiz->id));

    for ($i = 0; $i <= $assignquiz->feedbackboundarycount; $i++) {
        $feedback = new stdClass();
        $feedback->quizid = $assignquiz->id;
        $feedback->feedbacktext = $assignquiz->feedbacktext[$i]['text'];
        $feedback->feedbacktextformat = $assignquiz->feedbacktext[$i]['format'];
        $feedback->mingrade = $assignquiz->feedbackboundaries[$i];
        $feedback->maxgrade = $assignquiz->feedbackboundaries[$i - 1];
        $feedback->id = $DB->insert_record('aiquiz_feedback', $feedback);
        $feedbacktext = file_save_draft_area_files((int)$assignquiz->feedbacktext[$i]['itemid'],
            $context->id, 'mod_assignquiz', 'feedback', $feedback->id,
            array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
            $assignquiz->feedbacktext[$i]['text']);
        $DB->set_field('aiquiz_feedback', 'feedbacktext', $feedbacktext,
            array('id' => $feedback->id));
    }

    // Store any settings belonging to the access rules.
    aiquiz_access_manager::save_settings($assignquiz);

    // Update the events relating to this quiz.
//    quiz_update_events($assignquiz);
    $completionexpected = (!empty($assignquiz->completionexpected)) ? $assignquiz->completionexpected : null;
    \core_completion\api::update_completion_date_event($assignquiz->coursemodule, 'quiz', $assignquiz->id, $completionexpected);
    assignquiz_grade_item_update($assignquiz);
}

function assignquiz_grade_item_update($assignquiz, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($assignquiz, 'cmidnumber')) { // May not be always present.
        $params = array('itemname' => $assignquiz->name, 'idnumber' => $assignquiz->cmidnumber);
    } else {
        $params = array('itemname' => $assignquiz->name);
    }

    if ($assignquiz->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $assignquiz->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    // What this is trying to do:
    // 1. If the quiz is set to not show grades while the quiz is still open,
    //    and is set to show grades after the quiz is closed, then create the
    //    grade_item with a show-after date that is the quiz close date.
    // 2. If the quiz is set to not show grades at either of those times,
    //    create the grade_item as hidden.
    // 3. If the quiz is set to show grades, create the grade_item visible.
    $openreviewoptions = mod_quiz_display_options::make_from_quiz($assignquiz,
        mod_quiz_display_options::LATER_WHILE_OPEN);
    $closedreviewoptions = mod_quiz_display_options::make_from_quiz($assignquiz,
        mod_quiz_display_options::AFTER_CLOSE);
    if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
        $closedreviewoptions->marks < question_display_options::MARK_AND_MAX) {
        $params['hidden'] = 1;

    } else if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
        $closedreviewoptions->marks >= question_display_options::MARK_AND_MAX) {
        if ($assignquiz->timeclose) {
            $params['hidden'] = $assignquiz->timeclose;
        } else {
            $params['hidden'] = 1;
        }

    } else {
        // Either
        // a) both open and closed enabled
        // b) open enabled, closed disabled - we can not "hide after",
        //    grades are kept visible even after closing.
        $params['hidden'] = 0;
    }

    if (!$params['hidden']) {
        // If the grade item is not hidden by the quiz logic, then we need to
        // hide it if the quiz is hidden from students.
        if (property_exists($assignquiz, 'visible')) {
            // Saving the quiz form, and cm not yet updated in the database.
            $params['hidden'] = !$assignquiz->visible;
        } else {
            $cm = get_coursemodule_from_instance('assignquiz', $assignquiz->id);
            $params['hidden'] = !$cm->visible;
        }
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradebook_grades = grade_get_grades($assignquiz->course, 'mod', 'assignquiz', $assignquiz->id);
    if (!empty($gradebook_grades->items)) {
        $grade_item = $gradebook_grades->items[0];
        if ($grade_item->locked) {
            // NOTE: this is an extremely nasty hack! It is not a bug if this confirmation fails badly. --skodak.
            $confirm_regrade = optional_param('confirm_regrade', 0, PARAM_INT);
            if (!$confirm_regrade) {
                if (!AJAX_SCRIPT) {
                    $message = get_string('gradeitemislocked', 'grades');
                    $back_link = $CFG->wwwroot . '/mod/quiz/report.php?q=' . $assignquiz->id .
                        '&amp;mode=overview';
                    $regrade_link = qualified_me() . '&amp;confirm_regrade=1';
                    echo $OUTPUT->box_start('generalbox', 'notice');
                    echo '<p>'. $message .'</p>';
                    echo $OUTPUT->container_start('buttons');
                    echo $OUTPUT->single_button($regrade_link, get_string('regradeanyway', 'grades'));
                    echo $OUTPUT->single_button($back_link,  get_string('cancel'));
                    echo $OUTPUT->container_end();
                    echo $OUTPUT->box_end();
                }
                return GRADE_UPDATE_ITEM_LOCKED;
            }
        }
    }
    return grade_update('mod/assignquiz', $assignquiz->course, 'mod', 'assignquiz', $assignquiz->instance, 0, $grades, $params);
}


/**
 * Removes an instance of the mod_assignquiz from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function assignquiz_delete_instance($id)
{
    global $DB;

    $assignquiz = $DB->get_record('assignquiz', array('id' => $id));

    if (!$assignquiz) {
        return false;
    }
    assignquiz_grade_item_delete($assignquiz);
    $DB->delete_records('assignquiz', array('id' => $id));
    return true;
}


function assignquiz_grade_item_delete($assignquiz) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/assignquiz', $assignquiz->course, 'mod', 'assignquiz', $assignquiz->instance, 0,
        null, array('deleted' => 1));
}

//displays info on course view
function assignquiz_get_coursemodule_info($coursemodule) {
    global $DB;

    // Fetch assignquiz record
    $assignquiz_record = $DB->get_record('assignquiz', ['id' => $coursemodule->instance], '*', MUST_EXIST);

    // Fetch aiquiz ID (handle case where no record exists)
    $assignquizid = $DB->get_field('assignquiz', 'id', ['id' => $coursemodule->instance]);
    if (!$assignquizid) {
        error_log("AIQuiz record not found for assignquiz ID: " . $coursemodule->instance);
        return null;
    }

    // Create a new course module info object
    $info = new cached_cm_info();

    // Set the name of the activity (this is required)
    $info->name = $assignquiz_record->name;

    // Format availability text properly
    if (!empty($assignquiz_record->timeopen) && !empty($assignquiz_record->timeclose)) {
        $info->content = get_string('availablefromuntilquiz', 'assignquiz', [
            'timeopen' => userdate($assignquiz_record->timeopen),
            'timeclose' => userdate($assignquiz_record->timeclose)
        ]);
    } elseif (!empty($assignquiz_record->timeopen)) {
        $info->content = get_string('availablefrom', 'assignquiz', [
            'timeopen' => userdate($assignquiz_record->timeopen)
        ]);
    } elseif (!empty($assignquiz_record->timeclose)) {
        $info->content = get_string('availableuntil', 'assignquiz', [
            'timeclose' => userdate($assignquiz_record->timeclose)
        ]);
    }

    // Check if the description should be shown
    $showdescription = $DB->get_field('course_modules', 'showdescription', ['instance' => $coursemodule->instance], MUST_EXIST);
    if ($showdescription && !empty($assignquiz_record->intro)) {
        $info->content .= ' <hr/>' . $assignquiz_record->intro;
    }

    // Return the course module info
    return $info;
}

