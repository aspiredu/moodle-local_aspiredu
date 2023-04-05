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

use context_course;
use context_module;
use context_user;
use core_course_external;
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

/**
 * Get users by role external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_get_contents_pag extends external_api {

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
        $sort = strtoupper($sort);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sort, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sort . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }
        $modules = [];
        $perpage = get_config('local_aspiredu', 'maxrecordsperpage');
        $contents = core_course_external::get_course_contents($courseid, array());
        foreach ($contents as $content) {
            if (isset($content['modules'][0]['completion']) && $content['modules'][0]['completion'] !== null) {
                $content['modules'][0]['completion_expected'] = $content['modules'][0]['completion'] > 0;
            }
            $modules = array_merge($content['modules'],$modules);
        }
        switch ($sort) {
            case 'ASC':
                sort($modules);
                break;
            case 'DESC':
                rsort($modules);
        }
        $total_records = count($modules);
        $total_pages   = ceil($total_records / $perpage);

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        if ($page < 1) {
            $page = 1;
        }

        $offset = ($page - 1) * $perpage;

        // Get the subset of records to be displayed from the array
        return array_slice($modules, $offset, $perpage);

        return $modules;
    }


    /**
     * Describes the get_forum return value.
     *
     * @return external_multiple_structure
     * @since Moodle 2.5
     */
    public static function execute_returns()
    {
        $completiondefinition = \core_completion\external\completion_info_exporter::get_read_structure(VALUE_DEFAULT, []);

        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'activity id'),
                    'modname' => new external_value(PARAM_PLUGIN, 'activity module type'),
                    'name' => new external_value(PARAM_RAW, 'activity module name'),
                    'instance' => new external_value(PARAM_INT, 'instance id', VALUE_OPTIONAL),
                    'modplural' => new external_value(PARAM_TEXT, 'activity module plural name'),
                    'completion_expected' => new external_value(PARAM_BOOL, 'Completion is configured', VALUE_OPTIONAL),
                )
            )
        );
    }
}
