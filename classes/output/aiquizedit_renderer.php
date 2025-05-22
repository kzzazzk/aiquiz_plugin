<?php
namespace mod_aiquiz\output;
defined('MOODLE_INTERNAL') || die();

use aiquiz_attempt_nav_panel;
use core_question\local\bank\question_version_status;
use mod_quiz\output\edit_renderer;
use mod_aiquiz\aiquiz_structure;
use mod_quiz\question\bank\qbank_helper;
use \mod_quiz\structure;
use \html_writer;
use quiz_nav_panel_base;
use quiz_attempt_nav_panel;

require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/aiquiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/question/bank/custom_view.php');
/**
 * Renderer outputting the quiz editing UI.
 *
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */
class aiquizedit_renderer extends edit_renderer
{
    public function aiquizpage_edit_page(\aiquiz $quizobj, aiquiz_structure $structure,
                              \core_question\local\bank\question_edit_contexts $contexts, \moodle_url $pageurl, array $pagevars) {
        $output = '';

        // Page title.
        $output .= $this->heading(get_string('questions', 'quiz'));

        // Information at the top.
        $output .= $this->quiz_state_warnings($structure);

        $output .= html_writer::start_div('mod_aiquiz-edit-top-controls');

        $output .= html_writer::start_div('d-flex justify-content-between flex-wrap mb-1');
        $output .= html_writer::start_div('d-flex flex-column justify-content-around');
        $output .= $this->quiz_information($structure);
        $output .= html_writer::end_tag('div');
        $output .= $this->maximum_grade_input($structure, $pageurl);
        $output .= html_writer::end_tag('div');

        $output .= html_writer::start_div('d-flex justify-content-between flex-wrap mb-1');
        $output .= html_writer::start_div('mod_aiquiz-edit-action-buttons btn-group edit-toolbar', ['role' => 'group']);
        $output .= $this->repaginate_button($structure, $pageurl);
        $output .= $this->selectmultiple_button($structure);
        $output .= html_writer::end_tag('div');

        $output .= html_writer::start_div('d-flex flex-column justify-content-around');
        $output .= $this->total_marks($quizobj->get_quiz());
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        $output .= $this->selectmultiple_controls($structure);
        $output .= html_writer::end_tag('div');

        // Show the questions organised into sections and pages.
        $output .= $this->start_section_list($structure);
        foreach ($structure->get_sections() as $section) {
            $output .= $this->start_section($structure, $section);
            $output .= $this->questions_in_section($structure, $section, $contexts, $pagevars, $pageurl);

            if ($structure->is_last_section($section)) {
                $output .= \html_writer::start_div('last-add-menu');
                $output .= html_writer::tag('span', $this->add_menu_actions($structure, 0,
                    $pageurl, $contexts, $pagevars), array('class' => 'add-menu-outer'));
                $output .= \html_writer::end_div();
            }

            $output .= $this->end_section();
        }

        $output .= $this->end_section_list();

        // Initialise the JavaScript.
        $this->aiquiz_initialise_editing_javascript($structure, $contexts, $pagevars, $pageurl);

        // Include the contents of any other popups required.
        if ($structure->can_be_edited()) {
            $thiscontext = $contexts->lowest();
            $this->page->requires->js_call_amd('mod_aiquiz/quizquestionbank', 'init', [
                $thiscontext->id
            ]);

            $this->page->requires->js_call_amd('mod_aiquiz/add_random_question', 'init', [
                $thiscontext->id,
                $pagevars['cat'],
                $pageurl->out_as_local_url(true),
                $pageurl->param('cmid'),
                \core\plugininfo\qbank::is_plugin_enabled(\qbank_managecategories\helper::PLUGINNAME),
            ]);

            // Include the question chooser.
            $output .= $this->question_chooser();
        }
        return $output;
    }
    public function questions_in_section(structure $structure, $section,
                                                   $contexts, $pagevars, $pageurl) {

        $output = '';
        foreach ($structure->get_slots_in_section($section->id) as $slot) {
            $output .= $this->question_row($structure, $slot, $contexts, $pagevars, $pageurl);
        }
        return html_writer::tag('ul', $output, array('class' => 'section img-text'));
    }

    public function question_row(structure $structure, $slot, $contexts, $pagevars, $pageurl) {
        $output = '';

        $output .= $this->page_row($structure, $slot, $contexts, $pagevars, $pageurl);

        // Page split/join icon.
        $joinhtml = '';
        if ($structure->can_be_edited() && !$structure->is_last_slot_in_quiz($slot) &&
            !$structure->is_last_slot_in_section($slot)) {
            $joinhtml = $this->page_split_join_button($structure, $slot);
        }
        // Question HTML.
        $questionhtml = $this->aiquiz_question($structure, $slot, $pageurl);
        $qtype = $structure->get_question_type_for_slot($slot);
        $questionclasses = 'activity ' . $qtype . ' qtype_' . $qtype . ' slot';

        $output .= html_writer::tag('li', $questionhtml . $joinhtml,
            array('class' => $questionclasses, 'id' => 'slot-' . $structure->get_slot_id_for_slot($slot),
                'data-canfinish' => $structure->can_finish_during_the_attempt($slot)));

        return $output;
    }
    public function aiquiz_question(aiquiz_structure $structure, int $slot, \moodle_url $pageurl) {
        // Get the data required by the question_slot template.
        $slotid = $structure->get_slot_id_for_slot($slot);

        $output = '';
        $output .= html_writer::start_tag('div');

        if ($structure->can_be_edited()) {
            $output .= $this->question_move_icon($structure, $slot);
        }
        $data = [
            'slotid' => $slotid,
            'canbeedited' => $structure->can_be_edited(),
            'checkbox' => $this->get_checkbox_render($structure, $slot),
            'questionnumber' => $this->question_number($structure->get_displayed_number_for_slot($slot)),
            'questionname' => $this->aiquiz_get_question_name_for_slot($structure, $slot, $pageurl),
            'questionicons' => $this->get_action_icon($structure, $slot, $pageurl),
            'questiondependencyicon' => ($structure->can_be_edited() ? $this->question_dependency_icon($structure, $slot) : ''),
            'versionselection' => false,
            'draftversion' => $structure->get_question_in_slot($slot)->status == question_version_status::QUESTION_STATUS_DRAFT,
        ];

        $data['versionoptions'] = [];
        if ($structure->get_slot_by_number($slot)->qtype !== 'random') {
            $data['versionselection'] = true;
            $data['versionoption'] = $structure->get_version_choices_for_slot($slot);
            $this->page->requires->js_call_amd('mod_aiquiz/question_slot', 'init', [$slotid]);
        }

        // Render the question slot template.
        $output .= $this->render_from_template('mod_aiquiz/question_slot', $data);

        $output .= html_writer::end_tag('div');

        return $output;
    }
    public function aiquiz_initialise_editing_javascript(aiquiz_structure $structure, \core_question\local\bank\question_edit_contexts $contexts, array $pagevars, \moodle_url $pageurl){
        $config = new \stdClass();
        $config->resourceurl = '/mod/aiquiz/edit_rest.php';
        $config->sectionurl = '/mod/aiquiz/edit_rest.php';
        $config->pageparams = array();
        $config->questiondecimalpoints = $structure->get_decimal_places_for_question_marks();
        $config->pagehtml = $this->new_page_template($structure, $contexts, $pagevars, $pageurl);
        $config->addpageiconhtml = $this->add_page_icon_template($structure);

        $this->page->requires->yui_module('moodle-mod_aiquiz-toolboxes',
            'M.mod_aiquiz.init_resource_toolbox',
            array(array(
                'courseid' => $structure->get_courseid(),
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->resourceurl,
                'config' => $config,
            ))
        );
        unset($config->pagehtml);
        unset($config->addpageiconhtml);

        $this->page->requires->strings_for_js(array('areyousureremoveselected'), 'quiz');
        $this->page->requires->yui_module('moodle-mod_aiquiz-toolboxes',
            'M.mod_aiquiz.init_section_toolbox',
            array(array(
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->sectionurl,
                'config' => $config,
            ))
        );

        $this->page->requires->yui_module('moodle-mod_aiquiz-dragdrop', 'M.mod_aiquiz.init_section_dragdrop',
            array(array(
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->sectionurl,
                'config' => $config,
            )), null, true);

        $this->page->requires->yui_module('moodle-mod_aiquiz-dragdrop', 'M.mod_aiquiz.init_resource_dragdrop',
            array(array(
                'courseid' => $structure,
                'quizid' => $structure->get_quizid(),
                'ajaxurl' => $config->resourceurl,
                'config' => $config,
            )), null, true);

        // Require various strings for the command toolbox.
        $this->page->requires->strings_for_js(array(
            'clicktohideshow',
            'deletechecktype',
            'deletechecktypename',
            'edittitle',
            'edittitleinstructions',
            'emptydragdropregion',
            'hide',
            'markedthistopic',
            'markthistopic',
            'move',
            'movecontent',
            'moveleft',
            'movesection',
            'page',
            'question',
            'selectall',
            'show',
            'tocontent',
        ), 'moodle');

        $this->page->requires->strings_for_js(array(
            'addpagebreak',
            'cannotremoveallsectionslots',
            'cannotremoveslots',
            'confirmremovesectionheading',
            'confirmremovequestion',
            'dragtoafter',
            'dragtostart',
            'numquestionsx',
            'sectionheadingedit',
            'sectionheadingremove',
            'sectionnoname',
            'removepagebreak',
            'questiondependencyadd',
            'questiondependencyfree',
            'questiondependencyremove',
            'questiondependsonprevious',
        ), 'quiz');

        foreach (\question_bank::get_all_qtypes() as $qtype => $notused) {
            $this->page->requires->string_for_js('pluginname', 'qtype_' . $qtype);
        }

        return true;
    }
    public function edit_menu_actions(structure $structure, $page,
                                      \moodle_url $pageurl, array $pagevars) {
        $questioncategoryid = question_get_category_id_from_pagevars($pagevars);
        static $str;
        if (!isset($str)) {
            $str = get_strings(array('addasection', 'addaquestion', 'addarandomquestion',
                'addarandomselectedquestion', 'questionbank'), 'quiz');
        }

        // Get section, page, slotnumber and maxmark.
        $actions = array();

        // Add a new question to the quiz.
        $returnurl = new \moodle_url($pageurl, array('addonpage' => $page));
        $params = array('returnurl' => $returnurl->out_as_local_url(false),
            'cmid' => $structure->get_cmid(), 'category' => $questioncategoryid,
            'addonpage' => $page, 'appendqnumstring' => 'addquestion');

        $actions['addaquestion'] = new \action_menu_link_secondary(
            new \moodle_url('/question/bank/editquestion/addquestion.php', $params),
            new \pix_icon('t/add', $str->addaquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addaquestion, array('class' => 'cm-edit-action addquestion', 'data-action' => 'addquestion')
        );

        // Call question bank.
        $icon = new \pix_icon('t/add', $str->questionbank, 'moodle', array('class' => 'iconsmall', 'title' => ''));
        if ($page) {
            $title = get_string('addquestionfrombanktopage', 'quiz', $page);
        } else {
            $title = get_string('addquestionfrombankatend', 'quiz');
        }
        $attributes = array('class' => 'cm-edit-action questionbank',
            'data-header' => $title, 'data-action' => 'questionbank', 'data-addonpage' => $page);
        $actions['questionbank'] = new \action_menu_link_secondary($pageurl, $icon, $str->questionbank, $attributes);

        // Add a random question.
        if ($structure->can_add_random_questions()) {
            $returnurl = new \moodle_url('/mod/aiquiz/edit.php', array('cmid' => $structure->get_cmid(), 'data-addonpage' => $page));
            $params = ['returnurl' => $returnurl, 'cmid' => $structure->get_cmid(), 'appendqnumstring' => 'addarandomquestion'];
            $url = new \moodle_url('/mod/aiquiz/addrandom.php', $params);
            $icon = new \pix_icon('t/add', $str->addarandomquestion, 'moodle', array('class' => 'iconsmall', 'title' => ''));
            $attributes = array('class' => 'cm-edit-action addarandomquestion', 'data-action' => 'addarandomquestion');
            if ($page) {
                $title = get_string('addrandomquestiontopage', 'quiz', $page);
            } else {
                $title = get_string('addrandomquestionatend', 'quiz');
            }
            $attributes = array_merge(array('data-header' => $title, 'data-addonpage' => $page), $attributes);
            $actions['addarandomquestion'] = new \action_menu_link_secondary($url, $icon, $str->addarandomquestion, $attributes);
        }

        // Add a new section to the add_menu if possible. This is always added to the HTML
        // then hidden with CSS when no needed, so that as things are re-ordered, etc. with
        // Ajax it can be relevaled again when necessary.
        $params = array('cmid' => $structure->get_cmid(), 'addsectionatpage' => $page);

        $actions['addasection'] = new \action_menu_link_secondary(
            new \moodle_url($pageurl, $params),
            new \pix_icon('t/add', $str->addasection, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addasection, array('class' => 'cm-edit-action addasection', 'data-action' => 'addasection')
        );

        return $actions;
    }
    public function aiquiz_question_bank_contents(\mod_aiquiz\question\bank\aiquiz_custom_view $questionbank, array $pagevars) {

        $qbank = $questionbank->render($pagevars, 'editq');
        return html_writer::div(html_writer::div($qbank, 'bd'), 'questionbankformforpopup');
    }
    public function aiquiz_random_question(aiquiz_structure $structure, $slotnumber, $pageurl) {
        $question = $structure->get_question_in_slot($slotnumber);
        $slot = $structure->get_slot_by_number($slotnumber);
        $editurl = new \moodle_url('/mod/aiquiz/editrandom.php',
            array('returnurl' => $pageurl->out_as_local_url(), 'slotid' => $slot->id));

        $temp = clone($question);
        $temp->questiontext = '';
        $temp->name = qbank_helper::describe_random_question($slot);
        $instancename = quiz_question_tostring($temp);

        $configuretitle = get_string('configurerandomquestion', 'quiz');
        $qtype = \question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();
        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
            'class' => 'icon activityicon', 'alt' => ' ', 'role' => 'presentation'));

        $editicon = $this->pix_icon('t/edit', $configuretitle, 'moodle', array('title' => ''));
        $qbankurlparams = [
            'cmid' => $structure->get_cmid(),
            'cat' => $slot->category . ',' . $slot->contextid,
            'recurse' => $slot->randomrecurse,
        ];

        $slottags = [];
        if (isset($slot->randomtags)) {
            $slottags = $slot->randomtags;
        }
        foreach ($slottags as $index => $slottag) {
            $slottag = explode(',', $slottag);
            $qbankurlparams["qtagids[{$index}]"] = $slottag[0];
        }

        // If this is a random question, display a link to show the questions
        // selected from in the question bank.
        $qbankurl = new \moodle_url('/question/edit.php', $qbankurlparams);
        $qbanklink = ' ' . \html_writer::link($qbankurl,
                get_string('seequestions', 'quiz'), array('class' => 'mod_aiquiz_random_qbank_link'));

        return html_writer::link($editurl, $icon . $editicon, array('title' => $configuretitle)) .
            ' ' . $instancename . ' ' . $qbanklink;
    }
    public function aiquiz_get_question_name_for_slot(aiquiz_structure $structure, int $slot, \moodle_url $pageurl) : string {
        // Display the link to the question (or do nothing if question has no url).
        if ($structure->get_question_type_for_slot($slot) === 'random') {
            $questionname = $this->aiquiz_random_question($structure, $slot, $pageurl);
        } else {
            $questionname = $this->question_name($structure, $slot, $pageurl);
        }

        return $questionname;
    }


}
