<?php

use quizaccess_seb\access_manager;
use quizaccess_seb\quiz_settings;
use quizaccess_seb\settings_provider;
use \quizaccess_seb\event\access_prevented;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/seb/rule.php');
require_once($CFG->dirroot . '/mod/assignquiz/accessrule/seb/classes/assignquiz_settings.php');

/**
 * Implementation of the quizaccess_seb plugin.
 *
 * @copyright  2020 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignquizaccess_seb extends quizaccess_seb{
    public static function save_settings($assignquiz)
    {
        $context = context_module::instance($assignquiz->coursemodule);

        if (!settings_provider::can_configure_seb($context)) {
            return;
        }

        if (settings_provider::is_seb_settings_locked($assignquiz->id)) {
            return;
        }

        if (settings_provider::is_conflicting_permissions($context)) {
            return;
        }

        $cm = get_coursemodule_from_instance('assignquiz', $assignquiz->id, $assignquiz->course, false, MUST_EXIST);

        $settings = settings_provider::filter_plugin_settings($assignquiz);
        $settings->quizid = $assignquiz->id;
        $settings->cmid = $cm->id;

        // Get existing settings or create new settings if none exist.
        $quizsettings = assignquiz_settings::get_by_quiz_id($assignquiz->id);
        if (empty($quizsettings)) {
            $quizsettings = new assignquiz_settings(0, $settings);
        } else {
            $settings->id = $quizsettings->get('id');
            $quizsettings->from_record($settings);
        }

        // Process uploaded files if required.
        if ($quizsettings->get('requiresafeexambrowser') == settings_provider::USE_SEB_UPLOAD_CONFIG) {
            $draftitemid = file_get_submitted_draft_itemid('filemanager_sebconfigfile');
            settings_provider::save_filemanager_sebconfigfile_draftarea($draftitemid, $cm->id);
        } else {
            settings_provider::delete_uploaded_config_file($cm->id);
        }

        // Save or delete settings.
        if ($quizsettings->get('requiresafeexambrowser') != settings_provider::USE_SEB_NO) {
            $quizsettings->save();
        } else if ($quizsettings->get('id')) {
            $quizsettings->delete();
        }
    }
    public static function get_settings_sql($quizid) : array {
        return [
            'seb.requiresafeexambrowser AS seb_requiresafeexambrowser, '
            . 'seb.showsebtaskbar AS seb_showsebtaskbar, '
            . 'seb.showwificontrol AS seb_showwificontrol, '
            . 'seb.showreloadbutton AS seb_showreloadbutton, '
            . 'seb.showtime AS seb_showtime, '
            . 'seb.showkeyboardlayout AS seb_showkeyboardlayout, '
            . 'seb.allowuserquitseb AS seb_allowuserquitseb, '
            . 'seb.quitpassword AS seb_quitpassword, '
            . 'seb.linkquitseb AS seb_linkquitseb, '
            . 'seb.userconfirmquit AS seb_userconfirmquit, '
            . 'seb.enableaudiocontrol AS seb_enableaudiocontrol, '
            . 'seb.muteonstartup AS seb_muteonstartup, '
            . 'seb.allowspellchecking AS seb_allowspellchecking, '
            . 'seb.allowreloadinexam AS seb_allowreloadinexam, '
            . 'seb.activateurlfiltering AS seb_activateurlfiltering, '
            . 'seb.filterembeddedcontent AS seb_filterembeddedcontent, '
            . 'seb.expressionsallowed AS seb_expressionsallowed, '
            . 'seb.regexallowed AS seb_regexallowed, '
            . 'seb.expressionsblocked AS seb_expressionsblocked, '
            . 'seb.regexblocked AS seb_regexblocked, '
            . 'seb.allowedbrowserexamkeys AS seb_allowedbrowserexamkeys, '
            . 'seb.showsebdownloadlink AS seb_showsebdownloadlink, '
            . 'sebtemplate.id AS seb_templateid '
            , 'LEFT JOIN {aiquizaccess_seb_settings} seb ON seb.quizid = quiz.id '
            . 'LEFT JOIN {aiquizaccess_seb_template} sebtemplate ON seb.templateid = sebtemplate.id '
            , []
        ];
    }
}