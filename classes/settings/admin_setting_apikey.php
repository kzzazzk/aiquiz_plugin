<?php
namespace aiquiz\settings;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/vendor/autoload.php');
class admin_setting_apikey extends \admin_setting_configpasswordunmask {

    private $envkey;

    public function __construct($name, $visiblename, $description, $defaultsetting, $envkey) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->envkey = $envkey;
    }

    public function get_setting() {
        global $CFG;
        $env = parse_ini_file($CFG->dirroot.'/mod/aiquiz/.env');
        if ($env[$this->envkey]) {
            $return_value = $env[$this->envkey];
        }
        else{
            $return_value = '';
        }
        // Read from environment
        return $return_value;
    }

    public function write_setting($data) {
        global $CFG;
        $data = str_replace(' ', '', $data);
        // Option 1: Write to a custom file you read on app bootstrap
        $envFile = $CFG->dirroot . '/mod/aiquiz/.env';

        if (!file_exists($envFile) || filesize($envFile) === 0) {
            // If file doesn't exist or is empty, write the new key
            file_put_contents($envFile, "OPENAI_API_KEY={$data}\n");
        } else {
            // Read the .env file
            $envContent = file_get_contents($envFile);

            // Check if OPENAI_API_KEY already exists in the file
            if (strpos($envContent, 'OPENAI_API_KEY=') === false) {
                // Append the key to the file if it doesn't exist
                file_put_contents($envFile, "\nOPENAI_API_KEY={$data}", FILE_APPEND);
                $this->envkey = $data;

            } else {
                // If it exists, replace the existing value
                $envContent = preg_replace('/^OPENAI_API_KEY=.*$/m', "OPENAI_API_KEY={$data}", $envContent);
                file_put_contents($envFile, $envContent);
                $this->envkey = $data;

            }
        }

        return ''; // returning empty string prevents DB storage
    }
}
