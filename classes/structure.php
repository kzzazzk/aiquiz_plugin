<?php
namespace mod_aiquiz;

use mod_aiquiz\question\bank\aiquiz_qbank_helper;
use mod_aiquiz\aiquiz_repaginate;
use mod_quiz\structure;

require_once($CFG->dirroot . '/mod/aiquiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/repaginate.php');
require_once($CFG->dirroot . '/mod/aiquiz/classes/question/bank/aiquiz_qbank_helper.php');

defined('MOODLE_INTERNAL') || die();
class aiquiz_structure extends structure {
    public static function create() {
        return new self();
    }
    public static function create_for_quiz($quizobj) {
        $structure = self::create();
        $structure->quizobj = $quizobj;
        $structure->populate_structure();
        return $structure;
    }
    protected function populate_structure() {
        global $DB;

        $slots = aiquiz_qbank_helper::get_question_structure($this->quizobj->get_quizid(), $this->quizobj->get_context());

        $this->questions = [];
        $this->slotsinorder = [];
        foreach ($slots as $slotdata) {
            $this->questions[$slotdata->questionid] = $slotdata;

            $slot = clone($slotdata);
            $slot->quizid = $this->quizobj->get_quizid();
            $this->slotsinorder[$slot->slot] = $slot;
        }

        // Get quiz sections in ascending order of the firstslot.
        $this->sections = $DB->get_records('aiquiz_sections', ['quizid' => $this->quizobj->get_quizid()], 'firstslot');
        $this->populate_slots_with_sections();
        $this->populate_question_numbers();
    }
    public function check_can_be_edited() {
        if (!$this->can_be_edited()) {
            $reportlink = quiz_attempt_summary_link_to_reports($this->get_quiz(),
                $this->quizobj->get_cm(), $this->quizobj->get_context());
            throw new \moodle_exception('cannoteditafterattempts', 'quiz',
                new \moodle_url('/mod/aiquiz/edit.php', array('cmid' => $this->get_cmid())), $reportlink);
        }
    }
    public function add_section_heading($pagenumber, $heading = null) {
        global $DB;
        $section = new \stdClass();
        if ($heading !== null) {
            $section->heading = $heading;
        } else {
            $section->heading = get_string('newsectionheading', 'quiz');
        }
        $section->quizid = $this->get_quizid();
        $slotsonpage = $DB->get_records('aiquiz_slots', array('quizid' => $this->get_quizid(), 'page' => $pagenumber), 'slot DESC');
        $firstslot = end($slotsonpage);
        $section->firstslot = $firstslot->slot;
        $section->shufflequestions = 0;
        $sectionid = $DB->insert_record('aiquiz_sections', $section);

        // Log section break created event.
        $event = \mod_quiz\event\section_break_created::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $sectionid,
            'other' => [
                'quizid' => $this->get_quizid(),
                'firstslotnumber' => $firstslot->slot,
                'firstslotid' => $firstslot->id,
                'title' => $section->heading,
            ]
        ]);
        $event->trigger();

        return $sectionid;
    }

    public function get_version_choices_for_slot(int $slotnumber): array {
        $slot = $this->get_slot_by_number($slotnumber);

        // Get all the versions which exist.
        $versions = aiquiz_qbank_helper::get_version_options($slot->questionid);
        $latestversion = reset($versions);

        // Format the choices for display.
        $versionoptions = [];
        foreach ($versions as $version) {
            $version->selected = $version->version === $slot->requestedversion;

            if ($version->version === $latestversion->version) {
                $version->versionvalue = get_string('questionversionlatest', 'quiz', $version->version);
            } else {
                $version->versionvalue = get_string('questionversion', 'quiz', $version->version);
            }

            $versionoptions[] = $version;
        }

        // Make a choice for 'Always latest'.
        $alwaysuselatest = new \stdClass();
        $alwaysuselatest->versionid = 0;
        $alwaysuselatest->version = 0;
        $alwaysuselatest->versionvalue = get_string('alwayslatest', 'quiz');
        $alwaysuselatest->selected = $slot->requestedversion === null;
        array_unshift($versionoptions, $alwaysuselatest);

        return $versionoptions;
    }

    public function update_page_break($slotid, $type) {
        global $DB;

        $this->check_can_be_edited();

        $quizslots = $DB->get_records('aiquiz_slots', array('quizid' => $this->get_quizid()), 'slot');
        $repaginate = new \mod_aiquiz\aiquiz_repaginate($this->get_quizid(), $quizslots);
        $repaginate->repaginate_slots($quizslots[$slotid]->slot, $type);
        $slots = $this->refresh_page_numbers_and_update_db();

        if ($type == aiquiz_repaginate::LINK) {
            // Log page break created event.
            $event = \mod_quiz\event\page_break_deleted::create([
                'context' => $this->quizobj->get_context(),
                'objectid' => $slotid,
                'other' => [
                    'quizid' => $this->get_quizid(),
                    'slotnumber' => $quizslots[$slotid]->slot
                ]
            ]);
            $event->trigger();
        } else {
            // Log page deleted created event.
            $event = \mod_quiz\event\page_break_created::create([
                'context' => $this->quizobj->get_context(),
                'objectid' => $slotid,
                'other' => [
                    'quizid' => $this->get_quizid(),
                    'slotnumber' => $quizslots[$slotid]->slot
                ]
            ]);
            $event->trigger();
        }

        return $slots;
    }

    public function remove_slot($slotnumber) {
        global $DB;

        $this->check_can_be_edited();

        if ($this->is_only_slot_in_section($slotnumber) && $this->get_section_count() > 1) {
            throw new \coding_exception('You cannot remove the last slot in a section.');
        }

        $slot = $DB->get_record('aiquiz_slots', array('quizid' => $this->get_quizid(), 'slot' => $slotnumber));
        if (!$slot) {
            return;
        }
        $maxslot = $DB->get_field_sql('SELECT MAX(slot) FROM {aiquiz_slots} WHERE quizid = ?', array($this->get_quizid()));

        $trans = $DB->start_delegated_transaction();
        // Delete the reference if its a question.
        $questionreference = $DB->get_record('question_references',
            ['component' => 'mod_aiquiz', 'questionarea' => 'slot', 'itemid' => $slot->id]);
        if ($questionreference) {
            $DB->delete_records('question_references', ['id' => $questionreference->id]);
        }
        // Delete the set reference if its a random question.
        $questionsetreference = $DB->get_record('question_set_references',
            ['component' => 'mod_aiquiz', 'questionarea' => 'slot', 'itemid' => $slot->id]);
        if ($questionsetreference) {
            $DB->delete_records('question_set_references',
                ['id' => $questionsetreference->id, 'component' => 'mod_aiquiz', 'questionarea' => 'slot']);
        }
        $DB->delete_records('aiquiz_slots', array('id' => $slot->id));
        for ($i = $slot->slot + 1; $i <= $maxslot; $i++) {
            $DB->set_field('aiquiz_slots', 'slot', $i - 1,
                array('quizid' => $this->get_quizid(), 'slot' => $i));
            $this->slotsinorder[$i]->slot = $i - 1;
            $this->slotsinorder[$i - 1] = $this->slotsinorder[$i];
            unset($this->slotsinorder[$i]);
        }

        quiz_update_section_firstslots($this->get_quizid(), -1, $slotnumber);
        foreach ($this->sections as $key => $section) {
            if ($section->firstslot > $slotnumber) {
                $this->sections[$key]->firstslot--;
            }
        }
        $this->populate_slots_with_sections();
        $this->populate_question_numbers();
        $this->unset_question($slot->id);

        $this->refresh_page_numbers_and_update_db();

        $trans->allow_commit();

        // Log slot deleted event.
        $event = \mod_quiz\event\slot_deleted::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $slot->id,
            'other' => [
                'quizid' => $this->get_quizid(),
                'slotnumber' => $slotnumber,
            ]
        ]);
        $event->trigger();
    }
    public function update_slot_maxmark($slot, $maxmark) {
        global $DB;

        if (abs($maxmark - $slot->maxmark) < 1e-7) {
            // Grade has not changed. Nothing to do.
            return false;
        }

        $trans = $DB->start_delegated_transaction();
        $previousmaxmark = $slot->maxmark;
        $slot->maxmark = $maxmark;
        $DB->update_record('aiquiz_slots', $slot);
        \question_engine::set_max_mark_in_attempts(new \qubaids_for_quiz($slot->quizid),
            $slot->slot, $maxmark);
        $trans->allow_commit();

        // Log slot mark updated event.
        // We use $num + 0 as a trick to remove the useless 0 digits from decimals.
        $event = \mod_quiz\event\slot_mark_updated::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $slot->id,
            'other' => [
                'quizid' => $this->get_quizid(),
                'previousmaxmark' => $previousmaxmark + 0,
                'newmaxmark' => $maxmark + 0
            ]
        ]);
        $event->trigger();

        return true;
    }
    public function update_question_dependency($slotid, $requireprevious) {
        global $DB;
        $DB->set_field('aiquiz_slots', 'requireprevious', $requireprevious, array('id' => $slotid));

        // Log slot require previous event.
        $event = \mod_quiz\event\slot_requireprevious_updated::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $slotid,
            'other' => [
                'quizid' => $this->get_quizid(),
                'requireprevious' => $requireprevious ? 1 : 0
            ]
        ]);
        $event->trigger();
    }

    public function set_section_heading($id, $newheading) {
        global $DB;
        $section = $DB->get_record('aiquiz_sections', array('id' => $id), '*', MUST_EXIST);
        $section->heading = $newheading;
        $DB->update_record('aiquiz_sections', $section);

        // Log section title updated event.
        $firstslot = $DB->get_record('aiquiz_slots', array('quizid' => $this->get_quizid(), 'slot' => $section->firstslot));
        $event = \mod_quiz\event\section_title_updated::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $id,
            'other' => [
                'quizid' => $this->get_quizid(),
                'firstslotid' => $firstslot ? $firstslot->id : null,
                'firstslotnumber' => $firstslot ? $firstslot->slot : null,
                'newtitle' => $newheading
            ]
        ]);
        $event->trigger();
    }
    public function set_section_shuffle($id, $shuffle) {
        global $DB;
        $section = $DB->get_record('aiquiz_sections', array('id' => $id), '*', MUST_EXIST);
        $section->shufflequestions = $shuffle;
        $DB->update_record('aiquiz_sections', $section);

        // Log section shuffle updated event.
        $event = \mod_quiz\event\section_shuffle_updated::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $id,
            'other' => [
                'quizid' => $this->get_quizid(),
                'firstslotnumber' => $section->firstslot,
                'shuffle' => $shuffle
            ]
        ]);
        $event->trigger();
    }
    public function move_slot($idmove, $idmoveafter, $page) {
        global $DB;

        $this->check_can_be_edited();

        $movingslot = $this->get_slot_by_id($idmove);
        if (empty($movingslot)) {
            throw new \moodle_exception('Bad slot ID ' . $idmove);
        }
        $movingslotnumber = (int) $movingslot->slot;

        // Empty target slot means move slot to first.
        if (empty($idmoveafter)) {
            $moveafterslotnumber = 0;
        } else {
            $moveafterslotnumber = (int) $this->get_slot_by_id($idmoveafter)->slot;
        }

        // If the action came in as moving a slot to itself, normalise this to
        // moving the slot to after the previous slot.
        if ($moveafterslotnumber == $movingslotnumber) {
            $moveafterslotnumber = $moveafterslotnumber - 1;
        }

        $followingslotnumber = $moveafterslotnumber + 1;
        // Prevent checking against non-existance slot when already at the last slot.
        if ($followingslotnumber == $movingslotnumber && !$this->is_last_slot_in_quiz($followingslotnumber)) {
            $followingslotnumber += 1;
        }

        // Check the target page number is OK.
        if ($page == 0 || $page === '') {
            $page = 1;
        }
        if (($moveafterslotnumber > 0 && $page < $this->get_page_number_for_slot($moveafterslotnumber)) ||
            $page < 1) {
            throw new \coding_exception('The target page number is too small.');
        } else if (!$this->is_last_slot_in_quiz($moveafterslotnumber) &&
            $page > $this->get_page_number_for_slot($followingslotnumber)) {
            throw new \coding_exception('The target page number is too large.');
        }

        // Work out how things are being moved.
        $slotreorder = array();
        if ($moveafterslotnumber > $movingslotnumber) {
            // Moving down.
            $slotreorder[$movingslotnumber] = $moveafterslotnumber;
            for ($i = $movingslotnumber; $i < $moveafterslotnumber; $i++) {
                $slotreorder[$i + 1] = $i;
            }

            $headingmoveafter = $movingslotnumber;
            if ($this->is_last_slot_in_quiz($moveafterslotnumber) ||
                $page == $this->get_page_number_for_slot($moveafterslotnumber + 1)) {
                // We are moving to the start of a section, so that heading needs
                // to be included in the ones that move up.
                $headingmovebefore = $moveafterslotnumber + 1;
            } else {
                $headingmovebefore = $moveafterslotnumber;
            }
            $headingmovedirection = -1;

        } else if ($moveafterslotnumber < $movingslotnumber - 1) {
            // Moving up.
            $slotreorder[$movingslotnumber] = $moveafterslotnumber + 1;
            for ($i = $moveafterslotnumber + 1; $i < $movingslotnumber; $i++) {
                $slotreorder[$i] = $i + 1;
            }

            if ($page == $this->get_page_number_for_slot($moveafterslotnumber + 1)) {
                // Moving to the start of a section, don't move that section.
                $headingmoveafter = $moveafterslotnumber + 1;
            } else {
                // Moving tot the end of the previous section, so move the heading down too.
                $headingmoveafter = $moveafterslotnumber;
            }
            $headingmovebefore = $movingslotnumber + 1;
            $headingmovedirection = 1;
        } else {
            // Staying in the same place, but possibly changing page/section.
            if ($page > $movingslot->page) {
                $headingmoveafter = $movingslotnumber;
                $headingmovebefore = $movingslotnumber + 2;
                $headingmovedirection = -1;
            } else if ($page < $movingslot->page) {
                $headingmoveafter = $movingslotnumber - 1;
                $headingmovebefore = $movingslotnumber + 1;
                $headingmovedirection = 1;
            } else {
                return; // Nothing to do.
            }
        }

        if ($this->is_only_slot_in_section($movingslotnumber)) {
            throw new \coding_exception('You cannot remove the last slot in a section.');
        }

        $trans = $DB->start_delegated_transaction();

        // Slot has moved record new order.
        if ($slotreorder) {
            update_field_with_unique_index('aiquiz_slots', 'slot', $slotreorder,
                array('quizid' => $this->get_quizid()));
        }

        // Page has changed. Record it.
        if ($movingslot->page != $page) {
            $DB->set_field('aiquiz_slots', 'page', $page,
                array('id' => $movingslot->id));
        }

        // Update section fist slots.
        quiz_update_section_firstslots($this->get_quizid(), $headingmovedirection,
            $headingmoveafter, $headingmovebefore);

        // If any pages are now empty, remove them.
        $emptypages = $DB->get_fieldset_sql("
                SELECT DISTINCT page - 1
                  FROM {aiquiz_slots} slot
                 WHERE quizid = ?
                   AND page > 1
                   AND NOT EXISTS (SELECT 1 FROM {aiquiz_slots} WHERE quizid = ? AND page = slot.page - 1)
              ORDER BY page - 1 DESC
                ", array($this->get_quizid(), $this->get_quizid()));

        foreach ($emptypages as $emptypage) {
            $DB->execute("
                    UPDATE {aiquiz_slots}
                       SET page = page - 1
                     WHERE quizid = ?
                       AND page > ?
                    ", array($this->get_quizid(), $emptypage));
        }

        $trans->allow_commit();

        // Log slot moved event.
        $event = \mod_quiz\event\slot_moved::create([
            'context' => $this->quizobj->get_context(),
            'objectid' => $idmove,
            'other' => [
                'quizid' => $this->quizobj->get_quizid(),
                'previousslotnumber' => $movingslotnumber,
                'afterslotnumber' => $moveafterslotnumber,
                'page' => $page
            ]
        ]);
        $event->trigger();
    }
    public function refresh_page_numbers_and_update_db() {
        global $DB;
        $this->check_can_be_edited();

        $slots = $this->refresh_page_numbers();

        // Record new page order.
        foreach ($slots as $slot) {
            $DB->set_field('aiquiz_slots', 'page', $slot->page,
                array('id' => $slot->id));
        }

        return $slots;
    }
    public function refresh_page_numbers($slots = array()) {
        global $DB;
        // Get slots ordered by page then slot.
        if (!count($slots)) {
            $slots = $DB->get_records('aiquiz_slots', array('quizid' => $this->get_quizid()), 'slot, page');
        }

        // Loop slots. Start Page number at 1 and increment as required.
        $pagenumbers = array('new' => 0, 'old' => 0);

        foreach ($slots as $slot) {
            if ($slot->page !== $pagenumbers['old']) {
                $pagenumbers['old'] = $slot->page;
                ++$pagenumbers['new'];
            }

            if ($pagenumbers['new'] == $slot->page) {
                continue;
            }
            $slot->page = $pagenumbers['new'];
        }

        return $slots;
    }

}