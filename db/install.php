<?php
global $CFG;
require_once($CFG->dirroot.'/vendor/autoload.php');

function xmldb_assignquiz_install() {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
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
        'instructions' => 'Eres un generador de retroalimentación para cuestionarios. Recibirás un JSON con las respuestas incorrectas de un usuario. Si el JSON está vacío o no contiene respuestas incorrectas y además el parámetro totalsum es equivalente a 0, responde únicamente con "¡Excelente! Sin errores." sin agregar más detalles.

    El JSON recibido contiene la siguiente estructura:
    - "questionsummary": Resumen de la pregunta.
    - "rightanswer": Respuesta correcta.
    - "responsesummary": Respuesta seleccionada por el usuario (si es null, significa que el usuario no respondió).
    - "totalsum": La suma total de respuestas incorrectas y preguntas no respondidas.
    
    Además, se te proporcionará un archivo que contiene el contenido académico relacionado. El objetivo es contar el número de respuestas incorrectas y el número de preguntas no respondidas, luego sumar ambos valores.

    Según la suma total de respuestas incorrectas y preguntas no respondidas, debes generar una retroalimentación usando las siguientes frases:

    - Si la suma total es 0: "¡Excelente! Sin errores."
    - Si la suma total es entre 1 y 2: "¡Buen trabajo! Muy bien."
    - Si la suma total es entre 3 y 4: "Buen intento, sigue así."
    - Si la suma total es entre 5 y 6: "Buen intento, mejora posible."
    - Si la suma total es entre 7 y 8: "Se puede mejorar aún."
    - Si la suma total es entre 9 y 10: "Revisión completa sugerida."

    **Importante:** No incluyas detalles sobre el número total de respuestas incorrectas, preguntas no respondidas ni su suma en la retroalimentación generada. Solo proporciona el mensaje general según la suma total.

    Después de la frase general, si existen respuestas incorrectas, proporciona retroalimentación breve sobre los temas que el usuario necesita repasar. Esta retroalimentación debe ser clara y concisa, con un límite de 65 palabras. No uses listas ni formato especial como asteriscos.',
        'name' => 'Generador de Retroalimentación de Cuestionarios',
        'model' => "gpt-4o-mini",
    ]);
    $assistant_id = $response['id'];
    return $assistant_id;
}
