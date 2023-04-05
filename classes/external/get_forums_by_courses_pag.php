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
use external_api;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use local_aspiredu\local\helper;

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
class get_forums_by_courses_pag extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course ID',
                    VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, []),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'start_date' => new external_value(PARAM_INT, 'since date', VALUE_DEFAULT, 0),
                'end_date' => new external_value(PARAM_INT, 'until date', VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_TEXT, 'sort result', VALUE_DEFAULT, 'ASC')
            ]
        );
    }

    /**
     * Returns a list of forums in a provided list of courses,
     * if no list is provided all forums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @param int|null $page
     * @param int|null $start_date
     * @param int|null $end_date
     * @param string $sort
     * @return array the forum details
     * @throws \invalid_parameter_exception
     * @throws \coding_exception
     * @throws \restricted_context_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function execute(
        array $courseids = [],
        ?int $page = -1,
        ?int $start_date = 0,
        ?int $end_date = 0,
        string $sort = 'ASC'
    ): array {
        global $CFG, $DB, $USER;
        $perpage = get_config('local_aspiredu', 'maxrecordsperpage');
        require_once($CFG->dirroot . "/mod/forum/lib.php");

        $params = self::validate_parameters(self::execute_parameters(),
            [
                'courseids' => $courseids,
                'page' => $page,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'sort' => $sort
            ]);

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }
        $sort = strtoupper($sort);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sort, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sort . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        // Array to store the forums to return.
        $arrforums = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Go through the courseids and return the forums.
            foreach ($courseids as $cid) {
                // Get the course context.
                $context = context_course::instance($cid);
                // Check the user can function in this context.
                self::validate_context($context);
                // Get the forums in this course.
                if ($forums = $DB->get_records('forum', ['course' => $cid])) {
                    // Get the modinfo for the course.
                    $modinfo = get_fast_modinfo($cid);
                    // Get the forum instances.
                    $foruminstances = $modinfo->get_instances_of('forum');
                    // Loop through the forums returned by modinfo.
                    foreach ($foruminstances as $forumid => $cm) {
                        // If it is not visible or present in the forums get_records call, continue.
                        if (!$cm->uservisible || !isset($forums[$forumid])) {
                            continue;
                        }
                        // Set the forum object.
                        $forum = $forums[$forumid];
                        // Get the module context.
                        $context = context_module::instance($cm->id);
                        // Check they have the view forum capability.
                        require_capability('mod/forum:viewdiscussion', $context);
                        // Add the course module id to the object, this information is useful.
                        $forum->cmid = $cm->id;

                        // Discussions count. This function does static request cache.
                        $forum->numdiscussions = forum_count_discussions($forum, $cm, $modinfo->get_course());
                        if ($start_date !== 0 || $end_date !== 0) {
                            if ($forum->timemodified < $start_date || ($end_date !== 0 && $forum->timemodified > $end_date)) {
                                continue;
                            }
                        }
                        // Add the forum to the array to return.
                        $arrforums[$forum->id] = (array) $forum;
                    }
                }
            }
        }
        switch ($sort) {
            case 'ASC':
                sort($arrforums);
              break;
            case 'DESC':
                rsort($arrforums);
        }
        $total_records = count($arrforums);
        $total_pages   = ceil($total_records / $perpage);

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        if ($page < 1) {
            $page = 1;
        }

        $offset = ($page - 1) * $perpage;

        // Get the subset of records to be displayed from the array
        return array_slice($arrforums, $offset, $perpage);
    }


    /**
     * Describes the get_forum return value.
     *
     * @return external_multiple_structure
     * @since Moodle 2.5
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'name' => new external_value(PARAM_RAW, 'Forum name'),
                ), 'forum'
            )
        );
    }
}
