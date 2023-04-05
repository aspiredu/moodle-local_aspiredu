<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aspiredu\external;

use external_api;
use external_files;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use local_aspiredu\local\helper;
use mod_assign_external;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/externallib.php');
require_once($CFG->dirroot.'/local/aspiredu/externallib.php');
require_once($CFG->dirroot.'/mod/assign/externallib.php');


/**
 * Get users by role external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_assign_get_assignments_pag extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'courseid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'start_date' => new external_value(PARAM_INT, 'since date', VALUE_DEFAULT, 0),
                'end_date' => new external_value(PARAM_INT, 'until date', VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_TEXT, 'sort result', VALUE_DEFAULT, 'ASC'),
            ]
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $courseid
     * @param int|null $page
     * @param int|null $start_date
     * @param int|null $end_date
     * @param string $sort
     * @return array the forum post details
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function execute(
        int $courseid,
        ?int $page = -1,
        ?int $start_date = 0,
        ?int $end_date = 0,
        string $sort = 'ASC',
    ): array
    {
        global $CFG;
        require_once($CFG->dirroot . '/course/externallib.php');
        $grades = [];
        $sort = strtoupper($sort);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sort, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sort . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }
        $perpage = get_config('local_aspiredu', 'maxrecordsperpage');
        $assignments = mod_assign_external::get_assignments([$courseid,6],[],true);
        $assignment=[];
        foreach ($assignments['courses'] as $course) {

            foreach ($course['assignments'] as $courseassignments) {
                if ($start_date !== 0 || $end_date !== 0) {
                    if ($courseassignments->timemodified < $start_date || ($end_date !== 0 && $courseassignments->timemodified > $end_date)) {
                        continue;
                    }
                }
                $assignment[] = $courseassignments;
            }
        }
        switch ($sort) {
            case 'ASC':
                sort($assignment);
                break;
            case 'DESC':
                rsort($assignment);
        }
        $total_records = count($assignment);
        $total_pages   = ceil($total_records / $perpage);

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perpage;
        $assignment = array_slice($assignment, $offset, $perpage);

        return $assignment;
    }


    /**
     * Describes the return value for get_assignments
     *
     * @return external_multiple_structure
     * @since Moodle 2.4
     */
    public static function execute_returns() {
        return new external_multiple_structure(self::get_assignments_assignment_structure(), 'assignment info');

    }

    /**
     * Creates an assignment external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function get_assignments_assignment_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'assignment id'),
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'course' => new external_value(PARAM_INT, 'course id'),
                'name' => new external_value(PARAM_RAW, 'assignment name'),
                'duedate' => new external_value(PARAM_INT, 'assignment due date'),
                'grade' => new external_value(PARAM_INT, 'grade type'),
                'timemodified' => new external_value(PARAM_INT, 'last time assignment was modified'),
            ), 'assignment information object');
    }
}
