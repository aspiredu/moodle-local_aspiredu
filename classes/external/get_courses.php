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
use local_aspiredu\local\helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/externallib.php');

/**
 * Get paginated courses external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'options' => new external_single_structure(
                    ['ids' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'Course id')
                        , 'List of course id. If empty return all courses
                                except front page course.',
                        VALUE_OPTIONAL)
                    ], 'options - operator OR is used', VALUE_DEFAULT, []),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 100),
                'sort' => new external_value(PARAM_TEXT, 'sort items by', VALUE_DEFAULT, 'id'),
            ]
        );
    }

    /**
     * Returns a list of paginated courses given course ids.
     *
     * @param array $options
     * @param int|null $page current page
     * @param int|null $perpage items per page
     * @return array of warnings and users
     */
    public static function execute(array $options = [], ?int $page = 0, ?int $perpage = 100, ?string $sort = 'id'): array {

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'options' => $options,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
        ]);

        $coursesinfo = helper::get_courses($params['options'], $params['page'], $params['perpage'], $params['sort']);

        return $coursesinfo;
    }

    /**
     * Describes the get_users_by_roles return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure(self::get_course_structure(), 'course')
        );
    }

    /**
     * Returns the course structure as core_course_get_courses does not have a course description function :(.
     * So if the get_courses_returns function changes, this one should be changed too.
     *
     * @return array
     */
    private static function get_course_structure() {
        return [
            'id' => new external_value(PARAM_INT, 'course id'),
            'shortname' => new external_value(PARAM_RAW, 'course short name'),
            'categoryid' => new external_value(PARAM_INT, 'category id'),
            'categorysortorder' => new external_value(PARAM_INT,
                    'sort order into the category', VALUE_OPTIONAL),
            'fullname' => new external_value(PARAM_RAW, 'full name'),
            'displayname' => new external_value(PARAM_RAW, 'course display name'),
            'idnumber' => new external_value(PARAM_RAW, 'id number', VALUE_OPTIONAL),
            'summary' => new external_value(PARAM_RAW, 'summary'),
            'summaryformat' => new external_format_value('summary'),
            'format' => new external_value(PARAM_PLUGIN,
                    'course format: weeks, topics, social, site,..'),
            'showgrades' => new external_value(PARAM_INT,
                    '1 if grades are shown, otherwise 0', VALUE_OPTIONAL),
            'newsitems' => new external_value(PARAM_INT,
                    'number of recent items appearing on the course page', VALUE_OPTIONAL),
            'startdate' => new external_value(PARAM_INT,
                    'timestamp when the course start'),
            'enddate' => new external_value(PARAM_INT,
                    'timestamp when the course end'),
            'numsections' => new external_value(PARAM_INT,
                    '(deprecated, use courseformatoptions) number of weeks/topics',
                    VALUE_OPTIONAL),
            'maxbytes' => new external_value(PARAM_INT,
                    'largest size of file that can be uploaded into the course',
                    VALUE_OPTIONAL),
            'showreports' => new external_value(PARAM_INT,
                    'are activity report shown (yes = 1, no =0)', VALUE_OPTIONAL),
            'visible' => new external_value(PARAM_INT,
                    '1: available to student, 0:not available', VALUE_OPTIONAL),
            'hiddensections' => new external_value(PARAM_INT,
                    '(deprecated, use courseformatoptions) How the hidden sections in the course are displayed to students',
                    VALUE_OPTIONAL),
            'groupmode' => new external_value(PARAM_INT, 'no group, separate, visible',
                    VALUE_OPTIONAL),
            'groupmodeforce' => new external_value(PARAM_INT, '1: yes, 0: no',
                    VALUE_OPTIONAL),
            'defaultgroupingid' => new external_value(PARAM_INT, 'default grouping id',
                    VALUE_OPTIONAL),
            'timecreated' => new external_value(PARAM_INT,
                    'timestamp when the course have been created', VALUE_OPTIONAL),
            'timemodified' => new external_value(PARAM_INT,
                    'timestamp when the course have been modified', VALUE_OPTIONAL),
            'enablecompletion' => new external_value(PARAM_INT,
                    'Enabled, control via completion and activity settings. Disbaled,
                            not shown in activity settings.',
                    VALUE_OPTIONAL),
            'completionnotify' => new external_value(PARAM_INT,
                    '1: yes 0: no', VALUE_OPTIONAL),
            'lang' => new external_value(PARAM_SAFEDIR,
                    'forced course language', VALUE_OPTIONAL),
            'forcetheme' => new external_value(PARAM_PLUGIN,
                    'name of the force theme', VALUE_OPTIONAL),
            'courseformatoptions' => new external_multiple_structure(
                new external_single_structure(
                    ['name' => new external_value(PARAM_ALPHANUMEXT, 'course format option name'),
                            'value' => new external_value(PARAM_RAW, 'course format option value')
                    ]), 'additional options for particular course format', VALUE_OPTIONAL
            ),
            'showactivitydates' => new external_value(PARAM_BOOL, 'Whether the activity dates are shown or not'),
            'showcompletionconditions' => new external_value(PARAM_BOOL,
                    'Whether the activity completion conditions are shown or not'),
            'customfields' => new external_multiple_structure(
                new external_single_structure(
                    ['name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                        'shortname' => new external_value(PARAM_ALPHANUMEXT, 'The shortname of the custom field'),
                        'type'  => new external_value(PARAM_COMPONENT,
                                'The type of the custom field - text, checkbox...'),
                        'valueraw' => new external_value(PARAM_RAW, 'The raw value of the custom field'),
                        'value' => new external_value(PARAM_RAW, 'The value of the custom field')]
                ), 'Custom fields and associated values', VALUE_OPTIONAL),
        ];
    }
}
