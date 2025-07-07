<?php

namespace mod_aiquiz\ai;


interface AIProviderInterface
{
    public function generate_questions($document_content, $numberofquestions);

    public function generate_feedback($document_content, $responses);

}
