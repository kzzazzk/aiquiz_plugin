<?php

use quizaccess_seb\quiz_settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/accessrule/seb/classes/quiz_settings.php');
class assignquiz_settings extends quiz_settings
{
    const TABLE = 'aiquizaccess_seb_settings';
}
