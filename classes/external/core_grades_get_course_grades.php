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

/**
 * AspirEDU Integration
 *
 * @package    local_aspiredu
 * @copyright  2024 AspirEDU
 * @author     AspirEDU
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aspiredu\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/lib/externallib.php");
require_once("$CFG->dirroot/course/externallib.php");
require_once("$CFG->libdir/gradelib.php");
require_once($CFG->dirroot . '/grade/querylib.php');

use context_course;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use stdClass;

/**
 * Web serivce class.
 */
class core_grades_get_course_grades extends external_api {
    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                'name' => new external_value(PARAM_RAW, 'The module name'),
                'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                'locked' => new external_value(PARAM_BOOL, '0 means not locked, > 1 is a date to lock until'),
                'hidden' => new external_value(PARAM_BOOL, '0 means not hidden, > 1 is a date to hide until'),
                'grades' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'userid' => new external_value(
                                PARAM_INT, 'Student ID'),
                            'grade' => new external_value(
                                PARAM_FLOAT, 'Student grade'),
                            'grademax' => new external_value(
                                PARAM_FLOAT, 'Max student grade'),
                            'locked' => new external_value(
                                PARAM_BOOL, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(
                                PARAM_BOOL, '0 means not hidden, 1 hidden, > 1 is a date to hide until'),
                            'overridden' => new external_value(
                                PARAM_BOOL, '0 means not overridden, > 1 means overridden'),
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
                        ]
                    ), 'user grades', VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'),
                    'An array of user IDs, leave empty to just retrieve grade item information', VALUE_DEFAULT, []
                ),
            ]
        );
    }

    /**
     * Retrieve the final course grade for the given users.
     *
     * @param int $courseid Course id
     * @param array $userids Array of user ids
     * @return stdClass             Array of grades
     */
    public static function execute($courseid, $userids = []) {
        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'userids' => $userids]);

        $courseid = $params['courseid'];
        $userids = $params['userids'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);

        require_capability('moodle/grade:viewall', $coursecontext);

        $retval = grade_get_course_grades($courseid, $userids);
        foreach ($retval->grades as $userid => $grade) {
            $grade->userid = $userid;
            $grade->grademax = $retval->grademax;
        }
        return $retval;
    }
}
