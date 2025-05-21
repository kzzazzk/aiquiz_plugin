<?php
defined('MOODLE_INTERNAL') || die();

global $CFG;
define('ENV_FILE_PATH', $CFG->dirroot . '/mod/aiquiz/.env');
class EnvAccessor {
    private static ?array $cache = null;
    const KEY_NAME = 'OPENAI_API_KEY';
    public static function get(string $key, mixed $default = null): mixed {

        if (self::$cache === null) {
            $envPath = ENV_FILE_PATH;
            if (file_exists($envPath)) {
                self::$cache = parse_ini_file($envPath);
            } else {
                self::$cache = [];
            }
        }

        return self::$cache[$key] ?? $default;
    }
    public static function write($value): void {
        $value = str_replace(' ', '', $value);

        if (self::$cache === null) {
            self::$cache = parse_ini_file(ENV_FILE_PATH);
        }

        self::$cache[self::KEY_NAME] = $value;

        file_put_contents(ENV_FILE_PATH, self::arrayToIniString(self::$cache));
    }
    /*
     *     public function write_setting($data) {
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
     */
}
