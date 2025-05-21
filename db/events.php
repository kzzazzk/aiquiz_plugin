<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\course_created',
        'callback'  => '\mod_aiquiz\observer::course_created',
        'internal'    => false,
        'priority'    => 9999,
    ],
];
