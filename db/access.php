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
 * Plugin version and other meta-data are defined here.
 *
 * @package     mod_aiquiz
 * @copyright   2024 Zakaria Lasry z.lsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined("MOODLE_INTERNAL") || die();

$capabilities = [
    // Ability to see that the AI quiz exists, and the basic information
    // about it, for example the start date and time limit.
    "mod/assignquiz:view" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "guest" => CAP_ALLOW,
            "student" => CAP_ALLOW,
            "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],

    // Ability to add a new AI quiz to the course.
    "mod/assignquiz:addinstance" => [
        "riskbitmask" => RISK_XSS,

        "captype" => "write",
        "contextlevel" => CONTEXT_COURSE,
        "archetypes" => [
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
        "clonepermissionsfrom" => "moodle/course:manageactivities",
    ],

    // Ability to do the AI quiz as a 'student'.
    "mod/assignquiz:attempt" => [
        "riskbitmask" => RISK_SPAM,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "student" => CAP_ALLOW,
        ],
    ],

    // Ability for a 'Student' to review their previous attempts. Review by
    // 'Teachers' is controlled by mod/quiz:viewreports.
    "mod/assignquiz:reviewmyattempts" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "student" => CAP_ALLOW,
        ],
        "clonepermissionsfrom" => "moodle/quiz:attempt",
    ],

    // Edit the AI quiz settings, add and remove questions.
    "mod/assignquiz:manage" => [
        "riskbitmask" => RISK_SPAM,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],

    // Edit the AI quiz overrides.
    "mod/assignquiz:manageoverrides" => [
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],

    // View the AI quiz overrides (only checked for users who don't have mod/aiquiz:manageoverrides.
    "mod/assignquiz:viewoverrides" => [
        "captype" => "read",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],

    // Preview the AI quiz.
    "mod/assignquiz:preview" => [
        "captype" => "write", // Only just a write.
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],
    ],

    // Manually grade and comment on student attempts at a question.
    "mod/assignquiz:gradequiz" => [
        "riskbitmask" => RISK_SPAM | RISK_XSS,
        "captype" => "write",
        "contextlevel" => CONTEXT_MODULE,
        "archetypes" => [
            "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW,
            "manager" => CAP_ALLOW,
        ],

        // Regrade quizzes.
        "mod/assignquiz:regrade" => [
            "riskbitmask" => RISK_SPAM,
            "captype" => "write",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [
                "teacher" => CAP_ALLOW,
                "editingteacher" => CAP_ALLOW,
                "manager" => CAP_ALLOW,
            ],
            "clonepermissionsfrom" => "mod/assignquiz:gradequiz",
        ],

        // View the AI quiz reports.
        "mod/assignquiz:viewreports" => [
            "riskbitmask" => RISK_PERSONAL,
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [
                "teacher" => CAP_ALLOW,
                "editingteacher" => CAP_ALLOW,
                "manager" => CAP_ALLOW,
            ],
        ],

        // Delete attempts using the overview report.
        "mod/assignquiz:deleteattempts" => [
            "riskbitmask" => RISK_DATALOSS,
            "captype" => "write",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [
                "editingteacher" => CAP_ALLOW,
                "manager" => CAP_ALLOW,
            ],
        ],

        // Do not have the time limit imposed. Used for accessibility legislation compliance.
        "mod/assignquiz:ignoretimelimits" => [
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [],
        ],

        // Receive a confirmation message of own AI quiz submission.
        "mod/assignquiz:emailconfirmsubmission" => [
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [],
        ],

        // Receive a notification message of other peoples' AI quiz submissions.
        "mod/assignquiz:emailnotifysubmission" => [
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [],
        ],

        // Receive a notification message when an AI quiz attempt becomes overdue.
        "mod/assignquiz:emailwarnoverdue" => [
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [],
        ],

        // Receive a notification message when an AI quiz attempt manual graded.
        "mod/assignquiz:emailnotifyattemptgraded" => [
            "captype" => "read",
            "contextlevel" => CONTEXT_MODULE,
            "archetypes" => [],
        ],
    ],
];