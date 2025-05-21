<?php

namespace mod_aiquiz\local\structure;

use mod_quiz\local\structure\slot_random;

class aiquiz_slot_random extends slot_random
{
    public function insert($page)
    {
        global $DB;

        $slots = $DB->get_records('aiquiz_slots', array('quizid' => $this->record->quizid),
            'slot', 'id, slot, page');
        $quiz = $this->get_quiz();

        $trans = $DB->start_delegated_transaction();

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }

        if (is_int($page) && $page >= 1) {
            // Adding on a given page.
            $lastslotbefore = 0;
            foreach (array_reverse($slots) as $otherslot) {
                if ($otherslot->page > $page) {
                    $DB->set_field('aiquiz_slots', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
                } else {
                    $lastslotbefore = $otherslot->slot;
                    break;
                }
            }
            $this->record->slot = $lastslotbefore + 1;
            $this->record->page = min($page, $maxpage + 1);

            quiz_update_section_firstslots($this->record->quizid, 1, max($lastslotbefore, 1));
        } else {
            $lastslot = end($slots);
            if ($lastslot) {
                $this->record->slot = $lastslot->slot + 1;
            } else {
                $this->record->slot = 1;
            }
            if ($quiz->questionsperpage && $numonlastpage >= $quiz->questionsperpage) {
                $this->record->page = $maxpage + 1;
            } else {
                $this->record->page = $maxpage;
            }
        }

        $this->record->id = $DB->insert_record('aiquiz_slots', $this->record);

        $this->referencerecord->component = 'mod_aiquiz';
        $this->referencerecord->questionarea = 'slot';
        $this->referencerecord->itemid = $this->record->id;
        $this->referencerecord->filtercondition = $this->filtercondition;
        $DB->insert_record('question_set_references', $this->referencerecord);

        $trans->allow_commit();

        // Log slot created event.
        $cm = get_coursemodule_from_instance('aiquiz', $quiz->id);
        $event = \mod_quiz\event\slot_created::create([
            'context' => \context_module::instance($cm->id),
            'objectid' => $this->record->id,
            'other' => [
                'quizid' => $quiz->id,
                'slotnumber' => $this->record->slot,
                'page' => $this->record->page
            ]
        ]);
        $event->trigger();
    }

}