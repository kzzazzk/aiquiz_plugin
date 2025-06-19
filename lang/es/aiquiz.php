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
$string['pluginname'] = 'AI Quiz';
$string['modulename'] = 'AI Quiz';
$string['modulenameplural'] = 'AI Quizzes';
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
$string['questionhdr'] = 'Preguntas';
$string['numberofquestions'] = 'Número de preguntas';
$string['questioncorrectvalue'] = 'Valor de la pregunta respondida correctamente';
$string['questionincorrectvalue'] = 'Valor de la pregunta respondida incorrectamente';
$string['questiongradecorrect_help'] = 'Este es el número de puntos otorgados por cada pregunta respondida correctamente en el cuestionario. Determina cuánto contribuye una respuesta correcta a la puntuación total.';
$string['questiongradeincorrect_help'] = 'Este es el número de puntos que se descuentan por cada pregunta respondida incorrectamente. Generalmente se establece en 0, pero puedes asignar valores negativos para penalizaciones.';
$string['numberofquestions_help'] = 'Este es el número de preguntas que se generarán para el cuestionario. Por defecto está en 10 porque es generalmente un buen número para un cuestionario y se ha probado que no toma demasiado tiempo, pero puedes cambiarlo a cualquier número que quieras.';
$string['questionhdr_help'] = 'Aquí están todas las opciones para la configuración de la generación de preguntas. Estas configuraciones solo se aplicarán cuando se cree una nueva instancia, no actualizarán las instancias existentes.';
$string['quiznavigation'] = 'Navegación de AI Quiz';
$string['attemptquiznow'] = 'Realizar intento de AI Quiz ahora';
