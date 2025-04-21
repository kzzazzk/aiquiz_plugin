<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU GPL v3 or later.

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');

/**
 * Pre‑uninstallation hook for the assignquiz module.
 *
 * This is called immediately before all DB tables and data
 * belonging to assignquiz are removed.
 *
 * @return bool True to proceed with uninstall, false to abort.
 */
function xmldb_assignquiz_uninstall() {
    global $DB;
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    $yourApiKey = $_ENV['OPENAI_API_KEY'];
    $client = OpenAI::client($yourApiKey);
    error_log("Deleting assistants");
    $client->assistants()->delete(get_config('assignquiz', 'quiz_gen_assistant_id'));
    $client->assistants()->delete(get_config('assignquiz', 'feedback_gen_assistant_id'));
    $DB->delete_records('config_plugins', ['name' => 'quiz_gen_assistant_id']);
    $DB->delete_records('config_plugins', ['name' => 'feedback_gen_assistant_id']);
}

