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
class mod_forum_get_forum_discussion_posts_pag extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'start_date' => new external_value(PARAM_INT, 'since date', VALUE_DEFAULT, 0),
                'end_date' => new external_value(PARAM_INT, 'until date', VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_TEXT, 'sort result', VALUE_DEFAULT, 'ASC'),
                'includemsgcontent' => new external_value(PARAM_BOOL, 'Include content of post', VALUE_DEFAULT, false),
            ]
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param int|null $page
     * @param int|null $start_date
     * @param int|null $end_date
     * @param string $sort
     * @param bool $includemsgcontent
     * @return array the forum post details
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \required_capability_exception
     * @throws \restricted_context_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function execute(
        int $discussionid,
        ?int $page = -1,
        ?int $start_date = 0,
        ?int $end_date = 0,
        string $sort = 'ASC',
        bool $includemsgcontent = false
    ): array {
        global $CFG, $DB, $USER;

        $perpage = get_config('local_aspiredu', 'maxrecordsperpage');
        require_once($CFG->dirroot . "/mod/forum/lib.php");
        $warnings = [];
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'discussionid' => $discussionid,
                'page' => $page,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'sort' => $sort,
                'includemsgcontent' => $includemsgcontent
            ]);

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];
        $sortby         = 'created';
        $sort = $params['sort'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sort = strtoupper($sort);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sort, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sort . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/forum/discuss.php.
        require_once($CFG->dirroot . "/mod/forum/lib.php");

        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forum');

        if (! $post = forum_get_post_full($discussion->firstpost)) {
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

        $sort = 'p.' . $sortby . ' ' . $sort;
        $posts = forum_get_all_discussion_posts($discussion->id, $sort, $forumtracked);

        foreach ($posts as $pid => $post) {
            if ($start_date !== 0 || $end_date !== 0) {
                if ($post->created < $start_date || ($end_date !== 0 && $post->created > $end_date)) {
                    unset($posts[$pid]);
                    continue;
                }
            }

            if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                $warning = array();
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
                $posts[$pid]->postread = false;
            } else {
                $posts[$pid]->postread = true;
            }

            $posts[$pid]->canreply = $canreply;
            if (!empty($posts[$pid]->children)) {
                $posts[$pid]->children = array_keys($posts[$pid]->children);
            } else {
                $posts[$pid]->children = array();
            }

            // Rewrite embedded images URLs.
            if ($includemsgcontent) {
                [$post->message, $post->messageformat] =
                    external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forum', 'post', $post->id);
            } else {
                unset($post->message, $post->messageformat);
            }

            $posts[$pid] = (array) $post;
        }

        $total_records = count($posts);
        $total_pages   = ceil($total_records / $perpage);

        if ($page > $total_pages) {
            $page = $total_pages;
        }
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perpage;
        // Get the subset of records to be displayed from the array
        $result = array();
        $result['posts'] = array_slice($posts, $offset, $perpage);
        return $result;
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
                'posts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'message' => new external_value(PARAM_RAW, 'The post message',VALUE_OPTIONAL),
                            'messageformat' => new external_format_value('message', VALUE_OPTIONAL),
                        ), 'post'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }
}
