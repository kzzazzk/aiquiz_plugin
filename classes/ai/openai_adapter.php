<?php

namespace mod_aiquiz\ai;

use OpenAI;
require_once($CFG->dirroot . '/mod/aiquiz/classes/ai/ai_api_interface.php');
class openai_adapter implements ai_api_interface
{
    private $client;

    public function __construct($apikey)
    {
        $this->client = OpenAI::client($apikey);
    }

    public function create_question_assistant()
    {
        global $DB;
        $response = $this->client->assistants()->create([
            'instructions' => $DB->get_field('config', 'value', ['name' => 'questiongenerationprompt']),
            'name' => 'Quiz Question Generator',
            'model' => get_config('mod_aiquiz', 'questiongenmodel'),
        ]);
        return $response['id'];
    }


    //    public function generate_questions($filepath, $numberofquestions)
    public function generate_questions($pdf_text, $numberofquestions)
    {
        $assistant_id = get_config('mod_aiquiz', 'quiz_gen_assistant_id');
        $thread_create_response = $this->client->threads()->create([]);
        $this->client->threads()->messages()->create($thread_create_response->id, [
            'role' => 'assistant',
            'content' => "Genera $numberofquestions preguntas para un cuestionario basándote en el siguiente contenido:\n" . $pdf_text,
        ]);
        $response = $this->client->threads()->runs()->create(
            threadId: $thread_create_response->id,
            parameters: [
                'assistant_id' => $assistant_id,
            ],
        );

        do {
            sleep(1); // wait for a second (adjust as needed)
            $runStatus = $response = $this->client->threads()->runs()->retrieve(
                threadId: $thread_create_response->id,
                runId: $response->id,
            );
        } while ($runStatus->status !== 'completed');

        if ($runStatus->status === 'completed') {
            $response = $this->client->threads()->messages()->list($thread_create_response->id);
            return $response->data[0]->content[0]->text->value;
        } else {
            throw new Exception('Run did not complete in the expected time.');
        }
    }

    public function create_feedback_assistant()
    {
        $response = $this->client->assistants()->create([
            'instructions' => 'Eres un generador de retroalimentación para cuestionarios. Recibirás un JSON con las respuestas incorrectas de un usuario. Si el JSON está vacío o no contiene respuestas incorrectas no devuelvas absolutamente nada.
    
                El JSON recibido contiene la siguiente estructura:
                - "questionsummary": Resumen de la pregunta.
                - "rightanswer": Respuesta correcta.
                - "responsesummary": Respuesta seleccionada por el usuario (si es null, significa que el usuario no respondió).
                
                **Importante:** No incluyas detalles sobre el número total de respuestas incorrectas, preguntas no respondidas ni su suma en la retroalimentación generada. Solo proporciona el mensaje general según la suma total.
            
                Proporciona retroalimentación mencionando qué temas necesita el usuario repasar esto debe ser de forma clara y concisa, con un rango de 30 a 50 palabras. No uses listas ni formatos especiales como asteriscos.',
            'name' => 'Quiz Feedback Generator',
            'model' => get_config('mod_aiquiz', 'feedbackgenmodel'),
        ]);
        return $response['id'];
    }

    function generate_feedback($responses, $pdftext)
    {
        $assistant_id = get_config('mod_aiquiz', 'feedback_gen_assistant_id');
        $thread_create_response = $this->client->threads()->create([]);
        $this->client->threads()->messages()->create($thread_create_response->id, [
            'role' => 'assistant',
            'content' => 'Genera una breve retroalimentación para un test basándote en el contenido del temario y el JSON proporcionado:
        Este es el JSON:
        ' . $json_text . '\n' .

                'Este es el contenido del temario:'
                . $pdftext,
        ]);
        $response = $this->client->threads()->runs()->create(
            threadId: $thread_create_response->id,
            parameters: [
                'assistant_id' => $assistant_id,
            ],
        );

        $maxAttempts = 100; // or however many times you want to check
        $attempt = 0;

        do {
            sleep(1); // wait for a second (adjust as needed)
            $runStatus = $response = $this->client->threads()->runs()->retrieve(
                threadId: $thread_create_response->id,
                runId: $response->id,
            );
            $attempt++;
        } while ($runStatus->status !== 'completed' && $attempt < $maxAttempts);

        if ($runStatus->status === 'completed') {
            $response = $this->client->threads()->messages()->list($thread_create_response->id);
            return $response->data[0]->content[0]->text->value;
        } else {
            throw new Exception('Run did not complete in the expected time.');
        }
    }
}