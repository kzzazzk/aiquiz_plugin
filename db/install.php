<?php
global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');

function xmldb_assignquiz_install() {
    global $DB;
    if (get_config('mod_assignquiz', 'questiongenmodel') === false) {
        set_config('questiongenmodel', 'gpt-4.1-mini', 'mod_assignquiz');
    }

    if (get_config('mod_assignquiz', 'feedbackgenmodel') === false) {
        set_config('feedbackgenmodel', 'gpt-4.1-nano', 'mod_assignquiz');
    }

}
