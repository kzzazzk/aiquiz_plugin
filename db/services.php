<?php
defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_aiquiz_set_question_version' => [
        'classname' => 'mod_aiquiz\external\submit_question_version',
        'description' => 'Set the version of question that would be required for a given quiz.',
        'type' => 'write',
        'capabilities' => 'mod/quiz:view',
        'ajax' => true,
    ],
    'mod_aiquiz_preserve_questions' => [
        'classname' => 'mod_aiquiz\external\preserve_questions',
        'methodname' => 'execute',
        'description' => 'Preserve AI-generated questions by moving them to a question vault.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/quiz:manage'
    ]
);
