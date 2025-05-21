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

namespace mod_aiquiz\output;

global $CFG;

use moodle_url;
use renderable;
use renderer_base;
use templatable;
use url_select;

/**
 * Render overrides action in the quiz secondary navigation
 *
 * The user/group overrides are now handled in the secondary navigation.
 * This class provides the data for the templates to handle the data for
 * overrides tab.
 *
 * @package mod_quiz
 * @copyright 2021 Sujith Haridasan <sujith@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/mod/quiz/classes/output/overrides_actions.php');
use mod_quiz\output\overrides_actions;
class aiquiz_overrides_actions extends overrides_actions {
    public function __construct(int $cmid, string $mode, bool $canedit, bool $addenabled) {
        $this->cmid = $cmid;
        $this->mode = $mode;
        $this->canedit = $canedit;
        $this->addenabled = $addenabled;
    }

    /**
     * Create the add override button.
     *
     * @param \renderer_base $output an instance of the quiz renderer.
     * @return \single_button the button, ready to reander.
     */
    public function create_add_button(\renderer_base $output): \single_button {
        $addoverrideurl = new moodle_url('/mod/aiquiz/overrideedit.php',
                ['cmid' => $this->cmid, 'action' => 'add' . $this->mode]);

        if ($this->mode === 'group') {
            $label = get_string('addnewgroupoverride', 'quiz');
        } else {
            $label = get_string('addnewuseroverride', 'quiz');
        }

        $addoverridebutton = new \single_button($addoverrideurl, $label, 'get', true);
        if (!$this->addenabled) {
            $addoverridebutton->disabled = true;
        }

        return $addoverridebutton;
    }

    public function export_for_template(renderer_base $output): array {
        global $PAGE;
        $templatecontext = [];

        // Build the navigation drop-down.
        $useroverridesurl = new moodle_url('/mod/aiquiz/overrides.php', ['cmid' => $this->cmid, 'mode' => 'user']);
        $groupoverridesurl = new moodle_url('/mod/aiquiz/overrides.php', ['cmid' => $this->cmid, 'mode' => 'group']);

        $menu = [
            $useroverridesurl->out(false) => get_string('useroverrides', 'quiz'),
            $groupoverridesurl->out(false) => get_string('groupoverrides', 'quiz')
        ];

        $overridesnav = new url_select($menu, $PAGE->url->out(false), null, 'quizoverrides');
        $templatecontext['overridesnav'] = $overridesnav->export_for_template($output);

        // Build the add button - but only if the user can edit.
        if ($this->canedit) {
            $templatecontext['addoverridebutton'] = $this->create_add_button($output)->export_for_template($output);
        }

        return $templatecontext;
    }
}
