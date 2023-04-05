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
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use local_aspiredu\local\helper;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/externallib.php');
require_once($CFG->dirroot.'/local/aspiredu/externallib.php');


/**
 * Get users by role external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_grades_pag extends external_api {

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
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/course/externallib.php');
        $grades = [];
        $sort = strtoupper($sort);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sort, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sort . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }
        $enrolledusers = get_enrolled_users(\context_course::instance($courseid),'', 0, 'u.id');
        $perpage = get_config('local_aspiredu', 'maxrecordsperpage');
        $grades = \local_aspiredu_external::core_grades_get_grades($courseid,null,null,array_keys($enrolledusers));

        switch ($sort) {
            case 'ASC':
                sort($grades['items']);
                break;
            case 'DESC':
                rsort($grades['items']);
        }
        $total_records = count($grades['items']);
        $total_pages   = ceil($total_records / $perpage);

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perpage;
        $grades['items'] = array_slice($grades['items'], $offset, $perpage);

        return $grades;
    }


    /**
     * Describes the get_forum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function execute_returns() {
        return new external_single_structure(
            array(
                'items'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'modname' => new external_value(PARAM_RAW, 'The module name', VALUE_OPTIONAL),
                            'instance' => new external_value(PARAM_INT, 'module instance id', VALUE_OPTIONAL),
                            'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                            'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                            'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                            'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(
                                            PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                                        'hidden' => new external_value(
                                            PARAM_INT, '0 means not hidden, 1 hidden, > 1 is a date to hide until'),
                                        'overridden' => new external_value(
                                            PARAM_INT, '0 means not overridden, > 1 means overridden'),
                                        'feedback' => new external_value(
                                            PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_value(
                                            PARAM_INT, 'The format of the feedback'),
                                        'usermodified' => new external_value(
                                            PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'datesubmitted' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the student submitted the activity'),
                                        'dategraded' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the assignment was grades'),
                                        'str_grade' => new external_value(
                                            PARAM_RAW, 'A string representation of the grade'),
                                        'str_long_grade' => new external_value(
                                            PARAM_RAW, 'A nicely formatted string representation of the grade'),
                                        'str_feedback' => new external_value(
                                            PARAM_RAW, 'A formatted string representation of the feedback from the grader'),
                                    )
                                )
                            ),
                        )
                    )
                ),
            )
        );
    }
}
