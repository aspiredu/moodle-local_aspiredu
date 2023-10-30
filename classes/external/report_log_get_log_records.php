<?php

namespace local_aspiredu\external;

use context_course;
use context_system;
use dml_exception;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use local_aspiredu\local\report_log_renderable;
use moodle_exception;
use required_capability_exception;
use restricted_context_exception;

global $CFG;
require_once("$CFG->dirroot/course/externallib.php");
require_once("$CFG->dirroot/report/log/classes/renderable.php");

class report_log_get_log_records extends external_api {

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'logs' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'eventname' => new external_value(PARAM_RAW, 'eventname'),
                            'name' => new external_value(PARAM_RAW, 'get_name()'),
                            'description' => new external_value(PARAM_RAW, 'get_description()'),
                            'component' => new external_value(PARAM_COMPONENT, 'component'),
                            'action' => new external_value(PARAM_RAW, 'action'),
                            'target' => new external_value(PARAM_RAW, 'target'),
                            'objecttable' => new external_value(PARAM_RAW, 'objecttable'),
                            'objectid' => new external_value(PARAM_RAW, 'objectid'),
                            'crud' => new external_value(PARAM_ALPHA, 'crud'),
                            'edulevel' => new external_value(PARAM_INT, 'edulevel'),
                            'contextid' => new external_value(PARAM_INT, 'contextid'),
                            'contextlevel' => new external_value(PARAM_INT, 'contextlevel'),
                            'contextinstanceid' => new external_value(PARAM_INT, 'contextinstanceid'),
                            'userid' => new external_value(PARAM_INT, 'userid'),
                            'courseid' => new external_value(PARAM_INT, 'courseid'),
                            'relateduserid' => new external_value(PARAM_INT, 'relateduserid'),
                            'anonymous' => new external_value(PARAM_INT, 'anonymous'),
                            'other' => new external_value(PARAM_RAW, 'other'),
                            'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                        ]
                    )
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }

    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id (0 for site logs)', VALUE_DEFAULT, 0),
                'userid' => new external_value(PARAM_INT, 'user id, 0 for alls', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id (for filtering by groups)', VALUE_DEFAULT, 0),
                'date' => new external_value(PARAM_INT, 'timestamp for date, 0 all days', VALUE_DEFAULT, 0),
                'modid' => new external_value(PARAM_ALPHANUMEXT, 'mod id or "site_errors"', VALUE_DEFAULT, 0),
                'modaction' => new external_value(PARAM_NOTAGS, 'action (view, read)', VALUE_DEFAULT, ''),
                'logreader' => new external_value(PARAM_COMPONENT, 'Reader to be used for displaying logs', VALUE_DEFAULT, ''),
                'edulevel' => new external_value(PARAM_INT, 'educational level (1 teaching, 2 participating)', VALUE_DEFAULT, -1),
                'page' => new external_value(PARAM_INT, 'page to show', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'entries per page', VALUE_DEFAULT, 100),
                'order' => new external_value(PARAM_ALPHA, 'time order (ASC or DESC)', VALUE_DEFAULT, 'DESC'),
            ]
        );
    }


    /**
     * Return log entries
     *
     * @param int $courseid
     * @param int $userid
     * @param int $groupid
     * @param int $date
     * @param int $modid
     * @param string $modaction
     * @param string $logreader
     * @param int $edulevel
     * @param int $page
     * @param int $perpage
     * @param string $order
     * @return array of course objects (id, name ...) and warnings
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function execute($courseid = 0, $userid = 0, $groupid = 0, $date = 0, $modid = 0,
                                   $modaction = '', $logreader = '', $edulevel = -1, $page = 0,
                                   $perpage = 100, $order = 'DESC') {
        global $CFG;
        require_once($CFG->dirroot . '/lib/tablelib.php');

        $warnings = [];
        $logsrecords = [];

        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'groupid' => $groupid,
            'date' => $date,
            'modid' => $modid,
            'modaction' => $modaction,
            'logreader' => $logreader,
            'edulevel' => $edulevel,
            'page' => $page,
            'perpage' => $perpage,
            'order' => $order,
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);

        if ($params['logreader'] == 'logstore_legacy') {
            $params['edulevel'] = -1;
        }

        if ($params['order'] != 'ASC' && $params['order'] != 'DESC') {
            throw new invalid_parameter_exception('Invalid order parameter');
        }

        if (empty($params['courseid'])) {
            $site = get_site();
            $params['courseid'] = $site->id;
            $context = context_system::instance();
        } else {
            $context = context_course::instance($params['courseid']);
        }

        $course = get_course($params['courseid']);

        self::validate_context($context);
        require_capability('report/log:view', $context);

        $reportlog = new report_log_renderable($params['logreader'], $course, $params['userid'], $params['modid'],
            $params['modaction'],
            $params['groupid'], $params['edulevel'], true, true,
            false, true, new \moodle_url(''), $params['date'], '',
            $params['page'], $params['perpage'], 'timecreated ' . $params['order']);
        $readers = $reportlog->get_readers();

        if (empty($readers)) {
            throw new moodle_exception('nologreaderenabled', 'report_log');
        }
        $reportlog->setup_table();
        $reportlog->tablelog->setup();
        $reportlog->tablelog->query_db($params['perpage'], false);

        foreach ($reportlog->tablelog->rawdata as $row) {
            $logsrecords[] = [
                'eventname' => $row->eventname,
                'name' => $row->get_name(),
                'description' => $row->get_description(),
                'component' => $row->component,
                'action' => $row->action,
                'target' => $row->target,
                'objecttable' => $row->objecttable,
                'objectid' => $row->objectid,
                'crud' => $row->crud,
                'edulevel' => $row->edulevel,
                'contextid' => $row->contextid,
                'contextlevel' => $row->contextlevel,
                'contextinstanceid' => $row->contextinstanceid,
                'userid' => $row->userid,
                'courseid' => $row->courseid,
                'relateduserid' => $row->relateduserid,
                'anonymous' => $row->anonymous,
                'other' => json_encode($row->other),
                'timecreated' => $row->timecreated,
            ];
        }

        return [
            'logs' => $logsrecords,
            'warnings' => $warnings
        ];
    }
}