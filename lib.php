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


use mod_assignquiz\question\bank\assignquiz_custom_view;
use mod_quiz\question\bank\custom_view;
require_once($CFG->dirroot . '/mod/assignquiz/classes/question/bank/custom_view.php');
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
    $result = quiz_process_options($moduleinstance);
    if ($result && is_string($result)) {
        return $result;
    }
    $assignquizid = $DB->insert_record('assignquiz', $moduleinstance);
    $DB->insert_record('aiquiz_sections', array('quizid' => $assignquizid,
        'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0));

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
//    $DB->delete_records('aiquiz_feedback', array('quizid' => $assignquiz->id));
//
//    for ($i = 0; $i <= $assignquiz->feedbackboundarycount; $i++) {
//        $feedback = new stdClass();
//        $feedback->quizid = $assignquiz->id;
//        $feedback->feedbacktext = $assignquiz->feedbacktext[$i]['text'];
//        $feedback->feedbacktextformat = $assignquiz->feedbacktext[$i]['format'];
//        $feedback->mingrade = $assignquiz->feedbackboundaries[$i];
//        $feedback->maxgrade = $assignquiz->feedbackboundaries[$i - 1];
//        $feedback->id = $DB->insert_record('aiquiz_feedback', $feedback);
//        $feedbacktext = file_save_draft_area_files((int)$assignquiz->feedbacktext[$i]['itemid'],
//            $context->id, 'mod_assignquiz', 'feedback', $feedback->id,
//            array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
//            $assignquiz->feedbacktext[$i]['text']);
////        $DB->set_field('aiquiz_feedback', 'feedbacktext', $feedbacktext,
////            array('id' => $feedback->id));
//    }

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

    return grade_update('mod/assignquiz', $assignquiz->course, 'mod', 'assignquiz', $assignquiz->id, 0,
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
function assignquiz_extend_settings_navigation($settings, $assignquiznode)
{
    global $CFG;

    require_once($CFG->libdir . '/questionlib.php');  // Only include when needed.

    // Get a list of existing child nodes.
    $keys = $assignquiznode->get_children_key_list();
    $beforekey = null;

    // Find the "Edit settings" node or the first child to insert the new nodes before.
    $i = array_search('modedit', $keys);
    if ($i === false && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];  // If no "Edit settings", add before the first node.
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];  // Insert after the "Edit settings".
    }

    // Add "Overrides" node if the user has required capabilities.
    if (has_any_capability(['mod/quiz:manageoverrides', 'mod/quiz:viewoverrides'], $settings->get_page()->cm->context)) {
        $url = new moodle_url('/mod/assignquiz/overrides.php', ['cmid' => $settings->get_page()->cm->id, 'mode' => 'user']);
        $node = navigation_node::create(get_string('overrides', 'quiz'), $url, navigation_node::TYPE_SETTING, null, 'mod_quiz_useroverrides');
        $assignquiznode->add_node($node, $beforekey);
    }

    // Add "Questions" node if the user can manage quizzes.
    if (has_capability('mod/quiz:manage', $settings->get_page()->cm->context)) {
        $node = navigation_node::create(get_string('questions', 'quiz'),
            new moodle_url('/mod/assignquiz/edit.php', array('cmid' => $settings->get_page()->cm->id)),
            navigation_node::TYPE_SETTING, null, 'mod_quiz_edit', new pix_icon('t/edit', ''));
        $assignquiznode->add_node($node, $beforekey);
    }

    // Add "Preview" node if the user can preview quizzes.
    if (has_capability('mod/quiz:preview', $settings->get_page()->cm->context)) {
        $url = new moodle_url('/mod/assignquiz/startattempt.php', array('cmid' => $settings->get_page()->cm->id, 'sesskey' => sesskey()));
        $node = navigation_node::create(get_string('preview', 'quiz'), $url,
            navigation_node::TYPE_SETTING, null, 'mod_quiz_preview', new pix_icon('i/preview', ''));
        $previewnode = $assignquiznode->add_node($node, $beforekey);
        $previewnode->set_show_in_secondary_navigation(false);  // Optionally hide in secondary navigation.
    }

    // Add question settings if any exist.
    question_extend_settings_navigation($assignquiznode, $settings->get_page()->cm->context)->trim_if_empty();

    // Add "Results" node if the user can view reports.
    if (has_any_capability(['mod/quiz:viewreports', 'mod/quiz:grade'], $settings->get_page()->cm->context)) {
        require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
        $reportlist = quiz_report_list($settings->get_page()->cm->context);

        $url = new moodle_url('/mod/assignquiz/report.php', array('id' => $settings->get_page()->cm->id, 'mode' => reset($reportlist)));
        $reportnode = $assignquiznode->add_node(navigation_node::create(get_string('results', 'quiz'), $url,
            navigation_node::TYPE_SETTING, null, 'quiz_report', new pix_icon('i/report', '')));

        foreach ($reportlist as $report) {
            $url = new moodle_url('/mod/assignquiz/report.php', ['id' => $settings->get_page()->cm->id, 'mode' => $report]);
            $reportnode->add_node(navigation_node::create(get_string($report, 'quiz_' . $report), $url,
                navigation_node::TYPE_SETTING, null, 'quiz_report_' . $report, new pix_icon('i/item', '')));
        }
    }
}

    function mod_assignquiz_output_fragment_quiz_question_bank($args) {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/mod/assignquiz/locallib.php');
        require_once($CFG->dirroot . '/question/editlib.php');

        $querystring = preg_replace('/^\?/', '', $args['querystring']);
        $params = [];
        parse_str($querystring, $params);

        // Build the required resources. The $params are all cleaned as
        // part of this process.
        list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
            question_build_edit_resources('editq', '/mod/assignquiz/edit.php', $params, custom_view::DEFAULT_PAGE_SIZE);

        // Get the course object and related bits.
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        require_capability('mod/assignquiz:manage', $contexts->lowest());

        // Create quiz question bank view.
        $questionbank = new assignquiz_custom_view($contexts, $thispageurl, $course, $cm, $quiz);
        $questionbank->set_quiz_has_attempts(quiz_has_attempts($quiz->id));

        // Output.
        $renderer = $PAGE->get_renderer('mod_assignquiz', 'assignquizedit');
        return $renderer->assignquiz_question_bank_contents($questionbank, $pagevars);
    }
    function mod_assignquiz_output_fragment_add_random_question_form($args) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assignquiz/addrandomform.php');

        $contexts = new \core_question\local\bank\question_edit_contexts($args['context']);
        $formoptions = [
            'contexts' => $contexts,
            'cat' => $args['cat']
        ];
        $formdata = [
            'category' => $args['cat'],
            'addonpage' => $args['addonpage'],
            'returnurl' => $args['returnurl'],
            'cmid' => $args['cmid']
        ];

        $form = new quiz_add_random_form(
            new \moodle_url('/mod/assignquiz/addrandom.php'),
            $formoptions,
            'post',
            '',
            null,
            true,
            $formdata
        );
        $form->set_data($formdata);

        return $form->render();
    }
    function assignquiz_get_user_attempts($quizids, $userid, $status = 'finished', $includepreviews = false) {
        global $DB, $CFG;
        // TODO MDL-33071 it is very annoying to have to included all of locallib.php
        // just to get the quiz_attempt::FINISHED constants, but I will try to sort
        // that out properly for Moodle 2.4. For now, I will just do a quick fix for
        // MDL-33048.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $params = array();
        switch ($status) {
            case 'all':
                $statuscondition = '';
                break;

            case 'finished':
                $statuscondition = ' AND state IN (:state1, :state2)';
                $params['state1'] = quiz_attempt::FINISHED;
                $params['state2'] = quiz_attempt::ABANDONED;
                break;

            case 'unfinished':
                $statuscondition = ' AND state IN (:state1, :state2)';
                $params['state1'] = quiz_attempt::IN_PROGRESS;
                $params['state2'] = quiz_attempt::OVERDUE;
                break;
        }

        $quizids = (array) $quizids;
        list($insql, $inparams) = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED);
        $params += $inparams;
        $params['userid'] = $userid;

        $previewclause = '';
        if (!$includepreviews) {
            $previewclause = ' AND preview = 0';
        }

        return $DB->get_records_select('aiquiz_attempts',
            "quiz $insql AND userid = :userid" . $previewclause . $statuscondition,
            $params, 'quiz  , attempt ASC');
    }
    function assignquiz_get_best_grade($quiz, $userid) {
        global $DB;
        $grade = $DB->get_field('aiquiz_grades', 'grade',
            array('quiz' => $quiz->id, 'userid' => $userid));

        // Need to detect errors/no result, without catching 0 grades.
        if ($grade === false) {
            return null;
        }

        return $grade + 0; // Convert to number.
    }
function assignquiz_update_effective_access($quiz, $userid) {
    global $DB;

    // Check for user override.
    $override = $DB->get_record('aiquiz_overrides', array('quiz' => $quiz->id, 'userid' => $userid));

    if (!$override) {
        $override = new stdClass();
        $override->timeopen = null;
        $override->timeclose = null;
        $override->timelimit = null;
        $override->attempts = null;
        $override->password = null;
    }

    // Check for group overrides.
    $groupings = groups_get_user_groups($quiz->course, $userid);

    if (!empty($groupings[0])) {
        // Select all overrides that apply to the User's groups.
        list($extra, $params) = $DB->get_in_or_equal(array_values($groupings[0]));
        $sql = "SELECT * FROM {aiquiz_overrides}
                WHERE groupid $extra AND quiz = ?";
        $params[] = $quiz->id;
        $records = $DB->get_records_sql($sql, $params);

        // Combine the overrides.
        $opens = array();
        $closes = array();
        $limits = array();
        $attempts = array();
        $passwords = array();

        foreach ($records as $gpoverride) {
            if (isset($gpoverride->timeopen)) {
                $opens[] = $gpoverride->timeopen;
            }
            if (isset($gpoverride->timeclose)) {
                $closes[] = $gpoverride->timeclose;
            }
            if (isset($gpoverride->timelimit)) {
                $limits[] = $gpoverride->timelimit;
            }
            if (isset($gpoverride->attempts)) {
                $attempts[] = $gpoverride->attempts;
            }
            if (isset($gpoverride->password)) {
                $passwords[] = $gpoverride->password;
            }
        }
        // If there is a user override for a setting, ignore the group override.
        if (is_null($override->timeopen) && count($opens)) {
            $override->timeopen = min($opens);
        }
        if (is_null($override->timeclose) && count($closes)) {
            if (in_array(0, $closes)) {
                $override->timeclose = 0;
            } else {
                $override->timeclose = max($closes);
            }
        }
        if (is_null($override->timelimit) && count($limits)) {
            if (in_array(0, $limits)) {
                $override->timelimit = 0;
            } else {
                $override->timelimit = max($limits);
            }
        }
        if (is_null($override->attempts) && count($attempts)) {
            if (in_array(0, $attempts)) {
                $override->attempts = 0;
            } else {
                $override->attempts = max($attempts);
            }
        }
        if (is_null($override->password) && count($passwords)) {
            $override->password = array_shift($passwords);
            if (count($passwords)) {
                $override->extrapasswords = $passwords;
            }
        }

    }

    // Merge with quiz defaults.
    $keys = array('timeopen', 'timeclose', 'timelimit', 'attempts', 'password', 'extrapasswords');
    foreach ($keys as $key) {
        if (isset($override->{$key})) {
            $quiz->{$key} = $override->{$key};
        }
    }

    return $quiz;
}

