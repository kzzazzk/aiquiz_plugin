<?php
namespace mod_aiquiz\question\bank;

use mod_quiz\question\bank\custom_view;

class aiquiz_custom_view extends custom_view
{

    public function render($pagevars, $tabname): string
    {
        ob_start();
        $this->display($pagevars, $tabname);
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

    public function add_to_quiz_url($questionid)
    {
        $params = $this->baseurl->params();
        $params['addquestion'] = $questionid;
        $params['sesskey'] = sesskey();
        return new \moodle_url('/mod/aiquiz/edit.php', $params);
    }

    public function display($pagevars, $tabname): void
    {

        $page = $pagevars['qpage'];
        $perpage = $pagevars['qperpage'];
        $cat = $pagevars['cat'];
        $recurse = $pagevars['recurse'];
        $showhidden = $pagevars['showhidden'];
        $showquestiontext = $pagevars['qbshowtext'];
        $tagids = [];
        if (!empty($pagevars['qtagids'])) {
            $tagids = $pagevars['qtagids'];
        }

        echo \html_writer::start_div('questionbankwindow boxwidthwide boxaligncenter');

        $editcontexts = $this->contexts->having_one_edit_tab_cap($tabname);

        // Show the filters and search options.
        $this->wanted_filters($cat, $tagids, $showhidden, $recurse, $editcontexts, $showquestiontext);
        // Continues with list of questions.
        $this->display_question_list($this->baseurl, $cat, null, $page, $perpage,
            $this->contexts->having_cap('moodle/question:add'));
        echo \html_writer::end_div();

    }

    protected function display_bottom_controls(\context $catcontext): void
    {
        $cmoptions = new \stdClass();
        $cmoptions->hasattempts = !empty($this->quizhasattempts);

        $canuseall = has_capability('moodle/question:useall', $catcontext);

        echo \html_writer::start_tag('div', ['class' => 'pt-2']);
        if ($canuseall) {
            // Add selected questions to the quiz.
            $params = array(
                'type' => 'submit',
                'name' => 'add',
                'class' => 'btn btn-primary',
                'value' => get_string('addselectedquestionstoquiz', 'quiz'),
                'data-action' => 'toggle',
                'data-togglegroup' => 'qbank',
                'data-toggle' => 'action',
                'disabled' => true,
            );
            echo \html_writer::empty_tag('input', $params);
        }
        echo \html_writer::end_tag('div');
    }


}
