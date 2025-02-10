<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_assignquiz.
 *
 * @package     mod_assignquiz
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/quiz/accessmanager.php');
class aiquiz_access_manager extends quiz_access_manager
{
    public static function load_quiz_and_settings($quizid) {
        global $DB;
        $rules = self::get_rule_classes();
        list($sql, $params) = self::get_load_sql($quizid, $rules, 'assignquiz.*');
        $quiz = $DB->get_record_sql($sql, $params, MUST_EXIST);

        foreach ($rules as $rule) {
            foreach ($rule::get_extra_settings($quizid) as $name => $value) {
                $quiz->$name = $value;
            }
        }

        return $quiz;
    }
    protected static function get_load_sql($quizid, $rules, $basefields) {
        global $DB;
        $allfields = $basefields;
        $alljoins = '{assignquiz} assignquiz';  // Alias is 'aiquiz'
        $allparams = array('quizid' => $quizid);

        foreach ($rules as $rule) {
            list($fields, $joins, $params) = $rule::get_settings_sql($quizid);
            if ($fields) {
                if ($allfields) {
                    $allfields .= ', ';
                }
                $allfields .= $fields;
            }
            if ($joins) {
                // Ensure the JOIN clauses use the correct alias 'assignquiz'
                $joins = str_replace('quiz.id', 'assignquiz.id', $joins); //For some reason some joins are hardcoded, this fixes it.
                $alljoins .= ' ' . $joins;
            }
            if ($params) {
                $allparams += $params;
            }
        }

        if ($allfields === '') {
            return array('', array());
        }

        return array("SELECT $allfields FROM $alljoins WHERE assignquiz.id = :quizid", $allparams);
    }
}