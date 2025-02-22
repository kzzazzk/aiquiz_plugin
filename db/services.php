<?php
defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_assignquiz_set_question_version' => [
        'classname'     => 'mod_assignquiz\external\submit_question_version',
        'description'   => 'Set the version of question that would be required for a given quiz.',
        'type'          => 'write',
        'capabilities'  => 'mod/assignquiz:view',
        'ajax'          => true,
    ],
);
