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
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

require_once($CFG->dirroot . '/mod/assignquiz/classes/question/bank/custom_view.php');
require_once($CFG->dirroot . '/mod/assignquiz/attemptlib.php');
require_once($CFG->dirroot.'/vendor/autoload.php');

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
    generate_quiz_questions($moduleinstance);
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
    quiz_process_options($moduleinstance);
    $oldquiz = $DB->get_record('assignquiz', array('id' => $moduleinstance->instance));

    // We need two values from the existing DB record that are not in the form,
    // in some of the function calls below.
    $moduleinstance->sumgrades = $oldquiz->sumgrades;
    $moduleinstance->grade     = $oldquiz->grade;

    // Update the database.
    $moduleinstance->id = $moduleinstance->instance;
    $DB->update_record('assignquiz', $moduleinstance);

    // Do the processing required after an add or an update.
    assignquiz_after_add_or_update($moduleinstance);

    if ($oldquiz->grademethod != $moduleinstance->grademethod) {
        assignquiz_update_all_final_grades($moduleinstance);
        assignquiz_update_grades($moduleinstance);
    }

    $quizdateschanged = $oldquiz->timelimit   != $moduleinstance->timelimit
        || $oldquiz->timeclose   != $moduleinstance->timeclose
        || $oldquiz->graceperiod != $moduleinstance->graceperiod;
    if ($quizdateschanged) {
        assignquiz_update_open_attempts(array('quizid' => $moduleinstance->id));
    }

    // Delete any previous preview attempts.
    assignquiz_delete_previews($moduleinstance);

    // Repaginate, if asked to.
    if (!empty($moduleinstance->repaginatenow) && !assignquiz_has_attempts($moduleinstance->id)) {
        assignquiz_repaginate_questions($moduleinstance->id, $moduleinstance->questionsperpage);
    }

    return true;
}
function assignquiz_after_add_or_update($assignquiz) {
    global $DB;
    $cmid = $assignquiz->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $assignquiz->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Store any settings belonging to the access rules.
    aiquiz_access_manager::save_settings($assignquiz);

    // Update the events relating to this quiz.
    assignquiz_update_events($assignquiz);
    $completionexpected = (!empty($assignquiz->completionexpected)) ? $assignquiz->completionexpected : null;
    \core_completion\api::update_completion_date_event($assignquiz->coursemodule, 'assignquiz', $assignquiz->id, $completionexpected);
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
    $fs = get_file_storage();
    $course_module = get_coursemodule_from_instance('assignquiz', $id, $assignquiz->course, false, MUST_EXIST);
    $contextid = $DB->get_field('context', 'id', array('instanceid' => $course_module->id), MUST_EXIST);
    $fs->delete_area_files($contextid, 'mod_assignquiz', 'feedbacksource', 0);
    $fs->delete_area_files($contextid, 'mod_assignquiz', 'pdftext', 0);
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
        require_once($CFG->dirroot . '/mod/quiz/addrandomform.php');

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
function generate_quiz_questions($data) {
    global $CFG, $DB;

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    $section_name = $DB->get_field('course_sections', 'name', [
        'section' => $data->section,
        'course' => $data->course
    ]);
    $section_name = $section_name ?? 'Seccion con nombre por defecto de Moodle';

    $existing_category = $DB->get_record('question_categories', [
        'name' => 'Preguntas de la sección: ' . $section_name
    ]);

    $question_category_id = $existing_category
        ? $existing_category->id
        : create_question_category($data);

    $tempDir = get_temp_directory($CFG);
    $pdfFiles = process_pdfs($tempDir, $data);

    $mergedPdfTempFilename = (count($pdfFiles) === 1)
        ? $pdfFiles[0]
        : merge_pdfs($pdfFiles, $tempDir);

    // Persist the merged PDF in Moodle's File API
    $persistentfilename = store_file($mergedPdfTempFilename, 'feedbacksource', $data);
    $cm_instance = $DB->get_field('course_modules', 'instance', ['id' => $data->coursemodule]);
    $DB->set_field('assignquiz', 'generativefilename', $persistentfilename, ['id' => $cm_instance]);

    if ($mergedPdfTempFilename && file_exists($mergedPdfTempFilename)) {
        try {
            $response = call_api($mergedPdfTempFilename, $data);
            $formattedResponse = filter_text_format($response);
            add_question_to_question_bank($formattedResponse, $question_category_id, $data);
        } finally {
            // Always clean up the temporary file
            unlink($mergedPdfTempFilename);
        }
    } else {
        error_log("Error: Merged PDF file does not exist.");
    }
}

/**
 * Persists the merged PDF using the Moodle File API.
 */
function store_file($mergedPdfTempFilename, $filearea, $data) {
    $fs = get_file_storage();
    // Get the context from the course module.
    $context = context_module::instance($data->coursemodule);

    // Define a filename (you could incorporate the course module id or a hash)
    $filename = 'merged_moodle_pdf_' . $data->coursemodule . '.pdf';
    $fileinfo = [
        'contextid' => $context->id,
        'component' => 'mod_assignquiz',
        'filearea'  => $filearea,
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $filename,
    ];

    // Create the file in Moodle's file storage.
    $fs->create_file_from_pathname($fileinfo, $mergedPdfTempFilename);
    // Remove the temporary file since it's now stored persistently.
    return $filename;
}


function get_temp_directory($CFG)
{
    $tempDir = $CFG->dataroot . '/temp/assignquiz_pdf/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    return $tempDir;
}
function process_pdfs($tempDir, $data)
{
    $fs = get_file_storage();
    $pdfFiles = [];
    $files = get_pdfs_in_section($data);

    foreach ($files as $pdf) {
        $pdfFile = $fs->get_file($pdf->contextid, $pdf->component, $pdf->filearea, $pdf->itemid, $pdf->filepath, $pdf->filename);
        $file_content = $pdfFile->get_content();

        $tempFilename = $tempDir . uniqid('moodle_pdf_', true) . '.pdf';
        file_put_contents($tempFilename, $file_content);

        $convertedFile = convert_pdf($tempFilename, $tempDir);
        if ($convertedFile) {
            $pdfFiles[] = $convertedFile;
        }
        unlink($tempFilename);
    }
    return $pdfFiles;
}
function convert_pdf($inputFile, $tempDir)
{
    $outputFile = $tempDir . uniqid('converted_moodle_pdf_', true) . '.pdf';
    $gsCmd = 'gswin64c.exe -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dBATCH -sOutputFile="' . $outputFile . '" "' . $inputFile . '" 2>&1';
    exec($gsCmd, $output, $returnVar);
    return $outputFile;
}
function merge_pdfs($pdfFiles, $tempDir)
{
    $mergedPdfTempFilename = $tempDir . uniqid('merged_moodle_pdf_', true) . '.pdf';

    $mergeResult = mergePDFs($pdfFiles, $mergedPdfTempFilename);
    foreach ($pdfFiles as $pdfFile) {
        unlink($pdfFile);
    }

    return $mergeResult ? $mergedPdfTempFilename : null;
}
function create_question_category($data) {
    global $DB, $USER;
    $context_id = $DB->get_field('context', 'id', ['contextlevel' => 50, 'instanceid' => $data->course]);
    $question_category = new stdClass();
    $section_name = $DB->get_field('course_sections', 'name', ['section' => $data->section, 'course' => $data->course]);
    $section_name = $section_name  == null ? 'Undefined': $section_name;
    $question_category->name = 'Preguntas de la sección: '.$section_name;
    $question_category->contextid = $context_id;
    $question_category->info = 'Categoría de preguntas generadas por IA de la sección '.$section_name;
    $top_question_category = $DB->get_field('question_categories', 'id', ['contextid' => $context_id, 'parent' => 0]);
    $question_category->parent = $DB->get_field("question_categories", 'id', ['contextid' => $context_id, 'parent' => $top_question_category]);
    $question_category->sortorder = 999;
    $question_category->stamp = make_unique_id_code();
    $question_category->createdby = $USER->id;
    $question_category->modifiedby = $USER->id;
    $question_category->timecreated = time();
    $question_category->timemodified = time();
    return $DB->insert_record('question_categories', $question_category);
}

function mergePDFs(array $files, string $outputFile): bool
{
    try {
        $pdf = new Fpdi();

        foreach ($files as $file) {
            if (!file_exists($file)) {
                throw new \RuntimeException("File not found: $file");
            }

            $pageCount = $pdf->setSourceFile($file);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $pdf->AddPage();
                $pdf->useTemplate($tplId);
            }
        }

        $pdf->Output('F', $outputFile);

        // Free memory
        unset($pdf);
        gc_collect_cycles();

        return true;
    } catch (PdfParserException $e) {
        error_log("PDF Parser Error: " . $e->getMessage());
        return false;
    } catch (\RuntimeException $e) {
        error_log("Error: " . $e->getMessage());
        return false;
    }
}
function add_question_to_question_bank($response, $question_category_id, $data) {

    global $DB, $USER;
    $i = 1;
    foreach ($response as $question_data) {
        $question = new stdClass();
        $question->name = $question_data['question_name'];
        $question->questiontext = [
            'text' => $question_data['question_name'],
            'format' => FORMAT_HTML,
        ];
        $question->qtype = 'multichoice';
        $question->category = $question_category_id;
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;
        $question->correctfeedback = [
            'text'   => '',
            'format' => FORMAT_HTML,  // for example, FORMAT_HTML
        ];
        $question->partiallycorrectfeedback = [
            'text'   => '',
            'format' => FORMAT_HTML,  // for example, FORMAT_HTML
        ];
        $question->incorrectfeedback = [
            'text'   => '',
            'format' => FORMAT_HTML,  // for example, FORMAT_HTML
        ];
        // Prepare the form object with required parameters.
        $qtype = question_bank::get_qtype('multichoice');
        $form = new stdClass();
        $form->category = $question->category;
        $form->name =  $question->name;
        $form->questiontext = $question->questiontext;
        $form->penalty = 0.3333333;
        $form->single = 1;
        $form->answernumbering = 'abc';
        $form->shuffleanswers = 1;
        $form->partiallycorrectfeedback = $question->partiallycorrectfeedback;
        $form->correctfeedback = $question->correctfeedback;
        $form->incorrectfeedback = $question->incorrectfeedback;


        // Populate answer choices, feedback and fractions.
        $form->answer = array();
        $form->feedback = array();
        $form->fraction = array();
        foreach ($question_data['answer_options'] as $index => $answer_text) {
            // Only add non-empty answers.
            if (trim($answer_text) === '') {
                continue;
            }
            // Answer text with proper formatting.
            $form->answer[$index] = [
                'text' => '<p dir="ltr" style="text-align: left;">' . $answer_text . '</p>',
                'format' => FORMAT_HTML,
            ];
            // Default feedback; you can customize this as needed.
            $form->feedback[$index] = [
                'text' => 'Your feedback here',
                'format' => FORMAT_HTML,
            ];
            // Set fraction: 1.0 for correct answer, -1.0 or 0 for incorrect.
            $form->fraction[$index] = ($index == $question_data['correct_answer_index']) ? 1.0 : 0;
        }

        // Now save the question. The save_question() call will use $form->answer etc.
        $question = $qtype->save_question($question, $form);

        $quiz_slot = new stdClass();
        $quiz_slot->slot = $i;
        $i++;
        $quiz_slot->quizid = $DB->get_field('course_modules', 'instance', ['id' => $data->coursemodule]);
        $quiz_slot->page = 1;
        $quiz_slot->maxmark = 1;

        $slot_id = $DB->insert_record('aiquiz_slots', $quiz_slot);


        // Create question reference.
        $question_reference = new stdClass();
        $question_reference->usingcontextid = context_module::instance($data->coursemodule)->id;
        $question_reference->component = 'mod_assignquiz';
        $question_reference->questionarea = 'slot';
        $question_reference->itemid =  $slot_id; // Usually the question id or slot id
        $question_reference->questionbankentryid = get_question_bank_entry($question->id)->id;

        $DB->insert_record('question_references', $question_reference);

        assignquiz_update_sumgrades($data);

    }
}
function get_pdfs_in_section($data) {
    global $DB;

    $resource_id = $DB->get_field('modules', 'id', ['name' => 'resource']);

    $section_id = $DB->get_field('course_sections', 'id', ['section' => $data->section, 'course' => $data->course]);


    if (!$section_id) {
        return [];
    }

    // Obtener los IDs de los módulos de recursos en la sección
    $module_ids = $DB->get_fieldset_select('course_modules', 'id', 'section = ? AND module = ?', [$section_id, $resource_id]);

    if (empty($module_ids)) {
        return [];
    }

    // Obtener los contextos de los módulos de recursos
    list($in_sql, $params) = $DB->get_in_or_equal($module_ids);
    $context_ids = $DB->get_fieldset_select('context', 'id', "instanceid $in_sql", $params);

    if (empty($context_ids)) {
        return [];
    }

    // Obtener los PDFs en estos contextos
    list($in_sql, $params) = $DB->get_in_or_equal($context_ids);
    $pdfs = $DB->get_records_sql("
        SELECT *
        FROM {files}
        WHERE contextid $in_sql
        AND component = 'mod_resource'
        AND filesize > 0
        AND filename <> '.'
        AND mimetype = 'application/pdf'
    ", $params);

    return $pdfs;
}
function filter_text_format($text) {
    // Define the regex pattern. The pattern captures:
    //   1. The question text after "Pregunta:"
    //   2. Option A text after "A."
    //   3. Option B text after "B."
    //   4. Option C text after "C."
    //   5. Option D text after "D."
    //   6. The correct answer letter (A-D) after "Respuesta correcta:"
    $pattern = '/\s*Pregunta:\s*(.+?)\s*Opciones:\s*A\.\s*(.+?)\s*B\.\s*(.+?)\s*C\.\s*(.+?)\s*D\.\s*(.+?)\s*Respuesta correcta:\s*([A-D])\s*/s';

    // Use preg_match_all to find all matches in the provided text.
    // PREG_SET_ORDER makes $matches an array of match arrays.
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

    $questions_list = array();

    // Loop over each match and build the questions array.
    foreach ($matches as $match) {
        // $match indices:
        //   [1] -> question text
        //   [2] -> option A
        //   [3] -> option B
        //   [4] -> option C
        //   [5] -> option D
        //   [6] -> correct answer letter
        $question_name = $match[1];
        $answer_options = array($match[2], $match[3], $match[4], $match[5]);
        $correct_answer_index = ord($match[6]) - ord('A'); // Convert letter (A-D) to an index (0-3)

        $questions_list[] = array(
            'question_name' => $question_name,
            'answer_options' => $answer_options,
            'correct_answer_index' => $correct_answer_index
        );
    }
    error_log("Questions List: " . json_encode($questions_list, JSON_UNESCAPED_UNICODE));
    // Return the questions list as a pretty-printed JSON string with unescaped Unicode characters.
    return $questions_list;
}
function call_api($filepath, $data)
{
    global $CFG;
    $yourApiKey = $_ENV['OPENAI_API_KEY'];
    $client = OpenAI::client($yourApiKey);
    $pdf_text = Spatie\PdfToText\Pdf::getText($filepath, getenv('POPPLER_PATH'));
    $tempFile = tempnam(get_temp_directory($CFG), 'pdf_to_text');
    file_put_contents($tempFile, "Hello from temp file");
    store_file($tempFile, 'pdftext', $data);
    unlink($tempFile);
    $assistant_id = get_config('mod_assignquiz', 'quiz_gen_assistant_id');
    $create_thread_response = openai_create_thread($client, $pdf_text, $assistant_id);
    return $create_thread_response;
}


function openai_create_thread($client, $text, $assistant_id){
    $thread_create_response = $client->threads()->create([]);

    $client->threads()->messages()->create($thread_create_response->id, [
        'role' => 'assistant',
        'content' => 'Genera 10 preguntas para un cuestionario basándote en el siguiente contenido:'. '\n'. $text,
    ]);
    $response = $client->threads()->runs()->create(
        threadId: $thread_create_response->id,
        parameters: [
            'assistant_id' => $assistant_id,
        ],
    );

    $maxAttempts = 100; // or however many times you want to check
    $attempt = 0;

    do {
        sleep(1); // wait for a second (adjust as needed)
        $runStatus = $response = $client->threads()->runs()->retrieve(
            threadId: $thread_create_response->id,
            runId: $response->id,
        );
        $attempt++;
    } while ($runStatus->status !== 'completed' && $attempt < $maxAttempts);

    if ($runStatus->status === 'completed') {
        $response = $client->threads()->messages()->list($thread_create_response->id);
        return $response->data[0]->content[0]->text->value;
    } else {
        throw new Exception('Run did not complete in the expected time.');
    }
}

function assignquiz_update_grades($quiz, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($quiz->grade == 0) {
        assignquiz_grade_item_update($quiz);

    } else if ($grades = assignquiz_get_user_grades($quiz, $userid)) {
        assignquiz_grade_item_update($quiz, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        assignquiz_grade_item_update($quiz, $grade);

    } else {
        assignquiz_grade_item_update($quiz);
    }
}

function assignquiz_get_user_grades($quiz, $userid = 0) {
    global $CFG, $DB;

    $params = array($quiz->id);
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                qg.grade AS rawgrade,
                qg.timemodified AS dategraded,
                MAX(qa.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {aiquiz_grades} qg ON u.id = qg.userid
            JOIN {aiquiz_attempts} qa ON qa.quiz = qg.quiz AND qa.userid = u.id

            WHERE qg.quiz = ?
            $usertest
            GROUP BY u.id, qg.grade, qg.timemodified", $params);
}

function assignquiz_update_events($quiz, $override = null) {
    global $DB;

    // Load the old events relating to this quiz.
    $conds = array('modulename'=>'assignquiz',
        'instance'=>$quiz->id);
    if (!empty($override)) {
        // Only load events for this override.
        if (isset($override->userid)) {
            $conds['userid'] = $override->userid;
        } else {
            $conds['groupid'] = $override->groupid;
        }
    }
    $oldevents = $DB->get_records('event', $conds, 'id ASC');

    // Now make a to-do list of all that needs to be updated.
    if (empty($override)) {
        // We are updating the primary settings for the quiz, so we need to add all the overrides.
        $overrides = $DB->get_records('aiquiz_overrides', array('quiz' => $quiz->id), 'id ASC');
        // It is necessary to add an empty stdClass to the beginning of the array as the $oldevents
        // list contains the original (non-override) event for the module. If this is not included
        // the logic below will end up updating the wrong row when we try to reconcile this $overrides
        // list against the $oldevents list.
        array_unshift($overrides, new stdClass());
    } else {
        // Just do the one override.
        $overrides = array($override);
    }

    // Get group override priorities.
    $grouppriorities = assignquiz_get_group_override_priorities($quiz->id);

    foreach ($overrides as $current) {
        $groupid   = isset($current->groupid)?  $current->groupid : 0;
        $userid    = isset($current->userid)? $current->userid : 0;
        $timeopen  = isset($current->timeopen)?  $current->timeopen : $quiz->timeopen;
        $timeclose = isset($current->timeclose)? $current->timeclose : $quiz->timeclose;

        // Only add open/close events for an override if they differ from the quiz default.
        $addopen  = empty($current->id) || !empty($current->timeopen);
        $addclose = empty($current->id) || !empty($current->timeclose);

        if (!empty($quiz->coursemodule)) {
            $cmid = $quiz->coursemodule;
        } else {
            $cmid = get_coursemodule_from_instance('assignquiz', $quiz->id, $quiz->course)->id;
        }

        $event = new stdClass();
        $event->type = !$timeclose ? CALENDAR_EVENT_TYPE_ACTION : CALENDAR_EVENT_TYPE_STANDARD;
        $event->description = format_module_intro('assignquiz', $quiz, $cmid, false);
        $event->format = FORMAT_HTML;
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $quiz->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'assignquiz';
        $event->instance    = $quiz->id;
        $event->timestart   = $timeopen;
        $event->timeduration = max($timeclose - $timeopen, 0);
        $event->timesort    = $timeopen;
        $event->visible     = instance_is_visible('assignquiz', $quiz);
        $event->eventtype   = QUIZ_EVENT_TYPE_OPEN;
        $event->priority    = null;

        // Determine the event name and priority.
        if ($groupid) {
            // Group override event.
            $params = new stdClass();
            $params->quiz = $quiz->name;
            $params->group = groups_get_group_name($groupid);
            if ($params->group === false) {
                // Group doesn't exist, just skip it.
                continue;
            }
            $eventname = get_string('overridegroupeventname', 'quiz', $params);
            // Set group override priority.
            if ($grouppriorities !== null) {
                $openpriorities = $grouppriorities['open'];
                if (isset($openpriorities[$timeopen])) {
                    $event->priority = $openpriorities[$timeopen];
                }
            }
        } else if ($userid) {
            // User override event.
            $params = new stdClass();
            $params->quiz = $quiz->name;
            $eventname = get_string('overrideusereventname', 'quiz', $params);
            // Set user override priority.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
        } else {
            // The parent event.
            $eventname = $quiz->name;
        }

        if ($addopen or $addclose) {
            // Separate start and end events.
            $event->timeduration  = 0;
            if ($timeopen && $addopen) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->name = get_string('quizeventopens', 'quiz', $eventname);
                // The method calendar_event::create will reuse a db record if the id field is set.
                calendar_event::create($event, false);
            }
            if ($timeclose && $addclose) {
                if ($oldevent = array_shift($oldevents)) {
                    $event->id = $oldevent->id;
                } else {
                    unset($event->id);
                }
                $event->type      = CALENDAR_EVENT_TYPE_ACTION;
                $event->name      = get_string('quizeventcloses', 'quiz', $eventname);
                $event->timestart = $timeclose;
                $event->timesort  = $timeclose;
                $event->eventtype = QUIZ_EVENT_TYPE_CLOSE;
                if ($groupid && $grouppriorities !== null) {
                    $closepriorities = $grouppriorities['close'];
                    if (isset($closepriorities[$timeclose])) {
                        $event->priority = $closepriorities[$timeclose];
                    }
                }
                calendar_event::create($event, false);
            }
        }
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}

function assignquiz_delete_override($quiz, $overrideid, $log = true) {
    global $DB;

    if (!isset($quiz->cmid)) {
        $cm = get_coursemodule_from_instance('assignquiz', $quiz->id, $quiz->course);
        $quiz->cmid = $cm->id;
    }

    $override = $DB->get_record('aiquiz_overrides', array('id' => $overrideid), '*', MUST_EXIST);

    // Delete the events.
    if (isset($override->groupid)) {
        // Create the search array for a group override.
        $eventsearcharray = array('modulename' => 'quiz',
            'instance' => $quiz->id, 'groupid' => (int)$override->groupid);
        $cachekey = "{$quiz->id}_g_{$override->groupid}";
    } else {
        // Create the search array for a user override.
        $eventsearcharray = array('modulename' => 'quiz',
            'instance' => $quiz->id, 'userid' => (int)$override->userid);
        $cachekey = "{$quiz->id}_u_{$override->userid}";
    }
    $events = $DB->get_records('event', $eventsearcharray);
    foreach ($events as $event) {
        $eventold = calendar_event::load($event);
        $eventold->delete();
    }

    $DB->delete_records('aiquiz_overrides', array('id' => $overrideid));
    cache::make('mod_assignquiz', 'overrides')->delete($cachekey);

    if ($log) {
        // Set the common parameters for one of the events we will be triggering.
        $params = array(
            'objectid' => $override->id,
            'context' => context_module::instance($quiz->cmid),
            'other' => array(
                'quizid' => $override->quiz
            )
        );
        // Determine which override deleted event to fire.
        if (!empty($override->userid)) {
            $params['relateduserid'] = $override->userid;
            $event = \mod_quiz\event\user_override_deleted::create($params);
        } else {
            $params['other']['groupid'] = $override->groupid;
            $event = \mod_quiz\event\group_override_deleted::create($params);
        }

        // Trigger the override deleted event.
        $event->add_record_snapshot('aiquiz_overrides', $override);
        $event->trigger();
    }

    return true;
}
function assignquiz_get_group_override_priorities($quizid) {
    global $DB;

    // Fetch group overrides.
    $where = 'quiz = :quiz AND groupid IS NOT NULL';
    $params = ['quiz' => $quizid];
    $overrides = $DB->get_records_select('aiquiz_overrides', $where, $params, '', 'id, timeopen, timeclose');
    if (!$overrides) {
        return null;
    }

    $grouptimeopen = [];
    $grouptimeclose = [];
    foreach ($overrides as $override) {
        if ($override->timeopen !== null && !in_array($override->timeopen, $grouptimeopen)) {
            $grouptimeopen[] = $override->timeopen;
        }
        if ($override->timeclose !== null && !in_array($override->timeclose, $grouptimeclose)) {
            $grouptimeclose[] = $override->timeclose;
        }
    }

    // Sort open times in ascending manner. The earlier open time gets higher priority.
    sort($grouptimeopen);
    // Set priorities.
    $opengrouppriorities = [];
    $openpriority = 1;
    foreach ($grouptimeopen as $timeopen) {
        $opengrouppriorities[$timeopen] = $openpriority++;
    }

    // Sort close times in descending manner. The later close time gets higher priority.
    rsort($grouptimeclose);
    // Set priorities.
    $closegrouppriorities = [];
    $closepriority = 1;
    foreach ($grouptimeclose as $timeclose) {
        $closegrouppriorities[$timeclose] = $closepriority++;
    }

    return [
        'open' => $opengrouppriorities,
        'close' => $closegrouppriorities
    ];
}

