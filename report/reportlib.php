<?php
function aiquiz_report_get_significant_questions($quiz) {
    $quizobj = \aiquiz::create($quiz->id);
    $structure = \mod_assignquiz\assignquiz_structure::create_for_quiz($quizobj);
    $slots = $structure->get_slots();

    $qsbyslot = [];
    $number = 1;
    foreach ($slots as $slot) {
        // Ignore 'questions' of zero length.
        if ($slot->length == 0) {
            continue;
        }

        $slotreport = new \stdClass();
        $slotreport->slot = $slot->slot;
        $slotreport->id = $slot->questionid;
        $slotreport->qtype = $slot->qtype;
        $slotreport->length = $slot->length;
        $slotreport->number = $number;
        $number += $slot->length;
        $slotreport->maxmark = $slot->maxmark;
        $slotreport->category = $slot->category;

        $qsbyslot[$slotreport->slot] = $slotreport;
    }

    return $qsbyslot;
}
function aiquiz_report_list($context) {
    global $DB;
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = $DB->get_records('quiz_reports', null, 'displayorder DESC', 'name, capability');
    $reportdirs = core_component::get_plugin_list('quiz');

    // Order the reports tab in descending order of displayorder.
    $reportcaps = array();
    foreach ($reports as $key => $report) {
        if (array_key_exists($report->name, $reportdirs)) {
            $reportcaps[$report->name] = $report->capability;
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end.
    foreach ($reportdirs as $reportname => $notused) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = array();
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/quiz:viewreports';
        }
        if (has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }
    return $reportlist;
}
function aiquiz_has_questions($quizid) {
    global $DB;
    return $DB->record_exists('aiquiz_slots', array('quizid' => $quizid));
}
function aiquiz_no_questions_message($quiz, $cm, $context) {
    global $OUTPUT;

    $output = '';
    $output .= $OUTPUT->notification(get_string('noquestions', 'quiz'));
    if (has_capability('mod/quiz:manage', $context)) {
        $output .= $OUTPUT->single_button(new moodle_url('/mod/assignquiz/edit.php',
            array('cmid' => $cm->id)), get_string('editquiz', 'quiz'), 'get');
    }

    return $output;
}
function aiquiz_report_grade_bands($bandwidth, $bands, $quizid, \core\dml\sql_join $usersjoins = null) {
    global $DB;
    if (!is_int($bands)) {
        debugging('$bands passed to quiz_report_grade_bands must be an integer. (' .
            gettype($bands) . ' passed.)', DEBUG_DEVELOPER);
        $bands = (int) $bands;
    }

    if ($usersjoins && !empty($usersjoins->joins)) {
        $userjoin = "JOIN {user} u ON u.id = qg.userid
                {$usersjoins->joins}";
        $usertest = $usersjoins->wheres;
        $params = $usersjoins->params;
    } else {
        $userjoin = '';
        $usertest = '1=1';
        $params = array();
    }
    $sql = "
SELECT band, COUNT(1)

FROM (
    SELECT FLOOR(qg.grade / :bandwidth) AS band
      FROM {aiquiz_grades} qg
    $userjoin
    WHERE $usertest AND qg.quiz = :quizid
) subquery

GROUP BY
    band

ORDER BY
    band";

    $params['quizid'] = $quizid;
    $params['bandwidth'] = $bandwidth;

    $data = $DB->get_records_sql_menu($sql, $params);

    // We need to create array elements with values 0 at indexes where there is no element.
    $data = $data + array_fill(0, $bands + 1, 0);
    ksort($data);

    // Place the maximum (perfect grade) into the last band i.e. make last
    // band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    // just 9 <= g <10.
    $data[$bands - 1] += $data[$bands];
    unset($data[$bands]);

    // See MDL-60632. When a quiz participant achieves an overall negative grade the chart fails to render.
    foreach ($data as $databand => $datanum) {
        if ($databand < 0) {
            $data["0"] += $datanum; // Add to band 0.
            unset($data[$databand]); // Remove entry below 0.
        }
    }

    return $data;
}
