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

require_once("$CFG->dirroot/course/externallib.php");
require_once("$CFG->dirroot/report/log/classes/renderable.php");
require_once("$CFG->dirroot/lib/externallib.php");

use context_module;
use core_user\fields;
use external_api;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_util;
use external_value;
use external_warnings;
use invalid_parameter_exception;
use moodle_exception;
use stdClass;
use user_picture;

/**
 * Web serivce class.
 */
class mod_forum_get_forum_discussion_posts extends external_api {
    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
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
                                        'fileurl' => new external_value(PARAM_URL, 'file download url'),
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
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters (
            [
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
            ]
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * NOTE - this function is based on a forward-port of a deprecated function - see MDL-65252.
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the forum post details
     */
    public static function execute($discussionid, $sortby = 'created', $sortdirection = 'DESC') {
        global $CFG, $DB, $USER, $PAGE;

        $warnings = [];

        // Validate the parameter.
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection, ]);

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

        $posts = [];
        foreach (forum_get_all_discussion_posts($discussion->id, $sort, $forumtracked) as $pid => $post) {
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
            $additionalfields = explode(',', implode(',', fields::get_picture_fields()));
            $user = username_load_fields_from_object($user, $post, null, $additionalfields);
            $post->userfullname = fullname($user, $canviewfullname);

            $userpicture = new user_picture($user);
            $userpicture->size = 1; // Size f1.
            $post->userpictureurl = $userpicture->get_url($PAGE)->out(false);

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forum', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = external_util::get_area_files($modcontext->id, 'mod_forum', 'attachment', $post->id);
            }

            $posts[$pid] = (array)$post;
        }

        $result = [];
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }
}
