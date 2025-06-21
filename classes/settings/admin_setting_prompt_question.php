<?php
namespace aiquiz\settings;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/vendor/autoload.php');

use admin_setting_configtextarea;
use OpenAI;

class admin_setting_prompt_question extends admin_setting_configtextarea {


    public function __construct($name, $visiblename, $description, $defaultsetting, $featureid) {
        global $DB;
        $this->featureid = $featureid;

        parent::__construct($name, $visiblename, $description, $defaultsetting);
        if($DB->get_field('config', 'value', ['name' => $name])  == false){
            set_config($this->name, get_string($name,'aiquiz'));
        }
    }



    public function write_setting($data) {
        global $CFG;
        $result = parent::write_setting($data);
        $env = parse_ini_file($CFG->dirroot.'/mod/aiquiz/.env');
        $yourApiKey = $env['OPENAI_API_KEY'];
        $client = OpenAI::client($yourApiKey);

        if(is_openai_api_key_valid($env['OPENAI_API_KEY']) || is_openai_apikey_empty()){
            if(get_config('mod_aiquiz', $this->featureid) == false){
                $assistant_id = quiz_generation_assistant_create($client);
                set_config( $this->featureid, $assistant_id, 'mod_aiquiz');
            }
            else{
                $client = OpenAI::client($env['OPENAI_API_KEY']);
                $client->assistants()->modify(get_config('mod_aiquiz',  $this->featureid), ['instructions' => $data]);
                set_config($this->name, $data);
            }
        }
        return $result;
    }


}
