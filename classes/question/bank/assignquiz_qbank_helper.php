<?php
namespace mod_assignquiz\question\bank;

use core_question\local\bank\question_version_status;
use core_question\local\bank\random_question_loader;
use mod_quiz\question\bank\qbank_helper;
use qubaid_condition;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessmanager.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');

/**
 * Helper class for question bank and its associated data.
 *
 * @package    mod_quiz
 * @category   question
 * @copyright  2021 Catalyst IT Australia Pty Ltd
 * @author     Safat Shahin <safatshahin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignquiz_qbank_helper extends qbank_helper
{
    public static function get_question_structure(int $quizid, \context_module $quizcontext,
                                                  int $slotid = null): array {
        global $DB;

        $params = [
            'draft' => question_version_status::QUESTION_STATUS_DRAFT,
            'quizcontextid' => $quizcontext->id,
            'quizcontextid2' => $quizcontext->id,
            'quizcontextid3' => $quizcontext->id,
            'quizid' => $quizid,
            'quizid2' => $quizid,
        ];
        $slotidtest = '';
        $slotidtest2 = '';
        if ($slotid !== null) {
            $params['slotid'] = $slotid;
            $params['slotid2'] = $slotid;
            $slotidtest = ' AND slot.id = :slotid';
            $slotidtest2 = ' AND lslot.id = :slotid2';
        }

        // Load all the data about each slot.
        $slotdata = $DB->get_records_sql("
                SELECT slot.slot,
                       slot.id AS slotid,
                       slot.page,
                       slot.maxmark,
                       slot.requireprevious,
                       qsr.filtercondition,
                       qv.status,
                       qv.id AS versionid,
                       qv.version,
                       qr.version AS requestedversion,
                       qv.questionbankentryid,
                       q.id AS questionid,
                       q.*,
                       qc.id AS category,
                       COALESCE(qc.contextid, qsr.questionscontextid) AS contextid

                  FROM {aiquiz_slots} slot

             -- case where a particular question has been added to the quiz.
             LEFT JOIN {question_references} qr ON qr.usingcontextid = :quizcontextid AND qr.component = 'mod_assignquiz'
                                        AND qr.questionarea = 'slot' AND qr.itemid = slot.id
             LEFT JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid

             -- This way of getting the latest version for each slot is a bit more complicated
             -- than we would like, but the simpler SQL did not work in Oracle 11.2.
             -- (It did work fine in Oracle 19.x, so once we have updated our min supported
             -- version we could consider digging the old code out of git history from
             -- just before the commit that added this comment.
             -- For relevant question_bank_entries, this gets the latest non-draft slot number.
             LEFT JOIN (
                   SELECT lv.questionbankentryid,
                          MAX(CASE WHEN lv.status <> :draft THEN lv.version END) AS usableversion,
                          MAX(lv.version) AS anyversion
                     FROM {aiquiz_slots} lslot
                     JOIN {question_references} lqr ON lqr.usingcontextid = :quizcontextid2 AND lqr.component = 'mod_assignquiz'
                                        AND lqr.questionarea = 'slot' AND lqr.itemid = lslot.id
                     JOIN {question_versions} lv ON lv.questionbankentryid = lqr.questionbankentryid
                    WHERE lslot.quizid = :quizid2
                          $slotidtest2
                      AND lqr.version IS NULL
                 GROUP BY lv.questionbankentryid
             ) latestversions ON latestversions.questionbankentryid = qr.questionbankentryid

             LEFT JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                                       -- Either specified version, or latest usable version, or a draft version.
                                       AND qv.version = COALESCE(qr.version,
                                           latestversions.usableversion,
                                           latestversions.anyversion)
             LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
             LEFT JOIN {question} q ON q.id = qv.questionid

             -- Case where a random question has been added.
             LEFT JOIN {question_set_references} qsr ON qsr.usingcontextid = :quizcontextid3 AND qsr.component = 'mod_assignquiz'
                                        AND qsr.questionarea = 'slot' AND qsr.itemid = slot.id

                 WHERE slot.quizid = :quizid
                       $slotidtest

              ORDER BY slot.slot
              ", $params);
        // Unpack the random info from question_set_reference.
        foreach ($slotdata as $slot) {
            // Ensure the right id is the id.
            $slot->id = $slot->slotid;

            if ($slot->filtercondition) {
                // Unpack the information about a random question.
                $filtercondition = json_decode($slot->filtercondition);
                $slot->questionid = 's' . $slot->id; // Sometimes this is used as an array key, so needs to be unique.
                $slot->category = $filtercondition->questioncategoryid;
                $slot->randomrecurse = (bool) $filtercondition->includingsubcategories;
                $slot->randomtags = isset($filtercondition->tags) ? (array) $filtercondition->tags : [];
                $slot->qtype = 'random';
                $slot->name = get_string('random', 'quiz');
                $slot->length = 1;
            } else if ($slot->qtype === null) {
                // This question must have gone missing. Put in a placeholder.
                $slot->questionid = 's' . $slot->id; // Sometimes this is used as an array key, so needs to be unique.
                $slot->category = 0;
                $slot->qtype = 'missingtype';
                $slot->name = get_string('missingquestion', 'quiz');
                $slot->questiontext = ' ';
                $slot->questiontextformat = FORMAT_HTML;
                $slot->length = 1;
            } else if (!\question_bank::qtype_exists($slot->qtype)) {
                // Question of unknown type found in the database. Set to placeholder question types instead.
                $slot->qtype = 'missingtype';
            } else {
                $slot->_partiallyloaded = 1;
            }
        }

        return $slotdata;
    }


}