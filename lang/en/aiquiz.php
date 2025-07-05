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
 * Plugin strings are defined here.
 *
 * @package     mod_aiquiz
 * @category    string
 * @copyright   2024 Zakaria Lasry zlsahraoui@alumnos.upm.es
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'AIQuiz';
$string['modulename'] = 'AIQuiz';
$string['modulenameplural'] = 'AI Quizzes';
$string['modulename_help'] = 'The AI Quiz plugin enables teachers to create personalized quizzes with AI-generated questions based on uploaded documents in a certain section. It includes all standard quiz features plus:

* A long-text input for specifying required knowledge
* AI-generated quizzes based on uploaded content
* AI-generated feedback on quiz performance

Quizzes may be used

* As personalized course exams
* As practice tests tailored to student submissions
* To provide immediate and specific feedback on performance
* For customized self-assessment';
$string['activitynamename'] = 'Name';
$string['aiquizfieldset'] = 'Settings';
$string['assignmenttiming'] = 'Availability';
$string['assignmentname'] = 'Task name';
$string['assigninstructions'] = 'Assignment submission instructions';
$string['assigninstructions_help'] = 'The actions you would like the student to complete for this assignment. This is only shown on the submission page where a student edits and submits their assignment.';
$string['activityname'] = 'Activity name';
$string['quiztiming'] = 'Timing';
$string['dynamic'] = 'Dynamic';
$string['static'] = 'Static';
$string['activitydescription'] = 'Required knowledge';
$string['availablefrom'] = 'Available from {$a->open}';
$string['availableuntil'] = 'Available until {$a->close}';
$string['availablefromuntilquiz'] = '
&ensp;&ensp;<b>Opened:</b> {$a->timeopen}<br>
&ensp;&ensp;<b>Close:</b> {$a->timeclose}';
$string['phase_switch_task'] = 'Switch phases in AI Quiz plugin';
$string['visible_after_allowsubmissionfromdate'] = 'Makes AI Quiz visible after the admission date has passed only if "Always show description" is disabled.';
$string['mingrade'] = 'Minimum grade';
$string['maxgrade'] = 'Maximum grade';
$string['submissionphasedescription'] = 'Submission phase description';
$string['quizphasedescription'] = 'Quiz phase description';
$string['description'] = 'Description';
$string['requiredknowledge_help'] = 'Describe in detail what knowledge is required for the users to properly meet with the standards of grading.';
$string['activityeditor_help'] = 'The actions you would like the student to complete for this assignment. This is only shown on the submission page where a student edits and submits their assignment.';
$string['aiassignconfigtitle'] = 'Assignment Configuration';
$string['aiquizconfigtitle'] = 'AI Quiz Configuration';
$string['coursemoduleconfigtitle'] = 'Other course module settings';
$string['basicsettings'] = 'Basic settings';
$string['pluginadministration'] = 'AI Quiz administration';
$string['apikey_desc'] = 'In this field, you can enter the OpenAI API key. This key is used to authenticate your requests and ensure that you have access to the AI features. Make sure to keep this key secure and do not share it with anyone else.';
$string['apikey'] = 'OpenAI API Key';
$string['apikeyinvalid'] = 'The provided API key is invalid. Please check your key and try again.';
$string['apikeyempty'] = 'The provided API key is empty. Please check your key and try again.';
$string['apikeyempty_course_view'] = 'The API key you set in the AI Quiz plugin settings is empty. Please check your key and try again.';
$string['apikeyinvalid_course_view'] = 'The API key you set in the AI Quiz plugin settings is invalid. Please check your key and try again.';
$string['questiongenmodel'] = 'OpenAI Question Generation Model:';
$string['questiongenmodeldescription'] = 'In this field, you can enter your preferred model for the question generation task';
$string['feedbackgenmodel'] = 'OpenAI Feedback Generation Model:';
$string['feedbackgenmodeldescription'] = 'In this field, you can enter your preferred model for the feedback generation  task';
$string['openaisettingsdescription'] = 'In this section, you can configure the OpenAI API key and the models used for question and feedback generation. The API key is required to access the OpenAI services, and the models determine how the AI generates questions and feedback based on the provided content.';
$string['openaisettings'] = 'OpenAI Settings';
$string['quiz_gen_assistant_id'] = 'Quiz Question Generator';
$string['feedback_gen_assistant_id'] = 'Quiz Feedback Generator';
$string['submitallandfinish'] = 'Once you submit your answers, you won’t be able to change them. Feedback will appear shortly after.';
$string['delete'] = 'Delete';
$string['cancel'] = 'Cancel';
$string['confirmclose'] = 'Once you submit your answers, you won’t be able to change them.
';
$string['submission_confirmation_unanswered'] = 'Questions without a response: {$a}';
$string['questionhdr'] = 'AI Question generation';
$string['numberofquestions'] = 'Number of Questions';
$string['questioncorrectvalue'] = 'Value of correctly answered question';
$string['questionincorrectvalue'] = 'Value of incorrectly answered question';
$string['questiongradecorrect_help'] = 'This is the number of points awarded for each correctly answered question in the quiz. It determines how much a correct response contributes to the total score.';
$string['questiongradeincorrect_help'] = 'This is the number of points deducted for each incorrectly answered question. Typically set to 0, but you can assign negative values for penalties.';
$string['numberofquestions_help'] = 'This is the number of questions that will be generated for the quiz. It is defaulted at 10 because it is generally a good number for a quiz and it is has been tested to not take too long, but you can change it to any number you want.';
$string['quiznavigation'] = 'AI Quiz navigation';
$string['attemptquiznow'] = 'Attemp aiquiz now';
$string['regeneratequestions'] = 'Regenerar preguntas';
$string['regeneratequestions_help'] = 'Si está marcado, las preguntas se regenerarán con la configuración actual al guardar las actualizaciones de la instancia de AI Quiz. Si no está marcado, las preguntas no se regenerarán y permanecerán tal como están.';
$string['questiongenerationprompt'] = 'Eres un generador de preguntas de opción múltiple basadas en documentos PDF.
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
            Respuesta correcta: [Letra]';
$string['feedbackgenerationprompt'] = 'Eres un generador de retroalimentación para cuestionarios. Recibirás un JSON con las respuestas incorrectas de un usuario. Si el JSON está vacío o no contiene respuestas incorrectas no devuelvas absolutamente nada.

    El JSON recibido contiene la siguiente estructura:
    - "questionsummary": Resumen de la pregunta.
    - "rightanswer": Respuesta correcta.
    - "responsesummary": Respuesta seleccionada por el usuario (si es null, significa que el usuario no respondió).
    
    **Importante:** No incluyas detalles sobre el número total de respuestas incorrectas, preguntas no respondidas ni su suma en la retroalimentación generada. Solo proporciona el mensaje general según la suma total.

    Proporciona retroalimentación mencionando qué temas necesita el usuario repasar esto debe ser de forma clara y concisa, con un rango de 30 a 50 palabras. No uses listas ni formatos especiales como asteriscos.';

$string['feedbackgenerationpromptdescription'] = 'This is the set of instructions the AI uses to generate feedback. Edit it carefully.';
$string['questiongenerationpromptdescription'] = 'This is the set of instructions the AI uses to generate questions. Edit it carefully.';
$string['feedbackgenerationpromptlabel'] = 'Instructions for generating feedback';
$string['questiongenerationpromptlabel'] = 'Instructions for generating questions';
$string['file_absence'] =  'You need to upload at least one file in the {$a->name} section before creating an AI Quiz instance in it.';

