<?php
namespace aiquiz\settings;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/vendor/autoload.php');
require_once($CFG->dirroot.'/mod/aiquiz/locallib.php');

use OpenAI;

class admin_setting_model extends \admin_setting_configselect {

    private $config_variable;
    public function __construct($name, $visiblename, $description, $defaultsetting, $choices, $config_variable)
    {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $choices);
        $this->config_variable = $config_variable;
    }

    public function write_setting($data) {
        global $CFG;
        $result = parent::write_setting($data);
        $env = parse_ini_file($CFG->dirroot.'/mod/aiquiz/.env');
        if(is_openai_api_key_valid($env['OPENAI_API_KEY']) || is_openai_apikey_empty()){
            if (assistant_exist(get_string($this->config_variable, 'aiquiz')) && !assistant_model_equivalent_to_openai_model($data, get_config('mod_aiquiz', $this->config_variable))) {
                $client = OpenAI::client($env['OPENAI_API_KEY']);
                $client->assistants()->modify(get_config('mod_aiquiz', $this->config_variable), ['model' => $data]);
            }
        }

       return $result;
    }
}
