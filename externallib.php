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
 * @author     AspirEDU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once("$CFG->dirroot/local/aspiredu/renderable.php");

class local_aspiredu_external extends external_api {
    /**
     * Describes the parameters for mod_forum_get_forum_discussions_paginated.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function mod_forum_get_forum_discussions_paginated_parameters() {
        return new external_function_parameters (
            [
                'forumid' => new external_value(PARAM_INT, 'forum ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            ]
        );
    }


    /**
     * Returns a list of forum discussions as well as a summary of the discussion in a provided list of forums.
     *
     * @param $forumid
     * @param string $sortby
     * @param string $sortdirection
     * @param int $page
     * @param int $perpage
     * @return array the forum discussion details
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @since Moodle 2.8
     */
    public static function mod_forum_get_forum_discussions_paginated($forumid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                                     $page = -1, $perpage = 0) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $warnings = [];

        $params = self::validate_parameters(self::mod_forum_get_forum_discussions_paginated_parameters(),
            [
                'forumid' => $forumid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            ]
        );

        // Compact/extract functions are not recommended.
        $forumid = $params['forumid'];
        $sortby = $params['sortby'];
        $sortdirection = $params['sortdirection'];
        $page = $params['page'];
        $perpage = $params['perpage'];

        $sortallowedvalues = ['id', 'timemodified', 'timestart', 'timeend'];
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = ['ASC', 'DESC'];
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forum');

        $sort = 'd.' . $sortby . ' ' . $sortdirection;
        $discussions = forum_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage);

        if ($discussions) {
            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = [];
            if ($cantrack = forum_tp_can_track_forums($forum)) {
                if ($forumtracked = forum_tp_is_tracked($forum)) {
                    $unreads = forum_get_discussions_unread($cm);
                }
            }
            // The forum function returns the replies for all the discussions in a given forum.
            $replies = forum_count_discussion_replies($forumid, $sort, -1, $page, $perpage);

            foreach ($discussions as $did => $discussion) {
                // This function checks for qanda forums.
                if (!forum_user_can_see_discussion($forum, $discussion, $modcontext)) {
                    $warning = [];
                    // Function forum_get_discussions returns forum_posts ids not forum_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && !empty($forumtracked)) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int)$unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int)$replies[$discussion->discussion]->replies;
                }

                // Load user objects from the results of the query.
                $user = new stdClass();
                $user->id = $discussion->userid;
                $user = username_load_fields_from_object($user, $discussion);
                $discussion->userfullname = fullname($user);
                $discussion->userpictureurl = moodle_url::make_pluginfile_url(
                    context_user::instance($user->id)->id, 'user', 'icon', null, '/', 'f1');
                // Fix the pluginfile.php link.
                $discussion->userpictureurl = str_replace('pluginfile.php', 'webservice/pluginfile.php',
                    $discussion->userpictureurl);

                $usermodified = new stdClass();
                $usermodified->id = $discussion->usermodified;
                $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um');
                $discussion->usermodifiedfullname = fullname($usermodified);
                $discussion->usermodifiedpictureurl = moodle_url::make_pluginfile_url(
                    context_user::instance($usermodified->id)->id, 'user', 'icon', null, '/', 'f1');
                // Fix the pluginfile.php link.
                $discussion->usermodifiedpictureurl = str_replace('pluginfile.php', 'webservice/pluginfile.php',
                    $discussion->usermodifiedpictureurl);

                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                        $modcontext->id, 'mod_forum', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = [];

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment',
                        $discussion->id, 'filename', false)) {
                        foreach ($files as $file) {
                            $filename = $file->get_filename();

                            $discussion->attachments[] = [
                                'filename' => $filename,
                                'mimetype' => $file->get_mimetype(),
                                'fileurl' => moodle_url::make_webservice_pluginfile_url($modcontext->id, 'mod_forum',
                                    'attachment', $discussion->id, '/', $filename),
                            ];
                        }
                    }
                }

                $discussions[$did] = (array)$discussion;
            }
        }

        $result = [];
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the mod_forum_get_forum_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function mod_forum_get_forum_discussions_paginated_returns() {
        return new external_single_structure(
            [
                'discussions' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'name' => new external_value(PARAM_RAW, 'Discussion name'),
                            'groupid' => new external_value(PARAM_INT, 'Group id'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                            'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                            'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                            'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'modified' => new external_value(PARAM_INT, 'Time modified'),
                            'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                            'subject' => new external_value(PARAM_RAW, 'The post subject'),
                            'message' => new external_value(PARAM_RAW, 'The post message'),
                            'messageformat' => new external_format_value('message'),
                            'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                            'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                            'attachments' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'filename' => new external_value(PARAM_FILE, 'file name'),
                                        'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                        'fileurl' => new external_value(PARAM_URL, 'file download url')
                                    ]
                                ), 'attachments', VALUE_OPTIONAL
                            ),
                            'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                            'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                            'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                            'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                            'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                            'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                            'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                            'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                                    not available due to forum settings.')
                        ], 'post'
                    )
                ),
                'warnings' => new external_warnings()
            ]
        );
    }


    /**
     * Describes the parameters for get_forum_discussion_posts.
     *
     * @return external_function_parameters
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts_parameters() {
        return new external_function_parameters (
            [
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            ]
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the forum post details
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts($discussionid, $sortby = 'created', $sortdirection = 'DESC') {
        global $CFG, $DB, $USER;

        $warnings = [];

        // Validate the parameter.
        $params = self::validate_parameters(self::mod_forum_get_forum_discussion_posts_parameters(),
            [
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection]);

        // Compact/extract functions are not recommended.
        $discussionid = $params['discussionid'];
        $sortby = $params['sortby'];
        $sortdirection = $params['sortdirection'];

        $sortallowedvalues = ['id', 'created', 'modified'];
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = ['ASC', 'DESC'];
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $forum->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/forum/discuss.php.
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forum');

        if (!$post = forum_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'forum');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'forum');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = forum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

        $forumtracked = forum_tp_is_tracked($forum);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $posts = forum_get_all_discussion_posts($discussion->id, $sort, $forumtracked);

        foreach ($posts as $pid => $post) {

            if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                $warning = [];
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function forum_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = [];
            }

            $user = new stdClass();
            $user->id = $post->userid;
            $user = username_load_fields_from_object($user, $post);
            $post->userfullname = fullname($user, $canviewfullname);
            $post->userpictureurl = moodle_url::make_pluginfile_url(
                context_user::instance($user->id)->id, 'user', 'icon', null, '/', 'f1');
            // Fix the pluginfile.php link.
            $post->userpictureurl = str_replace('pluginfile.php', 'webservice/pluginfile.php',
                $post->userpictureurl);

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forum', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = [];

                $fs = get_file_storage();
                if ($files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment', $post->id, 'filename', false)) {
                    foreach ($files as $file) {
                        $filename = $file->get_filename();

                        $post->attachments[] = [
                            'filename' => $filename,
                            'mimetype' => $file->get_mimetype(),
                            'fileurl' => moodle_url::make_webservice_pluginfile_url($modcontext->id, 'mod_forum',
                                'attachment', $post->id, '/', $filename),
                        ];
                    }
                }
            }

            $posts[$pid] = (array)$post;
        }

        $result = [];
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_forum_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts_returns() {
        return new external_single_structure(
            [
                'posts' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'modified' => new external_value(PARAM_INT, 'Time modified'),
                            'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                            'subject' => new external_value(PARAM_RAW, 'The post subject'),
                            'message' => new external_value(PARAM_RAW, 'The post message'),
                            'messageformat' => new external_format_value('message'),
                            'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                            'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                            'attachments' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'filename' => new external_value(PARAM_FILE, 'file name'),
                                        'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                        'fileurl' => new external_value(PARAM_URL, 'file download url')
                                    ]
                                ), 'attachments', VALUE_OPTIONAL
                            ),
                            'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                            'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                            'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                            'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                            'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                            'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                            'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL),
                        ], 'post'
                    )
                ),
                'warnings' => new external_warnings()
            ]
        );
    }


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function core_group_get_course_user_groups_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'userid' => new external_value(PARAM_INT, 'id of user')
            ]
        );
    }

    /**
     * Get all groups in the specified course for the specified user
     *
     * @param int $courseid id of course
     * @param int $userid id of user
     * @return array of group objects (id, name ...)
     */
    public static function core_group_get_course_user_groups($courseid, $userid) {
        global $USER;

        // Warnings array, it can be empty at the end but is mandatory.
        $warnings = [];

        $params = [
            'courseid' => $courseid,
            'userid' => $userid
        ];
        $params = self::validate_parameters(self::core_group_get_course_user_groups_parameters(), $params);
        $courseid = $params['courseid'];
        $userid = $params['userid'];

        // Validate course and user. get_course throws an exception if the course does not exists.
        get_course($courseid);
        core_user::get_user($userid, 'id', MUST_EXIST);

        // Security checks.
        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Check if we have permissions for retrieve the information.
        if ($userid != $USER->id) {
            if (!has_capability('moodle/course:managegroups', $context)) {
                throw new moodle_exception('accessdenied', 'admin');
            }
            // Validate if the user is enrolled in the course.
            if (!is_enrolled($context, $userid)) {
                // We return a warning because the function does not fail for not enrolled users.
                $warning['item'] = 'course';
                $warning['itemid'] = $courseid;
                $warning['warningcode'] = '1';
                $warning['message'] = "User $userid is not enrolled in course $courseid";
                $warnings[] = $warning;
            }
        }

        $usergroups = [];
        if (empty($warnings)) {
            $groups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name, g.description, g.descriptionformat');

            foreach ($groups as $group) {
                list($group->description, $group->descriptionformat) =
                    external_format_text($group->description, $group->descriptionformat,
                        $context->id, 'group', 'description', $group->id);
                $usergroups[] = (array)$group;
            }
        }

        return [
            'groups' => $usergroups,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function core_group_get_course_user_groups_returns() {
        return new external_single_structure(
            [
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'group record id'),
                            'name' => new external_value(PARAM_TEXT, 'multilang compatible name, course unique'),
                            'description' => new external_value(PARAM_RAW, 'group description text'),
                            'descriptionformat' => new external_format_value('description')
                        ]
                    )
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }


    /**
     * Describes the parameters for get_grades_table.
     *
     * @return external_function_parameters
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table_parameters() {
        return new external_function_parameters (
            [
                'courseid' => new external_value(PARAM_INT, 'Course Id', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'Return grades only for this user (optional)', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * Returns a list of grades tables for users in a course.
     *
     * @param int $courseid Course Id
     * @param int $userid Only this user (optional)
     *
     * @return array the grades tables
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table($courseid, $userid = 0) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/user/lib.php');

        $warnings = [];

        // Validate the parameter.
        $params = self::validate_parameters(self::gradereport_user_get_grades_table_parameters(),
            [
                'courseid' => $courseid,
                'userid' => $userid]
        );

        // Compact/extract functions are not recommended.
        $courseid = $params['courseid'];
        $userid = $params['userid'];

        // Function get_course internally throws an exception if the course doesn't exist.
        $course = get_course($courseid);

        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Specific capabilities.
        require_capability('gradereport/user:view', $context);

        $user = null;

        if (empty($userid)) {
            require_capability('moodle/grade:viewall', $context);
        } else {
            $user = core_user::get_user($userid, '*', MUST_EXIST);
        }

        $access = false;

        if (has_capability('moodle/grade:viewall', $context)) {
            // Can view all course grades.
            $access = true;
        } else if ($userid == $USER->id && has_capability('moodle/grade:view', $context) && $course->showgrades) {
            // View own grades.
            $access = true;
        } else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) && $course->showgrades) {
            // Can view grades of this user, parent most probably.
            $access = true;
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error', $CFG->wwwroot . '/course/view.php?id=' . $courseid);
        }

        $gpr = new grade_plugin_return(
            [
                'type' => 'report',
                'plugin' => 'user',
                'courseid' => $courseid,
                'userid' => $userid]
        );

        $tables = [];

        // Just one user.
        if ($user) {
            $report = new \gradereport_user\report\user($courseid, $gpr, $context, $userid);
            $report->fill_table();

            // Notice that we use array_filter for deleting empty elements in the array.
            // Those elements are items or category not visible by the user.
            $tables[] = [
                'courseid' => $courseid,
                'userid' => $user->id,
                'userfullname' => fullname($user),
                'maxdepth' => $report->maxdepth,
                'tabledata' => $report->tabledata
            ];

        } else {
            $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
            $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
            $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);

            $gui = new graded_users_iterator($course);
            $gui->require_active_enrolment($showonlyactiveenrol);
            $gui->init();

            while ($userdata = $gui->next_user()) {
                $currentuser = $userdata->user;
                $report = new \gradereport_user\report\user($courseid, $gpr, $context, $currentuser->id);
                $report->fill_table();

                // Notice that we use array_filter for deleting empty elements in the array.
                // Those elements are items or category not visible by the user.
                $tables[] = [
                    'courseid' => $courseid,
                    'userid' => $currentuser->id,
                    'userfullname' => fullname($currentuser),
                    'maxdepth' => $report->maxdepth,
                    'tabledata' => $report->tabledata
                ];
            }
            $gui->close();
        }

        $result = [];
        $result['tables'] = $tables;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates a table column structure
     *
     * @return array
     * @since  Moodle 2.8
     */
    private static function grades_table_column() {
        return [
            'class' => new external_value(PARAM_RAW, 'class'),
            'content' => new external_value(PARAM_RAW, 'cell content'),
            'headers' => new external_value(PARAM_RAW, 'headers')
        ];
    }

    /**
     * Describes tget_grades_table return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table_returns() {
        return new external_single_structure(
            [
                'tables' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'userfullname' => new external_value(PARAM_TEXT, 'user fullname'),
                            'maxdepth' => new external_value(PARAM_INT, 'table max depth (needed for printing it)'),
                            'tabledata' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'itemname' => new external_single_structure(
                                            [
                                                'class' => new external_value(PARAM_RAW, 'file name'),
                                                'colspan' => new external_value(PARAM_INT, 'mime type'),
                                                'content' => new external_value(PARAM_RAW, ''),
                                                'celltype' => new external_value(PARAM_RAW, ''),
                                                'id' => new external_value(PARAM_ALPHANUMEXT, '')
                                            ], 'The item returned data', VALUE_OPTIONAL
                                        ),
                                        'leader' => new external_single_structure(
                                            [
                                                'class' => new external_value(PARAM_RAW, 'file name'),
                                                'rowspan' => new external_value(PARAM_INT, 'mime type')
                                            ], 'The item returned data', VALUE_OPTIONAL
                                        ),
                                        'weight' => new external_single_structure(
                                            self::grades_table_column(), 'weight column', VALUE_OPTIONAL
                                        ),
                                        'grade' => new external_single_structure(
                                            self::grades_table_column(), 'grade column', VALUE_OPTIONAL
                                        ),
                                        'range' => new external_single_structure(
                                            self::grades_table_column(), 'range column', VALUE_OPTIONAL
                                        ),
                                        'percentage' => new external_single_structure(
                                            self::grades_table_column(), 'percentage column', VALUE_OPTIONAL
                                        ),
                                        'lettergrade' => new external_single_structure(
                                            self::grades_table_column(), 'lettergrade column', VALUE_OPTIONAL
                                        ),
                                        'rank' => new external_single_structure(
                                            self::grades_table_column(), 'rank column', VALUE_OPTIONAL
                                        ),
                                        'average' => new external_single_structure(
                                            self::grades_table_column(), 'average column', VALUE_OPTIONAL
                                        ),
                                        'feedback' => new external_single_structure(
                                            self::grades_table_column(), 'feedback column', VALUE_OPTIONAL
                                        ),
                                        'contributiontocoursetotal' => new external_single_structure(
                                            self::grades_table_column(), 'contributiontocoursetotal column', VALUE_OPTIONAL
                                        ),
                                    ], 'table'
                                )
                            )
                        ]
                    )
                ),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function report_log_get_log_records_parameters() {
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
    public static function report_log_get_log_records($courseid = 0, $userid = 0, $groupid = 0, $date = 0, $modid = 0,
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
        $params = self::validate_parameters(self::report_log_get_log_records_parameters(), $params);

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

        // Check if we are in 2.7 or above.
        if (class_exists('local_report_log_renderable')) {
            $reportlog = new local_report_log_renderable($params['logreader'], $course, $params['userid'], $params['modid'],
                $params['modaction'],
                $params['group'], $params['edulevel'], true, true,
                false, true, '', $params['date'], '',
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
        }

        return [
            'logs' => $logsrecords,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function report_log_get_log_records_returns() {
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

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_custom_course_settings_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'id of course, 0 for all', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Get all custom course settings
     *
     * @param int $courseid id of course
     * @return array of settings
     */
    public static function get_custom_course_settings($courseid = 0) {

        // Warnings array, it can be empty at the end but is mandatory.
        $warnings = [];
        $settings = [];

        $params = [
            'courseid' => $courseid
        ];
        $params = self::validate_parameters(self::get_custom_course_settings_parameters(), $params);
        $courseid = $params['courseid'];

        $courses = [];
        if ($courseid) {
            $courses[] = $courseid;
            $settings[$courseid] = get_config('local_aspiredu', 'course' . $courseid);
        } else {
            $coursesettings = get_config('local_aspiredu');
            foreach ($coursesettings as $key => $val) {
                if (strpos($key, 'course') !== false) {
                    $courseid = str_replace('course', '', $key);
                    $courses[] = $courseid;
                    $settings[$courseid] = $val;
                }
            }
        }

        foreach ($courses as $id) {
            try {
                $context = context_course::instance($id);
                self::validate_context($context);
                require_capability('moodle/course:update', $context);

            } catch (Exception $e) {
                $warnings[] = [
                    'item' => 'course',
                    'itemid' => $id,
                    'warningcode' => '1',
                    'message' => 'No access rights in course context'
                ];
                unset($settings[$id]);
            }
        }

        $finalsettings = [];
        foreach ($settings as $courseid => $settingjson) {
            $settingjson = json_decode($settingjson);
            foreach ($settingjson as $name => $value) {
                $finalsettings[] = [
                    'courseid' => $courseid,
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        return [
            'settings' => $finalsettings,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_custom_course_settings_returns() {
        return new external_single_structure(
            [
                'settings' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'name' => new external_value(PARAM_NOTAGS, 'setting name'),
                            'value' => new external_value(PARAM_NOTAGS, 'setting value'),
                        ]
                    )
                ),
                'warnings' => new external_warnings(),
            ]
        );
    }
}