<?php
namespace mod_aiquiz\external;

use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die;
global $CFG;

require_once($CFG->dirroot.'/mod/aiquiz/lib.php'); // Required for external API methods

class preserve_questions extends external_api {

    /**
     * The method to preserve the AI-generated questions by moving them to a question vault.
     *
     * @param int $quizid The ID of the quiz.
     * @return bool Whether the operation was successful.
     * @throws invalid_parameter_exception If invalid parameters are passed.
     */
    public static function execute($quizid) {
        aiquiz_delete_and_relocate_questions($quizid); // Delete the quiz instance
        return true; // Return success
    }

    // Define the parameters for the external function
    public static function execute_parameters() {
        return new external_function_parameters(
            array(
                'quizid' => new external_value(PARAM_INT, 'Quiz ID')
            )
        );
    }

    // Define the return values for the external function
    public static function execute_returns() {
        return new external_value(PARAM_BOOL, 'Success or failure');
    }
}
