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
$string['modulenameplural'] = 'AI Quiz';
$string['modulename_help'] = 'La actividad AI Quiz permite al profesor crear cuestionarios personalizados con preguntas generadas automáticamente por IA a partir de documentos PDF que se suben a una sección del curso.

El profesor define el número de preguntas y la puntuación para respuestas correctas e incorrectas. Las preguntas generadas se guardan en la categoría asociada a la instancia del cuestionario, identificadas según los documentos utilizados y la sección.

Cuando un estudiante realiza un intento, recibe retroalimentación personalizada generada por IA basada en sus respuestas y el contenido proporcionado.

AI Quiz incluye todas las funcionalidades habituales de un cuestionario, junto con:

* Generación automática de preguntas mediante IA
* Retroalimentación inmediata generada por IA y adaptada al desempeño del estudiante

Los AI Quiz son ideales para:

* Exámenes adaptados al curso
* Pruebas de práctica automáticas
* Autoevaluación eficaz';
$string['activitynamename'] = 'Nombre';
$string['aiquizfieldset'] = 'Configuraciones';
$string['assignmenttiming'] = 'Disponibilidad';
$string['assignmentname'] = 'Nombre de la tarea';
$string['assigninstructions'] = 'Instrucciones para la entrega de la tarea';
$string['assigninstructions_help'] = 'Las acciones que quieres que el estudiante complete para esta tarea. Esto solo se muestra en la página de entrega donde el estudiante edita y envía su trabajo.';
$string['activityname'] = 'Nombre de la actividad';
$string['quiztiming'] = 'Temporización';
$string['dynamic'] = 'Dinámico';
$string['static'] = 'Estático';
$string['activitydescription'] = 'Conocimiento requerido';
$string['availablefrom'] = 'Disponible desde {$a->open}';
$string['availableuntil'] = 'Disponible hasta {$a->close}';
$string['availablefromuntilquiz'] ='
&ensp;&ensp;<b>Abierto:</b> {$a->timeopen}<br>
&ensp;&ensp;<b>Cierra:</b> {$a->timeclose}';
$string['phase_switch_task'] = 'Cambiar fases en el plugin AI Quiz';
$string['visible_after_allowsubmissionfromdate'] = 'Hace visible AI Quiz solo después de la fecha de admisión si "Mostrar siempre descripción" está deshabilitado.';
$string['mingrade'] = 'Calificación mínima';
$string['maxgrade'] = 'Calificación máxima';
$string['submissionphasedescription'] = 'Descripción de la fase de entrega';
$string['quizphasedescription'] = 'Descripción de la fase del cuestionario';
$string['description'] = 'Descripción';
$string['requiredknowledge_help'] = 'Describe en detalle el conocimiento requerido para que los usuarios cumplan adecuadamente con los estándares de calificación.';
$string['activityeditor_help'] = 'Las acciones que quieres que el estudiante complete para esta tarea. Esto solo se muestra en la página de entrega donde el estudiante edita y envía su trabajo.';
$string['aiassignconfigtitle'] = 'Configuración de la tarea';
$string['aiquizconfigtitle'] = 'Configuración de AI Quiz';
$string['coursemoduleconfigtitle'] = 'Otras configuraciones del módulo del curso';
$string['basicsettings'] = 'Configuraciones básicas';
$string['pluginadministration'] = 'Administración de AI Quiz';
$string['apikey_desc'] = 'En este campo puedes ingresar la clave API de OpenAI. Esta clave se usa para autenticar tus solicitudes y asegurar que tienes acceso a las funciones de IA. Asegúrate de mantener esta clave segura y no compartirla con nadie más.';
$string['apikey'] = 'Clave API de OpenAI';
$string['apikeyinvalid'] = 'La clave API proporcionada no es válida. Por favor verifica tu clave e inténtalo de nuevo.';
$string['apikeyempty'] = 'La clave API proporcionada está vacía. Por favor verifica tu clave e inténtalo de nuevo.';
$string['apikeyempty_course_view'] = 'La clave API que configuraste en los ajustes del plugin AI Quiz está vacía. Por favor verifica tu clave e inténtalo de nuevo.';
$string['apikeyinvalid_course_view'] = 'La clave API que configuraste en los ajustes del plugin AI Quiz no es válida. Por favor verifica tu clave e inténtalo de nuevo.';
$string['questiongenmodel'] = 'Modelo de OpenAI para generación de preguntas:';
$string['questiongenmodeldescription'] = 'En este campo puedes ingresar tu modelo preferido para la tarea de generación de preguntas';
$string['feedbackgenmodel'] = 'Modelo de OpenAI para generación de retroalimentación:';
$string['feedbackgenmodeldescription'] = 'En este campo puedes ingresar tu modelo preferido para la tarea de generación de retroalimentación';
$string['openaisettingsdescription'] = 'En esta sección puedes configurar la clave API de OpenAI y los modelos usados para la generación de preguntas y retroalimentación. La clave API es necesaria para acceder a los servicios de OpenAI, y los modelos determinan cómo la IA genera preguntas y retroalimentación basadas en el contenido proporcionado.';
$string['openaisettings'] = 'Configuración de OpenAI';
$string['quiz_gen_assistant_id'] = 'Generador de preguntas del cuestionario';
$string['feedback_gen_assistant_id'] = 'Generador de retroalimentación del cuestionario';
$string['submitallandfinish'] = 'Una vez que envíes tus respuestas, no podrás cambiarlas. La retroalimentación aparecerá poco después.';
$string['delete'] = 'Eliminar';
$string['cancel'] = 'Cancelar';
$string['confirmclose'] = 'Una vez que envíes tus respuestas, no podrás cambiarlas.
';
$string['submission_confirmation_unanswered'] = 'Preguntas sin respuesta: {$a}';
$string['questionhdr'] = 'Generación de preguntas con IA';
$string['numberofquestions'] = 'Número de preguntas';
$string['questioncorrectvalue'] = 'Valor de la pregunta respondida correctamente';
$string['questionincorrectvalue'] = 'Valor de la pregunta respondida incorrectamente';
$string['questiongradecorrect_help'] = 'Este es el número de puntos otorgados por cada pregunta respondida correctamente en el cuestionario. Determina cuánto contribuye una respuesta correcta a la puntuación total.';
$string['questiongradeincorrect_help'] = 'Este es el número de puntos que se descuentan por cada pregunta respondida incorrectamente. Generalmente se establece en 0, pero puedes asignar valores negativos para penalizaciones.';
$string['numberofquestions_help'] = 'Este es el número de preguntas que se generarán para el cuestionario. Por defecto está en 10 porque es generalmente un buen número para un cuestionario y se ha probado que no toma demasiado tiempo, pero puedes cambiarlo a cualquier número que quieras.';
$string['quiznavigation'] = 'Navegación de AI Quiz';
$string['attemptquiznow'] = 'Realizar intento de AI Quiz ahora';
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
$string['feedbackgenerationprompt'] = 'Eres un generador de retroalimentación para cuestionarios. Recibirás un JSON con las respuestas incorrectas de un usuario. 

    El JSON recibido contiene la siguiente estructura:
    - "questionsummary": Resumen de la pregunta.
    - "rightanswer": Respuesta correcta.
    - "responsesummary": Respuesta seleccionada por el usuario (si es null, significa que el usuario no respondió).
    
    **Importante:** 
    - No incluyas detalles sobre el número total de respuestas incorrectas, preguntas no respondidas ni su suma en la retroalimentación generada. Solo proporciona el mensaje general según la suma total.
    - Escribe el mensaje impersonalmente, como si fuera un asistente que proporciona retroalimentación al usuario.
    - No generalices sobre qué temas debe repasar el usuario para mejorar su calificación.
    - Comienza la retroalimentación con "Se recomienda"


    Proporciona retroalimentación mencionando qué aspectos específicos del contenido del documento necesita el usuario repasar teniendo en cuenta las preguntas en las que ha fallado, esto debe ser de forma clara y concisa, con un rango de 30 a 50 palabras. No uses listas ni formatos especiales como asteriscos.';
$string['feedbackgenerationpromptdescription'] = 'Este es el conjunto de instrucciones que tiene la IA para generar retroalimentación. Edítalo con cuidado.';
$string['questiongenerationpromptdescription'] = 'Este es el conjunto de instrucciones que tiene la IA para generar preguntas. Edítalo con cuidado.';
$string['feedbackgenerationpromptlabel'] = 'Instrucciones para generar retroalimentación';
$string['questiongenerationpromptlabel'] = 'Instrucciones para generar preguntas';
$string['file_absence'] = 'Necesitas subir al menos un archivo en la sección {$a->name} antes de crear una instancia de AI Quiz en ella.';
