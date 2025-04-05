<?php

use mod_assignquiz\output\assignquizedit_renderer;
use mod_assignquiz\question\bank\assignquiz_qbank_helper;

require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/assignquiz/lib.php');
require_once($CFG->dirroot . '/mod/assignquiz/accessmanager.php');
require_once($CFG->dirroot . '/mod/assignquiz/classes/structure.php');
require_once($CFG->dirroot . '/mod/assignquiz/classes/question/bank/assignquiz_qbank_helper.php');

class aiquiz extends quiz
{
    public static function create($quizid, $userid = null)
    {
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

    public function get_structure()
    {
        return \mod_assignquiz\assignquiz_structure::create_for_quiz($this);
    }

    public function create_attempt_object($attemptdata)
    {
        return new aiquiz_attempt($attemptdata, $this->quiz, $this->cm, $this->course);
    }

    public function preload_questions() {
        $slots = assignquiz_qbank_helper::get_question_structure($this->quiz->id, $this->context);
        $this->questions = [];
        foreach ($slots as $slot) {
            $this->questions[$slot->questionid] = $slot;
        }
    }

    public function view_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/assignquiz/view.php?id=' . $this->cm->id;
    }
    public function start_attempt_url($page = 0) {
        $params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
        if ($page) {
            $params['page'] = $page;
        }
        return new moodle_url('/mod/assignquiz/startattempt.php', $params);
    }

    public function is_preview_user() {
        if (is_null($this->ispreviewuser)) {
            $this->ispreviewuser = has_capability('mod/assignquiz:preview', $this->context);
        }
        return $this->ispreviewuser;
    }

    public function edit_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/assignquiz/edit.php?cmid=' . $this->cm->id;
    }

    public function get_access_manager($timenow) {
        if (is_null($this->accessmanager)) {
            $this->accessmanager = new aiquiz_access_manager($this, $timenow,
                has_capability('mod/quiz:ignoretimelimits', $this->context, null, false));
        }
        return $this->accessmanager;
    }

    public function attempt_url($attemptid, $page = 0) {
        global $CFG;
        $url = $CFG->wwwroot . '/mod/assignquiz/attempt.php?attempt=' . $attemptid;
        if ($page) {
            $url .= '&page=' . $page;
        }
        $url .= '&cmid=' . $this->get_cmid();
        return $url;
    }
    public function get_sections() {
        global $DB;
        if ($this->sections === null) {
            $this->sections = array_values($DB->get_records('aiquiz_sections',
                array('quizid' => $this->get_quizid()), 'firstslot'));
        }
        return $this->sections;
    }

    public function review_url($attemptid) {
        return new moodle_url('/mod/assignquiz/review.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid()));
    }
}

class aiquiz_attempt extends quiz_attempt{

    public function __construct($attempt, $quiz, $cm, $course, $loadquestions = true) {
        $this->attempt = $attempt;
        $this->quizobj = new aiquiz($quiz, $cm, $course);
        if ($loadquestions) {
            $this->load_questions();
        }
    }
    public function attempt_url($slot = null, $page = -1, $thispage = -1) {
        return $this->page_and_question_url('attempt', $slot, $page, false, $thispage);
    }
    protected function page_and_question_url($script, $slot, $page, $showall, $thispage) {

        $defaultshowall = $this->get_default_show_all($script);
        if ($showall === null && ($page == 0 || $page == -1)) {
            $showall = $defaultshowall;
        }

        // Fix up $page.
        if ($page == -1) {
            if ($slot !== null && !$showall) {
                $page = $this->get_question_page($slot);
            } else {
                $page = 0;
            }
        }

        if ($showall) {
            $page = 0;
        }

        // Add a fragment to scroll down to the question.
        $fragment = '';
        if ($slot !== null) {
            if ($slot == reset($this->pagelayout[$page]) && $thispage != $page) {
                // Changing the page, go to top.
                $fragment = '#';
            } else {
                // Link to the question container.
                $qa = $this->get_question_attempt($slot);
                $fragment = '#' . $qa->get_outer_question_div_unique_id();
            }
        }

        // Work out the correct start to the URL.
        if ($thispage == $page) {
            return new moodle_url($fragment);

        } else {
            $url = new moodle_url('/mod/assignquiz/' . $script . '.php' . $fragment,
                array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
            if ($page == 0 && $showall != $defaultshowall) {
                $url->param('showall', (int) $showall);
            } else if ($page > 0) {
                $url->param('page', $page);
            }
            return $url;
        }
    }
    protected static function create_helper($conditions) {
        global $DB;

        $attempt = $DB->get_record('aiquiz_attempts', $conditions, '*', MUST_EXIST);
        $quiz = aiquiz_access_manager::load_quiz_and_settings($attempt->quiz);
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assignquiz', $quiz->id, $course->id, false, MUST_EXIST);

        // Update quiz with override information.
        $quiz = assignquiz_update_effective_access($quiz, $attempt->userid);

        return new aiquiz_attempt($attempt, $quiz, $cm, $course);
    }
    public static function create($attemptid) {
        return self::create_helper(array('id' => $attemptid));
    }

    public function summary_url() {
        return new moodle_url('/mod/assignquiz/summary.php', array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
    }
    public function load_questions() {
        global $DB;

        if (isset($this->quba)) {
            throw new coding_exception('This quiz attempt has already had the questions loaded.');
        }

        $this->quba = question_engine::load_questions_usage_by_activity($this->attempt->uniqueid);
        $this->slots = $DB->get_records('aiquiz_slots',
            array('quizid' => $this->get_quizid()), 'slot', 'slot, id, requireprevious');
        $this->sections = array_values($DB->get_records('aiquiz_sections',
            array('quizid' => $this->get_quizid()), 'firstslot'));
        $this->link_sections_and_slots();
        $this->determine_layout();
        $this->number_questions();
    }
    public function fire_attempt_viewed_event() {
        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'quizid' => $this->get_quizid(),
                'page' => $this->get_currentpage()
            )
        );
        $event = \mod_quiz\event\attempt_viewed::create($params);
        $event->add_record_snapshot('aiquiz_attempts', $this->get_attempt());
        $event->trigger();
    }
    public function processattempt_url() {
        return new moodle_url('/mod/assignquiz/processattempt.php');
    }
    public function fire_attempt_summary_viewed_event() {

        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'quizid' => $this->get_quizid()
            )
        );
        $event = \mod_quiz\event\attempt_summary_viewed::create($params);
        $event->add_record_snapshot('aiquiz_attempts', $this->get_attempt());
        $event->trigger();
    }

    public function assignquiz_get_navigation_panel(mod_assignquiz_renderer $output,
                                                           $panelclass, $page, $showall = false) {
        $panel = new $panelclass($this, $this->get_display_options(true), $page, $showall);

        $bc = new block_contents();
        $bc->attributes['id'] = 'mod_quiz_navblock';
        $bc->attributes['role'] = 'navigation';
        $bc->title = get_string('quiznavigation', 'quiz');
        $bc->content = $output->navigation_panel($panel);
        return $bc;
    }

    public function process_submitted_actions($timestamp, $becomingoverdue = false, $simulatedresponses = null) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($simulatedresponses !== null) {
            if (is_int(key($simulatedresponses))) {
                // Legacy approach. Should be removed one day.
                $simulatedpostdata = $this->quba->prepare_simulated_post_data($simulatedresponses);
            } else {
                $simulatedpostdata = $simulatedresponses;
            }
        } else {
            $simulatedpostdata = null;
        }

        $this->quba->process_all_actions($timestamp, $simulatedpostdata);
        question_engine::save_questions_usage_by_activity($this->quba);

        $this->attempt->timemodified = $timestamp;
        if ($this->attempt->state == self::FINISHED) {
            $this->attempt->sumgrades = $this->quba->get_total_mark();
        }
        if ($becomingoverdue) {
            $this->process_going_overdue($timestamp, true);
        } else {
            $DB->update_record('aiquiz_attempts', $this->attempt);
        }

        if (!$this->is_preview() && $this->attempt->state == self::FINISHED) {
            quiz_save_best_grade($this->get_quiz(), $this->get_userid());
        }

        $transaction->allow_commit();
    }
    public function process_going_overdue($timestamp, $studentisonline) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->attempt->timemodified = $timestamp;
        $this->attempt->state = self::OVERDUE;
        // If we knew the attempt close time, we could compute when the graceperiod ends.
        // Instead we'll just fix it up through cron.
        $this->attempt->timecheckstate = $timestamp;
        $DB->update_record('aiquiz_attempts', $this->attempt);

        $this->fire_state_transition_event('\mod_quiz\event\attempt_becameoverdue', $timestamp, $studentisonline);

        $transaction->allow_commit();

        assignquiz_send_overdue_message($this);
    }

    protected function fire_state_transition_event($eventclass, $timestamp, $studentisonline) {
        global $USER;
        $quizrecord = $this->get_quiz();
        $params = array(
            'context' => $this->get_quizobj()->get_context(),
            'courseid' => $this->get_courseid(),
            'objectid' => $this->attempt->id,
            'relateduserid' => $this->attempt->userid,
            'other' => array(
                'submitterid' => CLI_SCRIPT ? null : $USER->id,
                'quizid' => $quizrecord->id,
                'studentisonline' => $studentisonline
            )
        );
        $event = $eventclass::create($params);
        $event->add_record_snapshot('aiquiz', $this->get_quiz());
        $event->add_record_snapshot('aiquiz_attempts', $this->get_attempt());
        $event->trigger();
    }
    public function get_display_options($reviewing) {
        if ($reviewing) {
            if (is_null($this->reviewoptions)) {
                $this->reviewoptions = assignquiz_get_review_options($this->get_quiz(),
                    $this->attempt, $this->quizobj->get_context());
                if ($this->is_own_preview()) {
                    // It should  always be possible for a teacher to review their
                    // own preview irrespective of the review options settings.
                    $this->reviewoptions->attempt = true;
                }
            }
            return $this->reviewoptions;

        } else {
            $options = mod_quiz_display_options::make_from_quiz($this->get_quiz(),
                mod_quiz_display_options::DURING);
            $options->flags = quiz_get_flag_option($this->attempt, $this->quizobj->get_context());
            return $options;
        }
    }
    public function review_url($slot = null, $page = -1, $showall = null, $thispage = -1) {
        return $this->page_and_question_url('review', $slot, $page, $showall, $thispage);
    }
    public function process_attempt($timenow, $finishattempt, $timeup, $thispage) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Get key times.
        $accessmanager = $this->get_access_manager($timenow);
        $timeclose = $accessmanager->get_end_time($this->get_attempt());
        $graceperiodmin = get_config('quiz', 'graceperiodmin');

        // Don't enforce timeclose for previews.
        if ($this->is_preview()) {
            $timeclose = false;
        }

        // Check where we are in relation to the end time, if there is one.
        $toolate = false;
        if ($timeclose !== false) {
            if ($timenow > $timeclose - QUIZ_MIN_TIME_TO_CONTINUE) {
                // If there is only a very small amount of time left, there is no point trying
                // to show the student another page of the quiz. Just finish now.
                $timeup = true;
                if ($timenow > $timeclose + $graceperiodmin) {
                    $toolate = true;
                }
            } else {
                // If time is not close to expiring, then ignore the client-side timer's opinion
                // about whether time has expired. This can happen if the time limit has changed
                // since the student's previous interaction.
                $timeup = false;
            }
        }

        // If time is running out, trigger the appropriate action.
        $becomingoverdue = false;
        $becomingabandoned = false;
        if ($timeup) {
            if ($this->get_quiz()->overduehandling === 'graceperiod') {
                if ($timenow > $timeclose + $this->get_quiz()->graceperiod + $graceperiodmin) {
                    // Grace period has run out.
                    $finishattempt = true;
                    $becomingabandoned = true;
                } else {
                    $becomingoverdue = true;
                }
            } else {
                $finishattempt = true;
            }
        }

        if (!$finishattempt) {
            // Just process the responses for this page and go to the next page.
            if (!$toolate) {
                try {
                    $this->process_submitted_actions($timenow, $becomingoverdue);
                    $this->fire_attempt_updated_event();
                } catch (question_out_of_sequence_exception $e) {
                    throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                        $this->attempt_url(null, $thispage));

                } catch (Exception $e) {
                    // This sucks, if we display our own custom error message, there is no way
                    // to display the original stack trace.
                    $debuginfo = '';
                    if (!empty($e->debuginfo)) {
                        $debuginfo = $e->debuginfo;
                    }
                    throw new moodle_exception('errorprocessingresponses', 'question',
                        $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
                }

                if (!$becomingoverdue) {
                    foreach ($this->get_slots() as $slot) {
                        if (optional_param('redoslot' . $slot, false, PARAM_BOOL)) {
                            $this->process_redo_question($slot, $timenow);
                        }
                    }
                }

            } else {
                // The student is too late.
                $this->process_going_overdue($timenow, true);
            }

            $transaction->allow_commit();

            return $becomingoverdue ? self::OVERDUE : self::IN_PROGRESS;
        }

        // Update the quiz attempt record.
        try {
            if ($becomingabandoned) {
                $this->process_abandon($timenow, true);
            } else {
                if (!$toolate || $this->get_quiz()->overduehandling === 'graceperiod') {
                    // Normally, we record the accurate finish time when the student is online.
                    $finishtime = $timenow;
                } else {
                    // But, if there is no grade period, and the final responses were too
                    // late to be processed, record the close time, to reduce confusion.
                    $finishtime = $timeclose;
                }
                $this->process_finish($timenow, !$toolate, $finishtime, true);
            }

        } catch (question_out_of_sequence_exception $e) {
            throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                $this->attempt_url(null, $thispage));

        } catch (Exception $e) {
            // This sucks, if we display our own custom error message, there is no way
            // to display the original stack trace.
            $debuginfo = '';
            if (!empty($e->debuginfo)) {
                $debuginfo = $e->debuginfo;
            }
            throw new moodle_exception('errorprocessingresponses', 'question',
                $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
        }

        // Send the user to the review page.
        $transaction->allow_commit();

        return $becomingabandoned ? self::ABANDONED : self::FINISHED;
    }

    public function process_abandon($timestamp, $studentisonline) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->attempt->timemodified = $timestamp;
        $this->attempt->state = self::ABANDONED;
        $this->attempt->timecheckstate = null;
        $DB->update_record('aiquiz_attempts', $this->attempt);

        $this->fire_state_transition_event('\mod_quiz\event\attempt_abandoned', $timestamp, $studentisonline);

        $transaction->allow_commit();
    }
    public function process_finish($timestamp, $processsubmitted, $timefinish = null, $studentisonline = false) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($processsubmitted) {
            $this->quba->process_all_actions($timestamp);
        }
        $this->quba->finish_all_questions($timestamp);

        question_engine::save_questions_usage_by_activity($this->quba);

        $this->attempt->timemodified = $timestamp;
        $this->attempt->timefinish = $timefinish ?? $timestamp;
        $this->attempt->sumgrades = $this->quba->get_total_mark();
        $this->attempt->state = self::FINISHED;
        $this->attempt->timecheckstate = null;
        $this->attempt->gradednotificationsenttime = null;

        if (!$this->requires_manual_grading() ||
            !has_capability('mod/quiz:emailnotifyattemptgraded', $this->get_quizobj()->get_context(),
                $this->get_userid())) {
            $this->attempt->gradednotificationsenttime = $this->attempt->timefinish;
        }

        $DB->update_record('aiquiz_attempts', $this->attempt);

        if (!$this->is_preview()) {
            assignquiz_save_best_grade($this->get_quiz(), $this->attempt->userid);

            // Trigger event.
            $this->fire_state_transition_event('\mod_quiz\event\attempt_submitted', $timestamp, $studentisonline);

            // Tell any access rules that care that the attempt is over.
            $this->get_access_manager($timestamp)->current_attempt_finished();
        }

        $transaction->allow_commit();
    }
}

abstract class assignquiz_nav_panel_base extends quiz_nav_panel_base {
    public function __construct(aiquiz_attempt $attemptobj, question_display_options $options, $page, $showall)
    {
       parent::__construct($attemptobj, $options, $page, $showall);
    }

}

class assignquiz_attempt_nav_panel extends assignquiz_nav_panel_base
{
    public function get_question_url($slot) {
        if ($this->attemptobj->can_navigate_to($slot)) {
            return $this->attemptobj->attempt_url($slot, -1, $this->page);
        } else {
            return null;
        }
    }

    public function render_before_button_bits(mod_quiz_renderer $output) {
        return html_writer::tag('div', get_string('navnojswarning', 'quiz'),
            array('id' => 'quiznojswarning'));
    }

    public function render_end_bits(mod_quiz_renderer $output) {
        if ($this->page == -1) {
            // Don't link from the summary page to itself.
            return '';
        }
        return html_writer::link($this->attemptobj->summary_url(),
                get_string('endtest', 'quiz'), array('class' => 'endtestlink aalink')) .
            $this->render_restart_preview_link($output);
    }
}
