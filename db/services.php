<?php
defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_assignquiz_set_question_version' => [
        'classname' => 'mod_assignquiz\external\submit_question_version',
        'description' => 'Set the version of question that would be required for a given quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:view',
        'ajax' => true,
    ],
    'mod_assignquiz_preserve_questions' => [
        'classname' => 'mod_assignquiz\external\preserve_questions',
        'methodname' => 'execute',
        'description' => 'Preserve AI-generated questions by moving them to a question vault.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:manage'
    ]
);
