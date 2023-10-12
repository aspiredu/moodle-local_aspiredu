<?php

namespace local_aspiredu\external;

use context_module;
use external_api;
use external_description;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use moodle_exception;
use stdClass;

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
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // If the user has permissions to manage the activity, return all the information.
        if (has_capability('moodle/course:manageactivities', $context)) {
            $info = $cm;
        } else {
            // Return information is safe to show to any user.
            $info = new stdClass();
            $info->id = $cm->id;
            $info->course = $cm->course;
            $info->module = $cm->module;
            $info->modname = $cm->modname;
            $info->instance = $cm->instance;
            $info->section = $cm->section;
            $info->sectionnum = $cm->sectionnum;
            $info->groupmode = $cm->groupmode;
            $info->groupingid = $cm->groupingid;
            $info->completion = $cm->completion;
        }
        // Format name.
        $info->name = format_string($cm->name, true, ['context' => $context]);

        $result = [];
        $result['cm'] = $info;
        $result['warnings'] = [];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'cm' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'The course module id'),
                        'course' => new external_value(PARAM_INT, 'The course id'),
                        'module' => new external_value(PARAM_INT, 'The module type id'),
                        'name' => new external_value(PARAM_RAW, 'The activity name'),
                        'modname' => new external_value(PARAM_COMPONENT, 'The module component name (forum, assign, etc..)'),
                        'instance' => new external_value(PARAM_INT, 'The activity instance id'),
                        'section' => new external_value(PARAM_INT, 'The module section id'),
                        'sectionnum' => new external_value(PARAM_INT, 'The module section number'),
                        'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                        'groupingid' => new external_value(PARAM_INT, 'Grouping id'),
                        'completion' => new external_value(PARAM_INT, 'If completion is enabled'),
                        'idnumber' => new external_value(PARAM_RAW, 'Module id number', VALUE_OPTIONAL),
                        'added' => new external_value(PARAM_INT, 'Time added', VALUE_OPTIONAL),
                        'score' => new external_value(PARAM_INT, 'Score', VALUE_OPTIONAL),
                        'indent' => new external_value(PARAM_INT, 'Indentation', VALUE_OPTIONAL),
                        'visible' => new external_value(PARAM_INT, 'If visible', VALUE_OPTIONAL),
                        'visibleold' => new external_value(PARAM_INT, 'Visible old', VALUE_OPTIONAL),
                        'completiongradeitemnumber' => new external_value(PARAM_INT, 'Completion grade item', VALUE_OPTIONAL),
                        'completionview' => new external_value(PARAM_INT, 'Completion view setting', VALUE_OPTIONAL),
                        'completionexpected' => new external_value(PARAM_INT, 'Completion time expected', VALUE_OPTIONAL),
                        'showdescription' => new external_value(PARAM_INT, 'If the description is showed', VALUE_OPTIONAL),
                        'availability' => new external_value(PARAM_RAW, 'Availability settings', VALUE_OPTIONAL),
                    ]
                ),
                'warnings' => new external_warnings()
            ]
        );
    }
}