<?php


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/assignquiz/classes/local/structure/slot_random.php');

use mod_assignquiz\local\structure\assignquiz_slot_random;
function assignquiz_has_attempts($quizid) {
    global $DB;
    return $DB->record_exists('aiquiz_attempts', array('quiz' => $quizid, 'preview' => 0));
}
function assignquiz_repaginate_questions($quizid, $slotsperpage) {
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
    $cm = get_coursemodule_from_instance('assignquiz', $quizid);
    $event = \mod_quiz\event\quiz_repaginated::create([
        'context' => \context_module::instance($cm->id),
        'objectid' => $quizid,
        'other' => [
            'slotsperpage' => $slotsperpage
        ]
    ]);
    $event->trigger();

}

function assignquiz_delete_previews($quiz, $userid = null) {
    global $DB;
    $conditions = array('quiz' => $quiz->id, 'preview' => 1);
    if (!empty($userid)) {
        $conditions['userid'] = $userid;
    }
    $previewattempts = $DB->get_records('aiquiz_attempts', $conditions);
    foreach ($previewattempts as $attempt) {
        quiz_delete_attempt($attempt, $quiz);
    }
}


function assignquiz_add_quiz_question($questionid, $quiz, $page = 0, $maxmark = null) {
    global $DB;

    if (!isset($quiz->cmid)) {
        $cm = get_coursemodule_from_instance('assignquiz', $quiz->id, $quiz->course);
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

    $questionslots = $DB->get_records_sql($sql, [$quiz->id, 'mod_assignquiz', 'slot',
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

        quiz_update_section_firstslots($quiz->id, 1, max($lastslotbefore, 1));

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
    $qreferenceitem = $DB->get_record_sql($sql, [$questionid, $slotid, 'mod_assignquiz', 'slot']);

    if (!$qreferenceitem) {
        // Create a new reference record for questions created already.
        $questionreferences = new \StdClass();
        $questionreferences->usingcontextid = context_module::instance($quiz->cmid)->id;
        $questionreferences->component = 'mod_assignquiz';
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
        $questionreferences->component = 'mod_assignquiz';
        $questionreferences->questionarea = 'slot';
        $questionreferences->itemid = $slotid;
        $questionreferences->questionbankentryid = get_question_bank_entry($questionid)->id;
        $questionreferences->version = null; // Always latest.
        $DB->insert_record('question_references', $questionreferences);
    }

    $trans->allow_commit();

    // Log slot created event.
    $cm = get_coursemodule_from_instance('assignquiz', $quiz->id);
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


function assignquiz_update_section_firstslots($quizid, $direction, $afterslot, $beforeslot = null) {
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


function assignquiz_update_sumgrades($quiz) {
    global $DB;

    $sql = 'UPDATE {aiquiz}
            SET sumgrades = COALESCE((
                SELECT SUM(maxmark)
                FROM {aiquiz_slots}
                WHERE quizid = {aiquiz}.id
            ), 0)
            WHERE id = ?';
    $DB->execute($sql, array($quiz->id));
    $quiz->sumgrades = $DB->get_field('assignquiz', 'sumgrades', array('id' => $quiz->id));

    if ($quiz->sumgrades < 0.000005 && assignquiz_has_attempts($quiz->id)) {
        // If the quiz has been attempted, and the sumgrades has been
        // set to 0, then we must also set the maximum possible grade to 0, or
        // we will get a divide by zero error.
        quiz_set_grade(0, $quiz);
    }

    $callbackclasses = \core_component::get_plugin_list_with_class('assignquiz', 'quiz_structure_modified');
    foreach ($callbackclasses as $callbackclass) {
        component_class_callback($callbackclass, 'callback', [$quiz->id]);
    }
}

function assignquiz_set_grade($newgrade, $quiz) {
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
    $DB->set_field('assignquiz', 'grade', $newgrade, array('id' => $quiz->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        quiz_update_all_final_grades($quiz);

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
    assignquiz_grade_item_update($quiz);
    quiz_update_grades($quiz);

    $transaction->allow_commit();

    // Log quiz grade updated event.
    // We use $num + 0 as a trick to remove the useless 0 digits from decimals.
    $cm = get_coursemodule_from_instance('assignquiz', $quiz->id);
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
function assignquiz_update_all_final_grades($quiz) {
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

    function assignquiz_add_random_questions($quiz, $addonpage, $categoryid, $number,
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
                    $cm = get_coursemodule_from_instance('assignquiz', $quiz->id, $quiz->course);
                    $quiz->cmid = $cm->id;
                }

                // Slot data.
                $randomslotdata = new stdClass();
                $randomslotdata->quizid = $quiz->id;
                $randomslotdata->usingcontextid = context_module::instance($quiz->cmid)->id;
                $randomslotdata->questionscontextid = $category->contextid;
                $randomslotdata->maxmark = 1;

                $randomslot = new assignquiz_slot_random($randomslotdata);
                $randomslot->set_quiz($quiz);
                $randomslot->set_filter_condition($filtercondition);
                $randomslot->insert($addonpage);
            }
        }
    function assignquiz_update_all_attempt_sumgrades($quiz){
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

