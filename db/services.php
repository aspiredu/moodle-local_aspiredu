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
 * Service definitions WebServices.
 *
 * @package    local_aspiredu
 * @copyright  AspirEDU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'local_aspiredu_core_grades_get_grades' => [
        'classname' => '\local_aspiredu\external\core_grades_get_grades',
        'methodname' => 'execute',
        'description' => 'Returns grade item details and optionally student grades.',
        'type' => 'read',
        'capabilities' => 'moodle/grade:view, moodle/grade:viewall',
    ],
    'local_aspiredu_core_group_get_course_user_groups' => [
        'classname' => 'core_group_external',
        'methodname' => 'get_course_user_groups',
        'classpath' => 'group/externallib.php',
        'description' => 'Returns all groups in specified course for the specified user.',
        'type' => 'read',
        'capabilities' => 'moodle/course:managegroups',
    ],
    'local_aspiredu_gradereport_user_get_grades_table' => [
        'classname' => 'gradereport_user\\external\\user',
        'methodname' => 'get_grades_table',
        'description' => 'Get the user/s report grades table for a course',
        'type' => 'read',
        'capabilities' => '',
    ],
    'local_aspiredu_mod_forum_get_forums_by_courses' => [
        'classname' => 'mod_forum_external',
        'methodname' => 'get_forums_by_courses',
        'classpath' => 'mod/forum/externallib.php',
        'description' => 'Returns a list of forum instances in a provided set of courses, if
            no courses are provided then all the forum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion'
    ],
    'local_aspiredu_mod_forum_get_forum_discussions_paginated' => [
        'classname' => 'local_aspiredu_external',
        'methodname' => 'mod_forum_get_forum_discussions_paginated',
        'classpath' => 'local/aspiredu/externallib.php',
        'description' => 'Returns a list of forum discussions contained within a given set of forums.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting',
    ],
    'local_aspiredu_mod_forum_get_forum_discussion_posts' => [
        'classname' => 'local_aspiredu_external',
        'methodname' => 'mod_forum_get_forum_discussion_posts',
        'classpath' => 'local/aspiredu/externallib.php',
        'description' => 'Returns a list of forum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting',
    ],
    'local_aspiredu_report_log_get_log_records' => [
        'classname' => 'gradereport_user\\external\\report_log_get_log_records',
        'methodname' => 'get_grades_table',
        'description' => 'Returns a list of log entries for the course and parameters specified using the new log system.',
        'type' => 'read',
        'capabilities' => '',
    ],
    'local_aspiredu_mod_assign_get_assignments' => [
        'classname'   => 'mod_assign_external',
        'methodname'  => 'get_assignments',
        'classpath'   => 'mod/assign/externallib.php',
        'description' => 'Returns the courses and assignments for the users capability',
        'type' => 'read'
    ],
    'local_aspiredu_mod_assign_get_submissions' => [
        'classname' => 'mod_assign_external',
        'methodname' => 'get_submissions',
        'classpath' => 'mod/assign/externallib.php',
        'description' => 'Returns the submissions for assignments',
        'type' => 'read',
    ],
    'local_aspiredu_get_custom_course_settings' => [
        'classname' => 'local_aspiredu_external',
        'methodname' => 'get_custom_course_settings',
        'classpath' => 'local/aspiredu/externallib.php',
        'description' => 'Get all custom course settings',
        'type' => 'read'
    ],
    'local_aspiredu_core_course_get_courses_paginated' => [
        'classname' => '\local_aspiredu\external\core_course_get_courses_paginated',
        'methodname' => 'execute',
        'description' => 'Returns a paginated list of courses.',
        'type' => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
    'local_aspiredu_core_course_get_course_module' => [
        'classname' => 'core_course_external',
        'methodname' => 'get_course_module',
        'classpath' => 'course/externallib.php',
        'description' => 'Return information about a course module',
        'type' => 'read'
    ],

    'local_aspiredu_core_course_get_course_module_from_instance' => [
        'classname' => '\local_aspiredu\external\core_course_get_course_module_from_instance',
        'methodname' => 'execute',
        'description' => 'Return information about a course module',
        'type' => 'read'
    ],

    'local_aspiredu_core_grades_get_course_grades' => [
        'classname' => '\local_aspiredu\external\core_grades_get_course_grades',
        'methodname' => 'execute',
        'description' => 'Return the final course grade for the given users',
        'type' => 'read'
    ],
];

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'AspirEDU Services' => [
        'functions' => [
            'core_webservice_get_site_info',
            'core_cohort_get_cohorts',
            'core_cohort_get_cohort_members',
            'core_course_get_courses',
            'core_course_get_contents',
            'core_course_get_categories',
            'core_course_get_course_module',
            'core_course_get_course_module_by_instance',
            'core_enrol_get_enrolled_users',
            'core_user_get_users',
            'core_enrol_get_enrolled_users_with_capability',
            'core_group_get_groups',
            'core_group_get_groupings',
            'gradereport_overview_get_course_grades',
            'gradereport_user_get_grade_items',
            'mod_forum_get_discussion_posts',
            'mod_forum_get_forum_discussions',
            'local_aspiredu_mod_forum_get_forums_by_courses',
            'local_aspiredu_mod_forum_get_forum_discussions_paginated',
            'local_aspiredu_mod_forum_get_forum_discussion_posts',
            'local_aspiredu_gradereport_user_get_grades_table',
            'local_aspiredu_core_grades_get_grades',
            'local_aspiredu_core_group_get_course_user_groups',
            'local_aspiredu_report_log_get_log_records',
            'local_aspiredu_mod_assign_get_assignments',
            'local_aspiredu_mod_assign_get_submissions',
            'local_aspiredu_get_custom_course_settings',
            'local_aspiredu_core_course_get_courses_paginated',
            'local_aspiredu_core_course_get_course_module',
            'local_aspiredu_core_course_get_course_module_from_instance',
            'local_aspiredu_core_grades_get_course_grades'
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
    ]
];
