<?php

namespace mod_aiquiz;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_created(\core\event\course_created $event) {
        global $DB;
        $courseid = $event->objectid;
        $context = \context_course::instance($courseid);
        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);
        // Check if the category already exists.
        $existingcategory = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'name' => '[AI Generated Questions Vault]',
        ]);
        if (!self::vault_category_exists($context->id)) {
            self::add_vault_category($context->id, $coursename);
        }
    }
    public static function vault_category_exists($contextid, $coursename) {
        global $DB;
        $existingcategory = $DB->get_record('question_categories', [
            'contextid' => $contextid,
            'name' => "[AI Question Vault for $coursename course]",
        ]);
        return $existingcategory;
    }

    public static function add_vault_category($contextid, $coursename) {
        global $DB;
        $topcategory = \question_get_top_category($contextid, true);
        $category = new \stdClass();
        $category->name = "[AI Question Vault for $coursename course]";
        $category->info = 'Auto-generated questions from PDFs (aiquiz plugin)';
        $category->contextid = $contextid;
        $category->parent = $topcategory->id;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();

        $DB->insert_record('question_categories', $category);
    }
}
