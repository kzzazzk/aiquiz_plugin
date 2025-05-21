<?php
global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');

function xmldb_aiquiz_install() {
    global $DB;
    if (get_config('mod_aiquiz', 'questiongenmodel') === false) {
        set_config('questiongenmodel', 'gpt-4.1-mini', 'mod_aiquiz');
    }

    if (get_config('mod_aiquiz', 'feedbackgenmodel') === false) {
        set_config('feedbackgenmodel', 'gpt-4.1-nano', 'mod_aiquiz');
    }

}
