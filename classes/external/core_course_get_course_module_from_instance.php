<?php

namespace local_aspiredu\external;

use external_api;
use external_description;
use external_function_parameters;
use external_value;
use moodle_exception;

global $CFG;
require_once("$CFG->dirroot/course/externallib.php");

class core_course_get_course_module_from_instance extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'module' => new external_value(PARAM_COMPONENT, 'The module name'),
                'instance' => new external_value(PARAM_INT, 'The module instance id')
            ]
        );
    }

    /**
     * Return information about a course module.
     *
     * @param int $module the module name
     * @param int $instance the module instance
     * @return array of warnings and the course module
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function execute($module, $instance) {

        $params = self::validate_parameters(self::execute_parameters(),
            [
                'module' => $module,
                'instance' => $instance,
            ]);

        $cm = get_coursemodule_from_instance($params['module'], $params['instance'], 0, true, MUST_EXIST);

        return \core_course_external::get_course_module($cm->id);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function execute_returns() {
        return \core_course_external::get_course_module_returns();
    }
}