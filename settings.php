<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
global $CFG, $ADMIN;

/**
 * Settings for the PayPal payment gateway
 *
 * @package    paygw_paypal
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use aiquiz\settings\admin_setting_apikey;
use aiquiz\settings\admin_setting_model;
use aiquiz\settings\admin_setting_prompt_feedback;
use aiquiz\settings\admin_setting_prompt_question;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/vendor/autoload.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/settings/admin_setting_apikey.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/settings/admin_setting_model.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/settings/admin_setting_prompt_question.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/settings/admin_setting_prompt_feedback.php');
require_once($CFG->dirroot . '/mod/aiquiz/locallib.php');
require_once($CFG->dirroot . '/mod/aiquiz/lib.php');

//OpenAI\Exceptions\UnserializableResponse
// Define the settings for the API key
if ($ADMIN->fulltree) {
    $env = parse_ini_file($CFG->dirroot . '/mod/aiquiz/.env');
    $settings->add(new admin_setting_heading('defaultsettings', get_string('openaisettings','aiquiz'), get_string('openaisettingsdescription', 'aiquiz')));
    if (!is_openai_apikey_empty()) {
        //si la apikey es vacía o no válida
        $settings->add(new admin_setting_apikey(
            'mod_aiquiz/apikey',
            get_string('apikey', 'aiquiz'),
            get_string('apikey_desc', 'aiquiz'),
            '', 'OPENAI_API_KEY',
        ));
        $settings->add(new admin_setting_heading(
            'mod_aiquiz/apikeyerror',
            '',
            html_writer::div(
                html_writer::div(
                    get_string('apikeyempty', 'aiquiz'),
                    'alert alert-danger col-sm-9 ml-auto '
                ),
                'form-item row'
            )

        ));
    }
    else if (!is_openai_api_key_valid($env['OPENAI_API_KEY'])) {
        $settings->add(new admin_setting_apikey(
            'mod_aiquiz/apikey',
            get_string('apikey', 'aiquiz'),
            get_string('apikey_desc', 'aiquiz'),
            '', 'OPENAI_API_KEY'));

        $settings->add(new admin_setting_heading(
            'mod_aiquiz/apikeyerror',
            '',
            html_writer::div(
                get_string('apikeyinvalid', 'aiquiz'),
                'alert alert-danger col-sm-9 ml-auto'
            )
        ));
    }
    else {
        $settings->add(new admin_setting_apikey(
            'mod_aiquiz/apikey',
            get_string('apikey', 'aiquiz'),
            get_string('apikey_desc', 'aiquiz'),
            '', 'OPENAI_API_KEY',));

        $env = parse_ini_file($CFG->dirroot . '/mod/aiquiz/.env');
        $yourApiKey = $env['OPENAI_API_KEY'];

        $client = OpenAI::client($yourApiKey);

        // Get model list
        $models = array_column($client->models()->list()->data, 'id');

        // Filter only models starting with "gpt" or "o"
        $models = array_filter($models, fn($id) => str_starts_with($id, 'gpt'));

        // Add known assistant-compatible "o" models manually
        $models = array_merge($models, ['o1', 'o1-2024-12-17', 'o3-mini', 'o3-mini-2025-01-31']);

        // Remove unwanted model types
        $excludePrefixes = ['audio', 'realtime', 'transcribe', 'search', 'instruct', 'tts'];
        foreach ($excludePrefixes as $prefix) {
            $models = array_filter($models, fn($id) => strpos($id, $prefix) === false);
        }

        // Remove duplicates and sort alphabetically
        $models = array_unique($models);
        sort($models);

        // Use model list as select options (key => value)
        $modelOptions = array_combine($models, $models);

        // Add model selection settings
        $settings->add(new admin_setting_model(
            'mod_aiquiz/questiongenmodel',
            get_string('questiongenmodel', 'aiquiz'),
            get_string('questiongenmodeldescription', 'aiquiz'),
            'gpt-4.1-mini',
            $modelOptions,
            'quiz_gen_assistant_id',
        ));
        $settings->add(new admin_setting_prompt_question(
            'questiongenerationprompt', // config key
            get_string('questiongenerationpromptlabel', 'mod_aiquiz'),
            '',
            get_string('questiongenerationpromptdescription', 'mod_aiquiz'),
            'quiz_gen_assistant_id'
        ));


        $settings->add(new admin_setting_model(
            'mod_aiquiz/feedbackgenmodel',
            get_string('feedbackgenmodel', 'aiquiz'),
            get_string('feedbackgenmodeldescription', 'aiquiz'),
            'gpt-4.1-nano',
            $modelOptions,
            'feedback_gen_assistant_id'
        ));


        $settings->add(new admin_setting_prompt_feedback(
            'feedbackgenerationprompt', // config key
            get_string('feedbackgenerationpromptlabel', 'mod_aiquiz'), // label
            '',
            get_string('feedbackgenerationpromptdescription', 'mod_aiquiz'),
            'feedback_gen_assistant_id',
        ));

    }
}

