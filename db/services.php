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

defined('MOODLE_INTERNAL') || die;

$functions = [
    'local_aspiredu_core_grades_get_grades' => [
        'classname' => '\local_aspiredu\external\core_grades_get_grades',
        'methodname' => 'execute',
        'description' => 'Returns grade item details and optionally student grades.',
        'type' => 'read',
        'capabilities' => 'moodle/grade:view, moodle/grade:viewall',
    ],
    'local_aspiredu_mod_forum_get_forum_discussion_posts' => [
        'classname' => 'local_aspiredu\external\mod_forum_get_forum_discussion_posts',
        'methodname' => 'execute',
        'description' => 'Returns a list of forum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting',
    ],
    'local_aspiredu_report_log_get_log_records' => [
        'classname' => 'local_aspiredu\external\report_log_get_log_records',
        'methodname' => 'execute',
        'description' => 'Returns a list of log entries for the course and parameters specified using the new log system.',
        'type' => 'read',
        'capabilities' => '',
    ],
    'local_aspiredu_core_course_get_courses_paginated' => [
        'classname' => '\local_aspiredu\external\core_course_get_courses_paginated',
        'methodname' => 'execute',
        'description' => 'Returns a paginated list of courses.',
        'type' => 'read',
        'capabilities' => 'moodle/course:view, moodle/course:viewhiddencourses',
    ],
    'local_aspiredu_core_grades_get_course_grades' => [
        'classname' => '\local_aspiredu\external\core_grades_get_course_grades',
        'methodname' => 'execute',
        'description' => 'Return the final course grade for the given users',
        'type' => 'read',
    ],
    'local_aspiredu_get_plugin_info' => [
        'classname' => 'local_aspiredu\external\get_plugin_info',
        'methodname'  => 'execute',
        'description' => 'Fetch information regarding the AspirEDU plugin.',
        'type' => 'read',
        'capabilities' => '',
    ],
    'local_aspiredu_get_users_by_roles' => [
        'classname'    => 'local_aspiredu\external\get_users_by_roles',
        'methodname'  => 'execute',
        'description'  => 'Return a list of users given a list of roles',
        'type'         => 'read',
        'capabilities' => 'moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail',
    ],
    'local_aspiredu_get_users_by_capabilities' => [
        'classname'    => 'local_aspiredu\external\get_users_by_capabilities',
        'methodname'  => 'execute',
        'description'  => 'Return a list of users given a list of capabilities',
        'type'         => 'read',
        'capabilities' => 'moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail',
    ],
    'local_aspiredu_get_site_admins' => [
        'classname'    => '\local_aspiredu\external\get_site_admins',
        'methodname'  => 'execute',
        'description'  => 'Return a list of users who are site admins',
        'type'         => 'read',
        'capabilities' => 'moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail',
    ],
    'local_aspiredu_gradereport_user_get_grade_items' => [
        'classname'    => '\local_aspiredu\external\gradereport_user_get_grade_items',
        'methodname'  => 'execute',
        'description' => 'Extends gradereport_user_get_grade_items, forcing some configuration of the report',
        'type' => 'read',
        'capabilities' => 'gradereport/user:view',
    ],
    'local_aspiredu_get_role_users' => [
        'classname'    => '\local_aspiredu\external\get_role_users',
        'methodname'  => 'execute',
        'description' => 'Wrapper for get_role_users',
        'type' => 'read',
        'capabilities' => 'moodle/user:viewdetails, moodle/user:viewhiddendetails, moodle/course:useremail',
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
            'core_group_get_course_user_groups',
            'core_group_get_groups',
            'core_group_get_groupings',
            'gradereport_overview_get_course_grades',
            'gradereport_user_get_grades_table',
            'gradereport_user_get_grade_items',
            'mod_assign_get_assignments',
            'mod_assign_get_submissions',
            'mod_forum_get_discussion_posts',
            'mod_forum_get_forum_discussions',
            'mod_forum_get_forums_by_courses',
            'local_aspiredu_mod_forum_get_forum_discussion_posts',
            'local_aspiredu_core_grades_get_grades',
            'local_aspiredu_report_log_get_log_records',
            'local_aspiredu_core_course_get_courses_paginated',
            'local_aspiredu_core_grades_get_course_grades',
            'local_aspiredu_get_plugin_info',
            'local_aspiredu_get_users_by_roles',
            'local_aspiredu_get_users_by_capabilities',
            'local_aspiredu_get_site_admins',
            'local_aspiredu_gradereport_user_get_grade_items',
            'local_aspiredu_get_role_users',
        ],
        'restrictedusers' => 1,
        'enabled' => 1,
    ],
];
