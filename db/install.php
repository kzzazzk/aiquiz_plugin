<?php
global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');

function xmldb_mod_assignquiz_install() {
    $yourApiKey = $_ENV['OPENAI_API_KEY'];
    $client = OpenAI::client($yourApiKey);
    $assistant_id = quiz_generation_assistant_create($client);
    set_config('quiz_gen_assistant_id', $assistant_id, 'assignquiz');
    $assistant_id = feedback_generation_assistant_create($client);
    set_config('feedback_gen_assistant_id', $assistant_id, 'assignquiz');
}

function quiz_generation_assistant_create($client){
    $response = $client->assistants()->create([
        'instructions' => '
        Eres un generador de preguntas de opción múltiple en español basadas en documentos PDF.
        Genera preguntas únicas con 4 opciones de respuesta cada una, asegurando una única respuesta correcta por pregunta.

        Reglas estrictas:
        - No incluyas pistas en la redacción de las preguntas.
        - Cubre todo el documento con las preguntas, no solo fragmentos.
        - Usa español, salvo términos sin traducción en el texto original.
        - No preguntes sobre ubicaciones (página/sección) dentro del documento, ni tampoco las uses como fundamento de pregunta.
        - Evita preguntas de definición directa; prioriza preguntas conceptuales y aplicadas.
        - No formules preguntas cuya respuesta se mencione directamente en el enunciado.
        - No devuelvas saltos de línea en el texto.
        - No utilices frases como "según el texto", "de acuerdo con el documento" o similares.

        Formato de salida:
            [Número]. Pregunta: [Texto de la pregunta]
            Opciones:
            A. [Opción 1]
            B. [Opción 2]
            C. [Opción 3]
            D. [Opción 4]
            Respuesta correcta: [Letra]',
        'name' => 'Moodle PDF to Quiz Generator',
        'model' => "gpt-4o-mini",
    ]);
    $assistant_id = $response['id'];
    return $assistant_id;
}

function feedback_generation_assistant_create($client){
    $response = $client->assistants()->create([
        'instructions' => '
        Eres un generador de retroalimentación para cuestionarios. El JSON que recibirás contiene una lista de preguntas respondidas erróneamente. Cada entrada incluye:
        - questionsummary: resumen de la pregunta.
        - rightanswer: respuesta correcta.
        - responsesummary: respuesta del usuario.

        También recibirás un texto extraído directamente del temario del curso.

        Si el JSON está vacío o no recibes ningún JSON, no generes retroalimentación.

        Tu tarea:
        Evalúa cada pregunta con respecto al temario. Genera una breve retroalimentación (<75 palabras) indicando los temas que el usuario debería repasar. 
        Menciona los temas concretos y evita listar preguntas.

        Nivel de motivación:
        - 0-2 fallos: "¡Buen trabajo!"
        - 3-5 fallos: "¡Buen intento!"
        - 6-10 fallos: "¡Ánimo, en el siguiente intento lo harás mejor!"

        Ejemplo: 
        "¡Buen trabajo! Solo necesitas reforzar el tema de codificación binaria ponderada y repasar la representación hexadecimal para consolidar tus conocimientos."',
        'name' => 'Generador de Retroalimentación de Cuestionarios',
        'model' => "gpt-4o-mini",
    ]);
    $assistant_id = $response['id'];
    return $assistant_id;
}
