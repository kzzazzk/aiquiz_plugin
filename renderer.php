<?php

class mod_aiquiz_renderer extends mod_quiz_renderer
{
    public function summary_page_controls($attemptobj) {
        $output = '';

        // Return to place button.
        if ($attemptobj->get_state() == quiz_attempt::IN_PROGRESS) {
            $button = new single_button(
                new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage())),
                get_string('returnattempt', 'quiz'));
            $output .= $this->container($this->container($this->render($button),
                'controls'), 'submitbtns mdl-align');
        }

        // Finish attempt button.
        $options = array(
            'attempt' => $attemptobj->get_attemptid(),
            'finishattempt' => 1,
            'timeup' => 0,
            'slots' => '',
            'cmid' => $attemptobj->get_cmid(),
            'sesskey' => sesskey(),
        );

        $button = new single_button(
            new moodle_url($attemptobj->processattempt_url(), $options),
            get_string('submitallandfinish', 'quiz'));
        $button->id = 'responseform';
        $button->class = 'btn-finishattempt';
        $button->formid = 'frm-finishattempt';
        if ($attemptobj->get_state() == quiz_attempt::IN_PROGRESS) {
            $totalunanswered = 0;
            if ($attemptobj->get_quiz()->navmethod == 'free') {
                // Only count the unanswered question if the navigation method is set to free.
                $totalunanswered = $attemptobj->get_number_of_unanswered_questions();
            }
            $this->page->requires->js_call_amd('mod_aiquiz/submission_confirmation', 'init', [$totalunanswered]);
        }
        $button->primary = true;

        $duedate = $attemptobj->get_due_date();
        $message = '';
        if ($attemptobj->get_state() == quiz_attempt::OVERDUE) {
            $message = get_string('overduemustbesubmittedby', 'quiz', userdate($duedate));

        } else if ($duedate) {
            $message = get_string('mustbesubmittedby', 'quiz', userdate($duedate));
        }

        $output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->container($message . $this->container(
                $this->render($button), 'controls'), 'submitbtns mdl-align');

        return $output;
    }
    public function review_page(quiz_attempt $attemptobj, $slots, $page, $showall,
                                             $lastpage, mod_quiz_display_options $displayoptions,
                                             $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
            $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
            $attemptobj);

        $output .= $this->review_next_navigation($attemptobj, $page, $lastpage, $showall);
        $output .= $this->footer();
        return $output;
    }

    public function view_table($quiz, $context, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        $table->caption = get_string('summaryofattempts', 'quiz');
        $table->captionhide = true;
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'quiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quiz') . ' / ' .
                quiz_format_grade($quiz, $quiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('gradenoun') . ' / ' .
                quiz_format_grade($quiz, $quiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                    $attemptobj->is_finished()) {
                    $row[] = quiz_format_grade($quiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quiz_rescale_grade($attemptobj->get_sum_marks(), $quiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                    $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                        && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                        && $attemptobj->get_state() == quiz_attempt::FINISHED
                        && $attemptgrade == $viewobj->mygrade
                        && $quiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = quiz_format_grade($quiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(),
                    $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = aiquiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }
    public function view_result_info($quiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = quiz_get_grading_option_name($quiz->grademethod);
                $a->mygrade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->quizgrade = quiz_format_grade($quiz, $quiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'quiz', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                $a = get_string('outofshort', 'quiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'quiz', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'),
                    array('class' => 'overriddennotice'))."\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'quiz'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'quizteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'quiz'), 3);
            $resultinfo .= html_writer::div(
                    aiquiz_feedback_for_grade($viewobj->mygrade, $quiz, $context),
                    'quizgradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }
    public function quiz_attempt_summary_link_to_reports($quiz, $cm, $context,
                                                         $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = quiz_num_attempt_summary($quiz, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/aiquiz/report/reportlib.php');
        $url = new moodle_url('/mod/aiquiz/report.php', array(
            'id' => $cm->id, 'mode' => quiz_report_default_report($context)));
        return html_writer::link($url, $summary);
    }


}

