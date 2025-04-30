<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU GPL v3 or later.

use OpenAI\Exceptions\UnserializableResponse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');
require_once($CFG->dirroot.'/mod/assignquiz/locallib.php');

/**
 * Pre‑uninstallation hook for the assignquiz module.
 *
 * This is called immediately before all DB tables and data
 * belonging to assignquiz are removed.
 *
 * @return bool True to proceed with uninstall, false to abort.
 */
function xmldb_assignquiz_uninstall() {
//    global $DB, $CFG;
//    $env = parse_ini_file($CFG->dirroot.'/mod/assignquiz/.env');
//    $yourApiKey = $env['OPENAI_API_KEY'];
//    delete_api_key_from_env();
//    try {
//        $client = OpenAI::client($yourApiKey);
//        $client->assistants()->delete(get_config('mod_assignquiz', 'quiz_gen_assistant_id'));
//        $client->assistants()->delete(get_config('mod_assignquiz', 'feedback_gen_assistant_id'));
//    }
//    catch (OpenAI\Exceptions\ErrorException $e) {
//        error_log("Error deleting assistants: " . $e->getMessage());
//        return false;
//    }
//    catch (UnserializableResponse $e){
//        error_log("Error deleting assistants: " . $e->getMessage());
//        return false;
//    }
//    $DB->delete_records('config_plugins', ['name' => 'quiz_gen_assistant_id']);
//    $DB->delete_records('config_plugins', ['name' => 'feedback_gen_assistant_id']);
//    $DB->delete_records('config_plugins', ['name' => 'questiongenmodel']);
//    $DB->delete_records('config_plugins', ['name' => 'feedbackgenmodel']);
}

