<?php

namespace mod_aiquiz\ai;


interface ai_api_interface
{
    public function generate_questions($document_content, $numberofquestions);

    public function generate_feedback($document_content, $responses);

}
