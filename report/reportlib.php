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

    $reports = $DB->get_records('aiquiz_reports', null, 'displayorder DESC', 'name, capability');
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