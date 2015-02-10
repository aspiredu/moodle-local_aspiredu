<?php

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

$functions = array(
    'local_aspiredu_core_grades_get_grades' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'core_grades_get_grades',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Returns grade item details and optionally student grades.',
        'type'          => 'read',
        'capabilities'  => 'moodle/grade:view, moodle/grade:viewall',
    ),
    'local_aspiredu_core_group_get_course_user_groups' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'core_group_get_course_user_groups',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Returns all groups in specified course for the specified user.',
        'type'          => 'read',
        'capabilities'  => 'moodle/course:managegroups',
    ),
    'local_aspiredu_gradereport_user_get_grades_table' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'gradereport_user_get_grades_table',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Get the user/s report grades table for a course',
        'type'          => 'read',
        'capabilities'  => '',
    ),
    'local_aspiredu_mod_forum_get_forums_by_courses' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'mod_forum_get_forums_by_courses',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Returns a list of forum instances in a provided set of courses, if
            no courses are provided then all the forum instances the user has access to will be
            returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/forum:viewdiscussion'
    ),
    'local_aspiredu_mod_forum_get_forum_discussions_paginated' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'mod_forum_get_forum_discussions_paginated',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Returns a list of forum discussions contained within a given set of forums.',
        'type'          => 'read',
        'capabilities'  => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting',
    ),
    'local_aspiredu_mod_forum_get_forum_discussion_posts' => array(
        'classname'     => 'local_aspiredu_external',
        'methodname'    => 'mod_forum_get_forum_discussion_posts',
        'classpath'     => 'local/aspiredu/externallib.php',
        'description'   => 'Returns a list of forum posts for a discussion.',
        'type'          => 'read',
        'capabilities'  => 'mod/forum:viewdiscussion, mod/forum:viewqandawithoutposting',
    ),

);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'AspirEDU Services' => array(
        'functions' => array (
            'core_webservice_get_site_info',
            'core_course_get_courses',
            'core_course_get_categories',
            'core_enrol_get_enrolled_users',
            'core_user_get_users',
            'core_enrol_get_enrolled_users_with_capability',
            'core_group_get_groups',
            'core_group_get_groupings',
            'core_grades_get_grades',
            'local_aspiredu_mod_forum_get_forums_by_courses',
            'local_aspiredu_mod_forum_get_forum_discussions_paginated',
            'local_aspiredu_mod_forum_get_forum_discussion_posts',
            'local_aspiredu_gradereport_user_get_grades_table',
            'local_aspiredu_core_grades_get_grades',
            'local_aspiredu_core_group_get_course_user_groups',
        ),
        'restrictedusers' => 1,
        'enabled' => 1,
    )
);
