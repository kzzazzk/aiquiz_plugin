<?php


defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot.'/mod/aiquiz/classes/local/structure/slot_random.php');
require_once($CFG->dirroot.'/mod/aiquiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/ai/OpenAIAdapter.php');

use mod_aiquiz\local\structure\aiquiz_slot_random;
use qbank_previewquestion\question_preview_options;
use mod_aiquiz\ai\OpenAIAdapter;

function aiquiz_has_attempts($quizid) {
    global $DB;
    return $DB->record_exists('aiquiz_attempts', array('quiz' => $quizid, 'preview' => 0));
}
function aiquiz_repaginate_questions($quizid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $sections = $DB->get_records('aiquiz_sections', array('quizid' => $quizid), 'firstslot ASC');
    $firstslots = array();
    foreach ($sections as $section) {
        if ((int)$section->firstslot === 1) {
            continue;
        }
        $firstslots[] = $section->firstslot;
    }

    $slots = $DB->get_records('aiquiz_slots', array('quizid' => $quizid),
        'slot');
    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if (($firstslots && in_array($slot->slot, $firstslots)) ||
            ($slotsonthispage && $slotsonthispage == $slotsperpage)) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('aiquiz_slots', 'page', $currentpage, array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();

    // Log quiz re-paginated event.
    $cm = get_coursemodule_from_instance('aiquiz', $quizid);
    $event = \mod_quiz\event\quiz_repaginated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $quizid,
        'other' => [
            'slotsperpage' => $slotsperpage
        ]
    ]);
    $event->trigger();

}

function aiquiz_delete_previews($quiz, $userid = null) {
    global $DB;
    $conditions = array('quiz' => $quiz->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('aiquiz_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        aiquiz_delete_attempt($attempt, $quiz);
    }
}


function aiquiz_add_quiz_question($questionid, $quiz, $page = 0, $maxmark = null) {
    global $DB;

    if (!isset($quiz->cmid)) {
        $cm = get_coursemodule_from_instance('aiquiz', $quiz->id, $quiz->course);
        $quiz->cmid = $cm->id;
    }

    // Make sue the question is not of the "random" type.
    $questiontype = $DB->get_field('question', 'qtype', array('id' => $questionid));
    if ($questiontype == 'random') {
        throw new coding_exception(
            'Adding "random" questions via quiz_add_quiz_question() is deprecated. Please use quiz_add_random_questions().'
        );
    }

    $trans = $DB->start_delegated_transaction();

    $sql = "SELECT qbe.id
              FROM {aiquiz_slots} slot
              JOIN {question_references} qr ON qr.itemid = slot.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
             WHERE slot.quizid = ?
               AND qr.component = ?
               AND qr.questionarea = ?
               AND qr.usingcontextid = ?";

    $questionslots = $DB->get_records_sql($sql, [$quiz->id, 'mod_aiquiz', 'slot',
        context_module::instance($quiz->cmid)->id]);

    $currententry = get_question_bank_entry($questionid);

    if (array_key_exists($currententry->id, $questionslots)) {
        $trans->allow_commit();
        return false;
    }

    $sql = "SELECT slot.slot, slot.page, slot.id
              FROM {aiquiz_slots} slot
             WHERE slot.quizid = ?
          ORDER BY slot.slot";

    $slots = $DB->get_records_sql($sql, [$quiz->id]);

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new instance.
    $slot = new stdClass();
    $slot->quizid = $quiz->id;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                $DB->set_field('aiquiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

        aiquiz_update_section_firstslots($quiz->id, 1, max($lastslotbefore, 1));

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($quiz->questionsperpage && $numonlastpage >= $quiz->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $slotid = $DB->insert_record('aiquiz_slots', $slot);

    // Update or insert record in question_reference table.
    $sql = "SELECT DISTINCT qr.id, qr.itemid
              FROM {question} q
              JOIN {question_versions} qv ON q.id = qv.questionid
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              JOIN {question_references} qr ON qbe.id = qr.questionbankentryid AND qr.version = qv.version
              JOIN {aiquiz_slots} qs ON qs.id = qr.itemid
             WHERE q.id = ?
               AND qs.id = ?
               AND qr.component = ?
               AND qr.questionarea = ?";
    $qreferenceitem = $DB->get_record_sql($sql, [$questionid, $slotid, 'mod_aiquiz', 'slot']);

    if (!$qreferenceitem) {
        // Create a new reference record for questions created already.
        $questionreferences = new \StdClass();
        $questionreferences->usingcontextid = context_module::instance($quiz->cmid)->id;
        $questionreferences->component = 'mod_aiquiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);

    } else if ($qreferenceitem->itemid === 0 || $qreferenceitem->itemid === null) {
        $questionreferences = new \StdClass();
        $questionreferences->id = $qreferenceitem->id;
        $questionreferences->itemid = $slotid;
        $DB->update_record('question_references', $questionreferences);
    } else {
        // If the reference record exits for another quiz.
        $questionreferences = new \StdClass();
        $questionreferences->usingcontextid = context_module::instance($quiz->cmid)->id;
        $questionreferences->component = 'mod_aiquiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);
    }

    $trans->allow_commit();

    // Log slot created event.
    $cm = get_coursemodule_from_instance('aiquiz', $quiz->id);
    $event = \mod_quiz\event\slot_created::create([
        'context' => context_module::instance($cm->id),
        'objectid' => $slotid,
        'other' => [
            'quizid' => $quiz->id,
            'slotnumber' => $slot->slot,
            'page' => $slot->page
        ]
    ]);
    $event->trigger();
}


function aiquiz_update_section_firstslots($quizid, $direction, $afterslot, $beforeslot = null) {
    global $DB;
    $where = 'quizid = ? AND firstslot > ?';
    $params = [$direction, $quizid, $afterslot];
    if ($beforeslot) {
        $where .= ' AND firstslot < ?';
        $params[] = $beforeslot;
    }
    $firstslotschanges = $DB->get_records_select_menu('aiquiz_sections',
        $where, $params, '', 'firstslot, firstslot + ?');
    update_field_with_unique_index('aiquiz_sections', 'firstslot', $firstslotschanges, ['quizid' => $quizid]);
}


function aiquiz_update_sumgrades($quiz) {
    global $DB;

    $sql = 'UPDATE {aiquiz}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {aiquiz_slots}
                WHERE quizid = {aiquiz}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($quiz->id));
    $quiz->sumgrades = $DB->get_field('aiquiz', 'sumgrades', array('id' => $quiz->id));

    if ($quiz->sumgrades < 0.000005 && aiquiz_has_attempts($quiz->id)) {
        // If the quiz has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        aiquiz_set_grade(0, $quiz);
    }

    $callbackclasses = \core_component::get_plugin_list_with_class('aiquiz', 'quiz_structure_modified');
    foreach ($callbackclasses as $callbackclass) {
        component_class_callback($callbackclass, 'callback', [$quiz->id]);
    }
}

function aiquiz_set_grade($newgrade, $quiz) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($quiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $quiz->grade;
    $quiz->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the quiz table.
    $DB->set_field('aiquiz', 'grade', $newgrade, array('id' => $quiz->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        aiquiz_update_all_final_grades($quiz);

    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {aiquiz_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE quiz = ?
        ", array($newgrade/$oldgrade, $timemodified, $quiz->id));
    }

    if ($oldgrade > 1e-7) {
        // Update the quiz_feedback table.
        $factor = $newgrade/$oldgrade;
        $DB->execute("
                UPDATE {aiquiz_feedback}
                SET mingrade = ? * mingrade, maxgrade = ? * maxgrade
                WHERE quizid = ?
        ", array($factor, $factor, $quiz->id));
    }

    // Update grade item and send all grades to gradebook.
    aiquiz_grade_item_update($quiz);
    aiquiz_update_grades($quiz);

    $transaction->allow_commit();

    // Log quiz grade updated event.
    // We use $num + 0 as a trick to remove the useless 0 digits from decimals.
    $cm = get_coursemodule_from_instance('aiquiz', $quiz->id);
    $event = \mod_quiz\event\quiz_grade_updated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $quiz->id,
        'other' => [
            'oldgrade' => $oldgrade + 0,
            'newgrade' => $newgrade + 0
        ]
    ]);
    $event->trigger();
    return true;
}
function aiquiz_update_all_final_grades($quiz) {
    global $DB;

    if (!$quiz->sumgrades) {
        return;
    }

    $param = array('iquizid' => $quiz->id, 'istatefinished' => quiz_attempt::FINISHED);
    $firstlastattemptjoin = "JOIN (
            SELECT
                iquiza.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {aiquiz_attempts} iquiza

            WHERE
                iquiza.state = :istatefinished AND
                iquiza.preview = 0 AND
                iquiza.quiz = :iquizid

            GROUP BY iquiza.userid
        ) first_last_attempts ON first_last_attempts.userid = quiza.userid";

    switch ($quiz->grademethod) {
        case QUIZ_ATTEMPTFIRST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quiza.attempt = first_last_attempts.firstattempt AND';
            break;

        case QUIZ_ATTEMPTLAST:
            // Because of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quiza.attempt = first_last_attempts.lastattempt AND';
            break;

        case QUIZ_GRADEAVERAGE:
            $select = 'AVG(quiza.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case QUIZ_GRADEHIGHEST:
            $select = 'MAX(quiza.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    if ($quiz->sumgrades >= 0.000005) {
        $finalgrade = $select . ' * ' . ($quiz->grade / $quiz->sumgrades);
    } else {
        $finalgrade = '0';
    }
    $param['quizid'] = $quiz->id;
    $param['quizid2'] = $quiz->id;
    $param['quizid3'] = $quiz->id;
    $param['quizid4'] = $quiz->id;
    $param['statefinished'] = quiz_attempt::FINISHED;
    $param['statefinished2'] = quiz_attempt::FINISHED;
    $finalgradesubquery = "
            SELECT quiza.userid, $finalgrade AS newgrade
            FROM {aiquiz_attempts} quiza
            $join
            WHERE
                $where
                quiza.state = :statefinished AND
                quiza.preview = 0 AND
                quiza.quiz = :quizid3
            GROUP BY quiza.userid";

    $changedgrades = $DB->get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {aiquiz_grades} qg
                WHERE quiz = :quizid
            UNION
                SELECT DISTINCT userid
                FROM {aiquiz_attempts} quiza2
                WHERE
                    quiza2.state = :statefinished2 AND
                    quiza2.preview = 0 AND
                    quiza2.quiz = :quizid2
            ) users

            LEFT JOIN {aiquiz_grades} qg ON qg.userid = users.userid AND qg.quiz = :quizid4

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                ((newgrades.newgrade IS NULL OR qg.grade IS NULL) AND NOT
                          (newgrades.newgrade IS NULL AND qg.grade IS NULL))",
        // The mess on the previous line is detecting where the value is
        // NULL in one column, and NOT NULL in the other, but SQL does
        // not have an XOR operator, and MS SQL server can't cope with
        // (newgrades.newgrade IS NULL) <> (qg.grade IS NULL).
        $param);

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;

        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass();
            $toinsert->quiz = $quiz->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            $DB->insert_record('aiquiz_grades', $toinsert);

        } else {
            $toupdate = new stdClass();
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            $DB->update_record('aiquiz_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        list($test, $params) = $DB->get_in_or_equal($todelete);
        $DB->delete_records_select('aiquiz_grades', 'quiz = ? AND userid ' . $test,
            array_merge(array($quiz->id), $params));
    }
}

    function aiquiz_add_random_questions($quiz, $addonpage, $categoryid, $number,
                                       $includesubcategories, $tagids = []) {
            global $DB;

            $category = $DB->get_record('question_categories', ['id' => $categoryid]);
            if (!$category) {
                new moodle_exception('invalidcategoryid');
            }

            $catcontext = context::instance_by_id($category->contextid);
            require_capability('moodle/question:useall', $catcontext);

            // Tags for filter condition.
            $tags = \core_tag_tag::get_bulk($tagids, 'id, name');
            $tagstrings = [];
            foreach ($tags as $tag) {
                $tagstrings[] = "{$tag->id},{$tag->name}";
            }
            // Create the selected number of random questions.
            for ($i = 0; $i < $number; $i++) {
                // Set the filter conditions.
                $filtercondition = new stdClass();
                $filtercondition->questioncategoryid = $categoryid;
                $filtercondition->includingsubcategories = $includesubcategories ? 1 : 0;
                if (!empty($tagstrings)) {
                    $filtercondition->tags = $tagstrings;
                }

                if (!isset($quiz->cmid)) {
                    $cm = get_coursemodule_from_instance('aiquiz', $quiz->id, $quiz->course);
                    $quiz->cmid = $cm->id;
                }

                // Slot data.
                $randomslotdata = new stdClass();
                $randomslotdata->quizid = $quiz->id;
                $randomslotdata->usingcontextid = context_module::instance($quiz->cmid)->id;
                $randomslotdata->questionscontextid = $category->contextid;
                $randomslotdata->maxmark = 1;

                $randomslot = new aiquiz_slot_random($randomslotdata);
                $randomslot->set_quiz($quiz);
                $randomslot->set_filter_condition($filtercondition);
                $randomslot->insert($addonpage);
            }
        }
    function aiquiz_update_all_attempt_sumgrades($quiz){
        global $DB;
        $dm = new question_engine_data_mapper();
        $timenow = time();

        $sql = "UPDATE {aiquiz_attempts}
            SET
                timemodified = :timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE quiz = :quizid AND state = :finishedstate";
        $DB->execute($sql, array('timenow' => $timenow, 'quizid' => $quiz->id,
            'finishedstate' => quiz_attempt::FINISHED));
    }

    function aiquiz_get_user_attempt_unfinished($quizid, $userid) {
            $attempts = aiquiz_get_user_attempts($quizid, $userid, 'unfinished', true);
            if ($attempts) {
                return array_shift($attempts);
            } else {
                return false;
            }
    }
    function aiquiz_prepare_and_start_new_attempt(aiquiz $quizobj, $attemptnumber, $lastattempt,
                                                     $offlineattempt = false, $forcedrandomquestions = [], $forcedvariants = [], $userid = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
            $ispreviewuser = $quizobj->is_preview_user();
        } else {
            $ispreviewuser = has_capability('mod/quiz:preview', $quizobj->get_context(), $userid);
        }
        // Delete any previous preview attempts belonging to this user.
        aiquiz_delete_previews($quizobj->get_quiz(), $userid);

        $quba = question_engine::make_questions_usage_by_activity('mod_aiquiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        // Create the new attempt and initialize the question sessions
        $timenow = time(); // Update time now, in case the server is running really slowly.
        $attempt = aiquiz_create_attempt($quizobj, $attemptnumber, $lastattempt, $timenow, $ispreviewuser, $userid);

        if (!($quizobj->get_quiz()->attemptonlast && $lastattempt)) {
            $attempt = aiquiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow,
                $forcedrandomquestions, $forcedvariants);
        } else {
            $attempt = quiz_start_attempt_built_on_last($quba, $attempt, $lastattempt);
        }

        $transaction = $DB->start_delegated_transaction();

        // Init the timemodifiedoffline for offline attempts.
        if ($offlineattempt) {
            $attempt->timemodifiedoffline = $attempt->timemodified;
        }
        $attempt = aiquiz_attempt_save_started($quizobj, $quba, $attempt);

        $transaction->allow_commit();

        return $attempt;
    }


function aiquiz_create_attempt(aiquiz $quizobj, $attemptnumber, $lastattempt, $timenow, $ispreview = false, $userid = null) {
    global $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $quiz = $quizobj->get_quiz();
    if ($quiz->sumgrades < 0.000005 && $quiz->grade > 0.000005) {
        throw new moodle_exception('cannotstartgradesmismatch', 'quiz',
            new moodle_url('/mod/aiquiz/view.php', array('q' => $quiz->id)),
            array('grade' => quiz_format_grade($quiz, $quiz->grade)));
    }

    if ($attemptnumber == 1 || !$quiz->attemptonlast) {
        // We are not building on last attempt so create a new attempt.
        $attempt = new stdClass();
        $attempt->quiz = $quiz->id;
        $attempt->userid = $userid;
        $attempt->preview = 0;
        $attempt->layout = '';
    } else {
        // Build on last attempt.
        if (empty($lastattempt)) {
            throw new \moodle_exception('cannotfindprevattempt', 'quiz');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;
    $attempt->timemodifiedoffline = 0;
    $attempt->state = quiz_attempt::IN_PROGRESS;
    $attempt->currentpage = 0;
    $attempt->sumgrades = null;
    $attempt->gradednotificationsenttime = null;

    // If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    $timeclose = $quizobj->get_access_manager($timenow)->get_end_time($attempt);
    if ($timeclose === false || $ispreview) {
        $attempt->timecheckstate = null;
    } else {
        $attempt->timecheckstate = $timeclose;
    }

    return $attempt;
}
function aiquiz_create_attempt_handling_errors($attemptid, $cmid = null) {
    try {
        $attempobj = aiquiz_attempt::create($attemptid);
    } catch (moodle_exception $e) {
        if (!empty($cmid)) {
            list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'aiquiz');
            $continuelink = new moodle_url('/mod/aiquiz/view.php', array('id' => $cmid));
            $context = context_module::instance($cm->id);
            if (has_capability('mod/quiz:preview', $context)) { // hay que arreglar esto par aque no salga siempre la review
                throw new moodle_exception('attempterrorcontentchange', 'quiz', $continuelink);
            } else {
                throw new moodle_exception('attempterrorcontentchangeforuser', 'quiz', $continuelink);
            }
        } else {
            throw new moodle_exception('attempterrorinvalid', 'quiz');
        }
    }
    if (!empty($cmid) && $attempobj->get_cmid() != $cmid) {
        throw new moodle_exception('invalidcoursemodule');
    } else {
        return $attempobj;
    }
}
function aiquiz_validate_new_attempt(aiquiz $quizobj, aiquiz_access_manager $accessmanager, $forcenew, $page, $redirect) {
    global $DB, $USER;
    $timenow = time();

    if ($quizobj->is_preview_user() && $forcenew) {
        $accessmanager->current_attempt_finished();
    }

    // Check capabilities.
    if (!$quizobj->is_preview_user()) {
        $quizobj->require_capability('mod/quiz:attempt');
    }

    // Check to see if a new preview was requested.
    if ($quizobj->is_preview_user() && $forcenew) {
        // To force the creation of a new preview, we mark the current attempt (if any)
        // as abandoned. It will then automatically be deleted below.
        $DB->set_field('aiquiz_attempts', 'state', quiz_attempt::ABANDONED,
            array('quiz' => $quizobj->get_quizid(), 'userid' => $USER->id));
    }

    // Look for an existing attempt.
    $attempts = aiquiz_get_user_attempts($quizobj->get_quizid(), $USER->id, 'all', true);
    $lastattempt = end($attempts);

    $attemptnumber = null;
    // If an in-progress attempt exists, check password then redirect to it.
    if ($lastattempt && ($lastattempt->state == quiz_attempt::IN_PROGRESS ||
            $lastattempt->state == quiz_attempt::OVERDUE)) {
        $currentattemptid = $lastattempt->id;
        $messages = $accessmanager->prevent_access();

        // If the attempt is now overdue, deal with that.
        $quizobj->create_attempt_object($lastattempt)->handle_if_time_expired($timenow, true);

        // And, if the attempt is now no longer in progress, redirect to the appropriate place.
        if ($lastattempt->state == quiz_attempt::ABANDONED || $lastattempt->state == quiz_attempt::FINISHED) {
            if ($redirect) {
                redirect($quizobj->review_url($lastattempt->id));
            } else {
                throw new moodle_quiz_exception($quizobj, 'attemptalreadyclosed');
            }
        }

        // If the page number was not explicitly in the URL, go to the current page.
        if ($page == -1) {
            $page = $lastattempt->currentpage;
        }

    } else {
        while ($lastattempt && $lastattempt->preview) {
            $lastattempt = array_pop($attempts);
        }

        // Get number for the next or unfinished attempt.
        if ($lastattempt) {
            $attemptnumber = $lastattempt->attempt + 1;
        } else {
            $lastattempt = false;
            $attemptnumber = 1;
        }
        $currentattemptid = null;

        $messages = $accessmanager->prevent_access() +
            $accessmanager->prevent_new_attempt(count($attempts), $lastattempt);

        if ($page == -1) {
            $page = 0;
        }
    }
    return array($currentattemptid, $attemptnumber, $lastattempt, $messages, $page);
}
function aiquiz_attempt_save_started($quizobj, $quba, $attempt) {
    global $DB;
    // Save the attempt in the database.
    question_engine::save_questions_usage_by_activity($quba);
    $attempt->uniqueid = $quba->get_id();
    $attempt->id = $DB->insert_record('aiquiz_attempts', $attempt);

    // Params used by the events below.
    $params = array(
        'objectid' => $attempt->id,
        'relateduserid' => $attempt->userid,
        'courseid' => $quizobj->get_courseid(),
        'context' => $quizobj->get_context()
    );
    // Decide which event we are using.
    if ($attempt->preview) {
        $params['other'] = array(
            'quizid' => $quizobj->get_quizid()
        );
        $event = \mod_quiz\event\attempt_preview_started::create($params);
    } else {
        $event = \mod_quiz\event\attempt_started::create($params);

    }

    // Trigger the event.
    $event->add_record_snapshot('aiquiz', $quizobj->get_quiz());
    $event->add_record_snapshot('aiquiz_attempts', $attempt);
    $event->trigger();

    return $attempt;
}
function aiquiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow,
                                $questionids = array(), $forcedvariantsbyslot = array()) {

    // Usages for this user's previous quiz attempts.
    $qubaids = new \mod_quiz\question\qubaids_for_users_attempts(
        $quizobj->get_quizid(), $attempt->userid);

    // Fully load all the questions in this quiz.
    $quizobj->preload_questions();
    $quizobj->load_questions();

    // First load all the non-random questions.
    $randomfound = false;
    $slot = 0;
    $questions = array();
    $maxmark = array();
    $page = array();
    foreach ($quizobj->get_questions() as $questiondata) {
        $slot += 1;
        $maxmark[$slot] = $questiondata->maxmark;
        $page[$slot] = $questiondata->page;
        if ($questiondata->status == \core_question\local\bank\question_version_status::QUESTION_STATUS_DRAFT) {
            throw new moodle_exception('questiondraftonly', 'mod_quiz', '', $questiondata->name);
        }
        if ($questiondata->qtype == 'random') {
            $randomfound = true;
            continue;
        }
        if (!$quizobj->get_quiz()->shuffleanswers) {
            $questiondata->options->shuffleanswers = false;
        }
        $questions[$slot] = question_bank::make_question($questiondata);
    }

    // Then find a question to go in place of each random question.
    if ($randomfound) {
        $slot = 0;
        $usedquestionids = array();
        foreach ($questions as $question) {
            if ($question->id && isset($usedquestions[$question->id])) {
                $usedquestionids[$question->id] += 1;
            } else {
                $usedquestionids[$question->id] = 1;
            }
        }
        $randomloader = new \core_question\local\bank\random_question_loader($qubaids, $usedquestionids);

        foreach ($quizobj->get_questions() as $questiondata) {
            $slot += 1;
            if ($questiondata->qtype != 'random') {
                continue;
            }

            $tagids = qbank_helper::get_tag_ids_for_slot($questiondata);

            // Deal with fixed random choices for testing.
            if (isset($questionids[$quba->next_slot_number()])) {
                if ($randomloader->is_question_available($questiondata->category,
                    (bool) $questiondata->questiontext, $questionids[$quba->next_slot_number()], $tagids)) {
                    $questions[$slot] = question_bank::load_question(
                        $questionids[$quba->next_slot_number()], $quizobj->get_quiz()->shuffleanswers);
                    continue;
                } else {
                    throw new coding_exception('Forced question id not available.');
                }
            }

            // Normal case, pick one at random.
            $questionid = $randomloader->get_next_question_id($questiondata->category,
                $questiondata->randomrecurse, $tagids);
            if ($questionid === null) {
                throw new moodle_exception('notenoughrandomquestions', 'quiz',
                    $quizobj->view_url(), $questiondata);
            }

            $questions[$slot] = question_bank::load_question($questionid,
                $quizobj->get_quiz()->shuffleanswers);
        }
    }

    // Finally add them all to the usage.
    ksort($questions);
    foreach ($questions as $slot => $question) {
        $newslot = $quba->add_question($question, $maxmark[$slot]);
        if ($newslot != $slot) {
            throw new coding_exception('Slot numbers have got confused.');
        }
    }

    // Start all the questions.
    $variantstrategy = new core_question\engine\variants\least_used_strategy($quba, $qubaids);

    if (!empty($forcedvariantsbyslot)) {
        $forcedvariantsbyseed = question_variant_forced_choices_selection_strategy::prepare_forced_choices_array(
            $forcedvariantsbyslot, $quba);
        $variantstrategy = new question_variant_forced_choices_selection_strategy(
            $forcedvariantsbyseed, $variantstrategy);
    }

    $quba->start_all_questions($variantstrategy, $timenow, $attempt->userid);

    // Work out the attempt layout.
    $sections = $quizobj->get_sections();
    foreach ($sections as $i => $section) {
        if (isset($sections[$i + 1])) {
            $sections[$i]->lastslot = $sections[$i + 1]->firstslot - 1;
        } else {
            $sections[$i]->lastslot = count($questions);
        }
    }

    $layout = array();
    foreach ($sections as $section) {
        if ($section->shufflequestions) {
            $questionsinthissection = array();
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                $questionsinthissection[] = $slot;
            }
            shuffle($questionsinthissection);
            $questionsonthispage = 0;
            foreach ($questionsinthissection as $slot) {
                if ($questionsonthispage && $questionsonthispage == $quizobj->get_quiz()->questionsperpage) {
                    $layout[] = 0;
                    $questionsonthispage = 0;
                }
                $layout[] = $slot;
                $questionsonthispage += 1;
            }

        } else {
            $currentpage = $page[$section->firstslot];
            for ($slot = $section->firstslot; $slot <= $section->lastslot; $slot += 1) {
                if ($currentpage !== null && $page[$slot] != $currentpage) {
                    $layout[] = 0;
                }
                $layout[] = $slot;
                $currentpage = $page[$slot];
            }
        }

        // Each section ends with a page break.
        $layout[] = 0;
    }
    $attempt->layout = implode(',', $layout);
    return $attempt;
}
function aiquiz_get_review_options($quiz, $attempt, $context) {
    $options = mod_quiz_display_options::make_from_quiz($quiz, quiz_attempt_state($quiz, $attempt));

    $options->readonly = true;
    $options->flags = quiz_get_flag_option($attempt, $context);
    if (!empty($attempt->id)) {
        $options->questionreviewlink = new moodle_url('/mod/quiz/reviewquestion.php',
            array('attempt' => $attempt->id));
    }

    // Show a link to the comment box only for closed attempts.
    if (!empty($attempt->id) && $attempt->state == quiz_attempt::FINISHED && !$attempt->preview &&
        !is_null($context) && has_capability('mod/quiz:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = new moodle_url('/mod/quiz/comment.php',
            array('attempt' => $attempt->id));
    }

    if (!is_null($context) && !$attempt->preview &&
        has_capability('mod/quiz:viewreports', $context) &&
        has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->numpartscorrect = question_display_options::VISIBLE;
        $options->manualcomment = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
        $options->userinfoinhistory = $attempt->userid;

    }

    return $options;
}

function aiquiz_send_overdue_message($attemptobj) {
    global $CFG, $DB;

    $submitter = $DB->get_record('user', array('id' => $attemptobj->get_userid()), '*', MUST_EXIST);

    if (!$attemptobj->has_capability('mod/quiz:emailwarnoverdue', $submitter->id, false)) {
        return; // Message not required.
    }

    if (!$attemptobj->has_response_to_at_least_one_graded_question()) {
        return; // Message not required.
    }

    // Prepare lots of useful information that admins might want to include in
    // the email message.
    $quizname = format_string($attemptobj->get_quiz_name());

    $deadlines = array();
    if ($attemptobj->get_quiz()->timelimit) {
        $deadlines[] = $attemptobj->get_attempt()->timestart + $attemptobj->get_quiz()->timelimit;
    }
    if ($attemptobj->get_quiz()->timeclose) {
        $deadlines[] = $attemptobj->get_quiz()->timeclose;
    }
    $duedate = min($deadlines);
    $graceend = $duedate + $attemptobj->get_quiz()->graceperiod;

    $a = new stdClass();
    // Course info.
    $a->courseid           = $attemptobj->get_course()->id;
    $a->coursename         = format_string($attemptobj->get_course()->fullname);
    $a->courseshortname    = format_string($attemptobj->get_course()->shortname);
    // Quiz info.
    $a->quizname           = $quizname;
    $a->quizurl            = $attemptobj->view_url();
    $a->quizlink           = '<a href="' . $a->quizurl . '">' . $quizname . '</a>';
    // Attempt info.
    $a->attemptduedate     = userdate($duedate);
    $a->attemptgraceend    = userdate($graceend);
    $a->attemptsummaryurl  = $attemptobj->summary_url()->out(false);
    $a->attemptsummarylink = '<a href="' . $a->attemptsummaryurl . '">' . $quizname . ' review</a>';
    // Student's info.
    $a->studentidnumber    = $submitter->idnumber;
    $a->studentname        = fullname($submitter);
    $a->studentusername    = $submitter->username;

    // Prepare the message.
    $eventdata = new \core\message\message();
    $eventdata->courseid          = $a->courseid;
    $eventdata->component         = 'mod_aiquiz';
    $eventdata->name              = 'attempt_overdue';
    $eventdata->notification      = 1;

    $eventdata->userfrom          = core_user::get_noreply_user();
    $eventdata->userto            = $submitter;
    $eventdata->subject           = get_string('emailoverduesubject', 'quiz', $a);
    $eventdata->fullmessage       = get_string('emailoverduebody', 'quiz', $a);
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';

    $eventdata->smallmessage      = get_string('emailoverduesmall', 'quiz', $a);
    $eventdata->contexturl        = $a->quizurl;
    $eventdata->contexturlname    = $a->quizname;
    $eventdata->customdata        = [
        'cmid' => $attemptobj->get_cmid(),
        'instance' => $attemptobj->get_quizid(),
        'attemptid' => $attemptobj->get_attemptid(),
    ];

    // Send the message.
    return message_send($eventdata);
}
function aiquiz_save_best_grade($quiz, $userid = null, $attempts = array()) {
    global $DB, $OUTPUT, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    if (!$attempts) {
        // Get all the attempts made by the user.
        $attempts = aiquiz_get_user_attempts($quiz->id, $userid);
    }

    // Calculate the best grade.
    $bestgrade = quiz_calculate_best_grade($quiz, $attempts);
    $bestgrade = quiz_rescale_grade($bestgrade, $quiz, false);

    // Save the best grade in the database.
    if (is_null($bestgrade)) {
        $DB->delete_records('aiquiz_grades', array('quiz' => $quiz->id, 'userid' => $userid));

    } else if ($grade = $DB->get_record('aiquiz_grades',
        array('quiz' => $quiz->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->update_record('aiquiz_grades', $grade);

    } else {
        $grade = new stdClass();
        $grade->quiz = $quiz->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        $DB->insert_record('aiquiz_grades', $grade);
    }

    aiquiz_update_grades($quiz, $userid);
}
function aiquiz_has_feedback($quiz) {
    global $DB;
    static $cache = array();
    if (!array_key_exists($quiz->id, $cache)) {
        $cache[$quiz->id] = quiz_has_grades($quiz) &&
            $DB->record_exists_select('aiquiz_feedback', "quizid = ? AND " .
                $DB->sql_isnotempty('aiquiz_feedback', 'feedbacktext', false, true),
                array($quiz->id));
    }
    return $cache[$quiz->id];
}

function aiquiz_feedback_for_grade($attempt, $context, $attempts = null,$maxornot = false) {

    if (!$maxornot){
        $feedback = aiquiz_feedback_record_for_grade($attempt);
    }
    else {
        if (is_array($attempts) && count($attempts) > 0) {
            // Find the attempt with the highest sumgrades
            $max_attempt = $attempts[0]->id;
            foreach ($attempts as $att) {
                if (isset($att->sumgrades) && $att->sumgrades > $max_attempt->sumgrades) {
                    $max_attempt = $att->id;
                }
            }

            // Now get the feedback for the max_attempt
            $feedback = aiquiz_feedback_record_for_grade($max_attempt);
        }
    }
    return $feedback;
}
function aiquiz_feedback_record_for_grade($attempt) {
    global $DB;

    // With CBM etc, it is possible to get -ve grades, which would then not match
    // any feedback. Therefore, we replace -ve grades with 0.$grade = max($grade, 0);

    $feedback = $DB->get_field('aiquiz_feedback','feedbacktext', ['attemptid' => $attempt], MUST_EXIST);
    return $feedback;
}

function process_responses_and_generate_feedback($course_module_id) {
    global $DB, $CFG;
    // Retrieve the stored persistent filename from the DB.
    $quizid = $DB->get_field('course_modules', 'instance', ['id' => $course_module_id]);
    $attemptid = optional_param('attempt', 0, PARAM_INT);
    $context = context_module::instance($course_module_id);
    // Directly retrieve the file from Moodle's File API.
    $numquestions = $DB->count_records('aiquiz_slots',['quizid' => $quizid]);
    $sumgrade = $DB->get_field('aiquiz_attempts', 'sumgrades', ['id' => $attemptid]);
    $grade = ($sumgrade / $numquestions) * 10;
    // Prepare question attempt info for feedback generation.
    $question_usage_id = $DB->get_field('question_usages', 'id', ['contextid' => $context->id]);
    $question_attempt_info = $DB->get_records('question_attempts', ['questionusageid' => $question_usage_id], null, 'questionsummary, rightanswer, responsesummary');
    $question_attempt_info = array_values($question_attempt_info);
    foreach ($question_attempt_info as $question_attempt) {
        if ($question_attempt->responsesummary != $question_attempt->rightanswer) {
            $filtered_question_attempt_info[] = $question_attempt;
        }
    }
    foreach ($filtered_question_attempt_info as $question_attempt) {
        $question_attempt->questionsummary = extract_answer_info_from_summary($question_attempt->questionsummary);
    }

    $filtered_question_attempt_info = json_encode($filtered_question_attempt_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // Call the API using the file content and question attempt info.
    if($grade < 2){
        $response = 'Revisión completa sugerida.';
    }
    elseif($grade == 10){
        $response = '¡Excelente! Sin errores.';
    }
    else{
        $pdftext = get_stored_feedbacksource($course_module_id);
        $env = parse_ini_file($CFG->dirroot . '/mod/aiquiz/.env');
        $openaiadapter = new OpenAIAdapter($env['OPENAI_API_KEY']);
        $response = $openaiadapter->generate_feedback($filtered_question_attempt_info, $pdftext);
        $response = cheer_text_generator($response, $grade);
    }
    // Store the generated feedback.
    $aiquiz_feedback = new stdClass();
    $aiquiz_feedback->quizid = $DB->get_field('course_modules', 'instance', ['id' => $course_module_id]);
    $aiquiz_feedback->attemptid = $attemptid;
    $aiquiz_feedback->feedbacktext = $response;
    $aiquiz_feedback->feedbacktextformat = FORMAT_HTML;

    $DB->insert_record('aiquiz_feedback', $aiquiz_feedback);
}

    function cheer_text_generator($response_text, $grade){
        if ($grade >= 8 && $grade < 10) {
            $cheertext =  "¡Buen trabajo! Muy bien.";
        } elseif ($grade >= 6 && $grade < 8) {
            $cheertext =  "Buen intento, sigue así.";
        } elseif ($grade >= 5 && $grade < 6) {
            $cheertext =  "Buen intento, mejora posible.";
        } elseif ($grade >= 2 && $grade < 5) {
            $cheertext =  "Revisión parcial sugerida.";
        }
        return $cheertext . ' ' . $response_text;
    }

function extract_answer_info_from_summary($question_summary) {
    $question_summary = trim($question_summary); // clean up whitespace
    $pos = strpos($question_summary, ':');

    if ($pos !== false) {
        return substr($question_summary, 0, $pos); // return everything before the first colon
    }

    return $question_summary; // no colon found, return full string
}



function get_stored_feedbacksource($coursemodule_id)
{
    $filename = 'feedbacksource_'.$coursemodule_id.'.pdf';
    $fs = new file_storage();
    $context = context_module::instance($coursemodule_id);
    $pdfFile = $fs->get_file($context->id, 'mod_aiquiz', 'feedbacksource', 0, '/', $filename);
    return $pdfFile->get_content();
}


function aiquiz_delete_attempt($attempt, $quiz) {
    global $DB;
    if (is_numeric($attempt)) {
        if (!$attempt = $DB->get_record('aiquiz_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->quiz != $quiz->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to quiz $attempt->quiz " .
            "but was passed quiz $quiz->id.");
        return;
    }

    if (!isset($quiz->cmid)) {
        $cm = get_coursemodule_from_instance('aiquiz', $quiz->id, $quiz->course);
        $quiz->cmid = $cm->id;
    }

    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);
    $DB->delete_records('aiquiz_attempts', array('id' => $attempt->id));

    // Log the deletion of the attempt if not a preview.
    if (!$attempt->preview) {
        $params = array(
            'objectid' => $attempt->id,
            'relateduserid' => $attempt->userid,
            'context' => context_module::instance($quiz->cmid),
            'other' => array(
                'quizid' => $quiz->id
            )
        );
        $event = \mod_quiz\event\attempt_deleted::create($params);
        $event->add_record_snapshot('aiquiz_attempts', $attempt);
        $event->trigger();

        $callbackclasses = \core_component::get_plugin_list_with_class('quiz', 'quiz_attempt_deleted');
        foreach ($callbackclasses as $callbackclass) {
            component_class_callback($callbackclass, 'callback', [$quiz->id]);
        }
    }

    // Search quiz_attempts for other instances by this user.
    // If none, then delete record for this quiz, this user from quiz_grades
    // else recalculate best grade.
    $userid = $attempt->userid;
    if (!$DB->record_exists('aiquiz_attempts', array('userid' => $userid, 'quiz' => $quiz->id))) {
        $DB->delete_records('aiquiz_grades', array('userid' => $userid, 'quiz' => $quiz->id));
    } else {
        aiquiz_save_best_grade($quiz, $userid);
    }

    aiquiz_update_grades($quiz, $userid);
}

function aiquiz_update_open_attempts(array $conditions) {
    global $DB;

    foreach ($conditions as &$value) {
        if (!is_array($value)) {
            $value = array($value);
        }
    }

    $params = array();
    $wheres = array("quiza.state IN ('inprogress', 'overdue')");
    $iwheres = array("iquiza.state IN ('inprogress', 'overdue')");

    if (isset($conditions['courseid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quiza.quiz IN (SELECT q.id FROM {aiquiz} q WHERE q.course $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['courseid'], SQL_PARAMS_NAMED, 'icid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquiza.quiz IN (SELECT q.id FROM {aiquiz} q WHERE q.course $incond)";
    }

    if (isset($conditions['userid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quiza.userid $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['userid'], SQL_PARAMS_NAMED, 'iuid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquiza.userid $incond";
    }

    if (isset($conditions['quizid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['quizid'], SQL_PARAMS_NAMED, 'qid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quiza.quiz $incond";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['quizid'], SQL_PARAMS_NAMED, 'iqid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquiza.quiz $incond";
    }

    if (isset($conditions['groupid'])) {
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'gid');
        $params = array_merge($params, $inparams);
        $wheres[] = "quiza.quiz IN (SELECT qo.quiz FROM {quiz_overrides} qo WHERE qo.groupid $incond)";
        list ($incond, $inparams) = $DB->get_in_or_equal($conditions['groupid'], SQL_PARAMS_NAMED, 'igid');
        $params = array_merge($params, $inparams);
        $iwheres[] = "iquiza.quiz IN (SELECT qo.quiz FROM {quiz_overrides} qo WHERE qo.groupid $incond)";
    }

    // SQL to compute timeclose and timelimit for each attempt:
    $quizausersql = quiz_get_attempt_usertime_sql(
        implode("\n                AND ", $iwheres));

    // SQL to compute the new timecheckstate
    $timecheckstatesql = "
          CASE WHEN quizauser.usertimelimit = 0 AND quizauser.usertimeclose = 0 THEN NULL
               WHEN quizauser.usertimelimit = 0 THEN quizauser.usertimeclose
               WHEN quizauser.usertimeclose = 0 THEN quiza.timestart + quizauser.usertimelimit
               WHEN quiza.timestart + quizauser.usertimelimit < quizauser.usertimeclose THEN quiza.timestart + quizauser.usertimelimit
               ELSE quizauser.usertimeclose END +
          CASE WHEN quiza.state = 'overdue' THEN quiz.graceperiod ELSE 0 END";

    // SQL to select which attempts to process
    $attemptselect = implode("\n                         AND ", $wheres);

    /*
     * Each database handles updates with inner joins differently:
     *  - mysql does not allow a FROM clause
     *  - postgres and mssql allow FROM but handle table aliases differently
     *  - oracle requires a subquery
     *
     * Different code for each database.
     */

    $dbfamily = $DB->get_dbfamily();
    if ($dbfamily == 'mysql') {
        $updatesql = "UPDATE {aiquiz_attempts} quiza
                        JOIN {aiquiz} quiz ON quiz.id = quiza.quiz
                        JOIN ( $quizausersql ) quizauser ON quizauser.id = quiza.id
                         SET quiza.timecheckstate = $timecheckstatesql
                       WHERE $attemptselect";
    } else if ($dbfamily == 'postgres') {
        $updatesql = "UPDATE {quiz_attempts} quiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {aiquiz} quiz, ( $quizausersql ) quizauser
                       WHERE quiz.id = quiza.quiz
                         AND quizauser.id = quiza.id
                         AND $attemptselect";
    } else if ($dbfamily == 'mssql') {
        $updatesql = "UPDATE quiza
                         SET timecheckstate = $timecheckstatesql
                        FROM {aiquiz_attempts} quiza
                        JOIN {aiquiz} quiz ON quiz.id = quiza.quiz
                        JOIN ( $quizausersql ) quizauser ON quizauser.id = quiza.id
                       WHERE $attemptselect";
    } else {
        // oracle, sqlite and others
        $updatesql = "UPDATE {aiquiz_attempts} quiza
                         SET timecheckstate = (
                           SELECT $timecheckstatesql
                             FROM {aiquiz} quiz, ( $quizausersql ) quizauser
                            WHERE quiz.id = quiza.quiz
                              AND quizauser.id = quiza.id
                         )
                         WHERE $attemptselect";
    }

    $DB->execute($updatesql, $params);
}

function aiquiz_question_action_icons($quiz, $cmid, $question, $returnurl, $variant = null) {
    $html = '';
    if ($question->qtype !== 'random') {
        $html = aiquiz_question_preview_button($quiz, $question, false, $variant);
    }
    $html .= quiz_question_edit_button($cmid, $question, $returnurl);
    return $html;
}
function aiquiz_question_preview_button($quiz, $question, $label = false, $variant = null, $random = null) {
    global $PAGE;
    if (!question_has_capability_on($question, 'use')) {
        return '';
    }
    $structure = aiquiz::create($quiz->id)->get_structure();
    if (!empty($question->slot)) {
        $requestedversion = $structure->get_slot_by_number($question->slot)->requestedversion
            ?? question_preview_options::ALWAYS_LATEST;
    } else {
        $requestedversion = question_preview_options::ALWAYS_LATEST;
    }
    return $PAGE->get_renderer('mod_quiz', 'edit')->question_preview_icon(
        $quiz, $question, $label, $variant, $requestedversion);
}

function get_openai_client() {
    global $CFG;
    $env = parse_ini_file($CFG->dirroot . '/mod/aiquiz/.env');
    $apiKey = $env['OPENAI_API_KEY'] ?? null;

    if (!$apiKey) {
        throw new Exception('OpenAI API key not found.');
    }

    return OpenAI::client($apiKey);
}

function get_assistant_by_name($assistant_name) {
    $client = get_openai_client();
    $list = $client->assistants()->list();

    foreach ($list->data as $assistant) {
        if ($assistant->name === $assistant_name) {
            return $assistant;
        }
    }

    return null; // If not found
}

function assistant_exist($assistant_name) {
    return get_assistant_by_name($assistant_name) !== null;
}

function assistant_model_equivalent_to_openai_model($requested_assistant, $current_assistant) {
    $assistant = get_assistant_by_name($requested_assistant);

    if (!$assistant) {
        return false;
    }

    $assistant_model = $assistant->model;

    return $assistant_model === $current_assistant;
}

function is_openai_apikey_empty() {
    global $CFG;
    $envFile = $CFG->dirroot . '/mod/aiquiz/.env';
    $env = parse_ini_file($envFile);
    return strlen($env['OPENAI_API_KEY']) !== 0;
}

function delete_api_key_from_env() {
    global $CFG;
    $envFile = $CFG->dirroot . '/mod/aiquiz/.env';

    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as &$line) {
            if (strpos($line, 'OPENAI_API_KEY=') === 0) {
                $line = 'OPENAI_API_KEY=';
            }
        }
        file_put_contents($envFile, implode("\n", $lines));
    }
}
function is_openai_api_key_valid($apikey) {
    try {
        $client = OpenAI::client($apikey);
        $client->models()->list();
        return true;
    }
    catch (OpenAI\Exceptions\ErrorException $e){
        return false;
    }
}
