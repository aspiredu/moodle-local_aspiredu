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

namespace local_aspiredu;

defined('MOODLE_INTERNAL') || die();

use context_module;
use external_api;
use externallib_advanced_testcase;
use local_aspiredu\external\mod_forum_get_forum_discussion_posts;
use mod_forum_external;
use moodle_url;
use stdClass;
use user_picture;

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/forum/externallib.php');

/**
 * Tests for mod_forum_get_forum_discussion_posts WS function.
 * @covers \local_aspiredu\external\mod_forum_get_forum_discussion_posts
 */
class mod_forum_get_forum_discussion_posts_test extends externallib_advanced_testcase {

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_mod_forum_get_forum_discussion_posts() {
        global $CFG, $PAGE;

        $this->resetAfterTest();

        // Set the CFG variable to allow track forums.
        $CFG->forum_trackreadposts = true;

        // Create a user who can track forums.
        $record = new stdClass();
        $record->trackforums = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();

        // Forum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUM_TRACKING_OFF;
        $forum1 = self::getDataGenerator()->create_module('forum', $record);
        $forum1context = context_module::instance($forum1->cmid);

        // Forum with tracking enabled.
        $record = new stdClass();
        $record->course = $course1->id;
        $forum2 = self::getDataGenerator()->create_module('forum', $record);
        $forum2cm = get_coursemodule_from_id('forum', $forum2->cmid);
        $forum2context = context_module::instance($forum2->cmid);

        // Add discussions to the forums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->forum = $forum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->forum = $forum1->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->forum = $forum2->id;
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        // Add 2 replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $record->attachment = true;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);
        $filename = 'shouldbeanimage.jpg';
        // Add a fake inline image to the post.
        $filerecordinline = [
            'contextid' => $forum1context->id,
            'component' => 'mod_forum',
            'filearea' => 'attachment',
            'itemid' => $discussion1reply1->id,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $fs = get_file_storage();
        $timepost = time();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $record->tags = ['Cats', 'Dogs'];
        unset($record->attachment);
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);

        // Enrol the user in the  course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/forum:viewdiscussion on all forums of the course.
        static::getDataGenerator()->enrol_user($user1->id, $course1->id);
        static::getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Delete one user, to test that we still receive posts by this user.
        delete_user($user3);

        // Create what we expect to be returned when querying the discussion.
        $expectedposts = [
            'posts' => [],
            'warnings' => [],
        ];

        // User pictures are initially empty, we should get the links once the external function is called.
        $expectedposts['posts'][] = [
            'id' => $discussion1reply2->id,
            'discussion' => $discussion1reply2->discussion,
            'parent' => $discussion1reply2->parent,
            'userid' => (int)$discussion1reply2->userid,
            'created' => $discussion1reply2->created,
            'modified' => $discussion1reply2->modified,
            'mailed' => $discussion1reply2->mailed,
            'subject' => $discussion1reply2->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply2->message, 'pluginfile.php',
                $forum1context->id, 'mod_forum', 'post', $discussion1reply2->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply2->messagetrust,
            'attachment' => $discussion1reply2->attachment,
            'totalscore' => $discussion1reply2->totalscore,
            'mailnow' => $discussion1reply2->mailnow,
            'children' => [],
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user3),
            'userpictureurl' => '',
        ];

        $expectedposts['posts'][] = [
            'id' => $discussion1reply1->id,
            'discussion' => $discussion1reply1->discussion,
            'parent' => $discussion1reply1->parent,
            'userid' => (int)$discussion1reply1->userid,
            'created' => $discussion1reply1->created,
            'modified' => $discussion1reply1->modified,
            'mailed' => $discussion1reply1->mailed,
            'subject' => $discussion1reply1->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply1->message, 'pluginfile.php',
                $forum1context->id, 'mod_forum', 'post', $discussion1reply1->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply1->messagetrust,
            'attachment' => $discussion1reply1->attachment,
            'attachments' => [
                [
                    'filename' => $filename,
                    'fileurl' => moodle_url::make_webservice_pluginfile_url($forum1context->id, 'mod_forum', 'attachment',
                        $discussion1reply1->id, '/', $filename)->out(false),
                    'mimetype' => 'image/jpeg',
                ],
            ],
            'totalscore' => $discussion1reply1->totalscore,
            'mailnow' => $discussion1reply1->mailnow,
            'children' => [$discussion1reply2->id],
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user2),
            'userpictureurl' => '',
        ];

        // Test a discussion with two additional posts (total 3 posts).
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion1->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        static::assertEquals(3, count($posts['posts']));

        // Generate here the pictures because we need to wait to the external function to init the theme.
        $userpicture = new user_picture($user3);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user2);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][1]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        // Unset the initial discussion post.
        array_pop($posts['posts']);
        static::assertEquals($expectedposts, $posts);

        // Test discussion without additional posts. There should be only one post (the one created by the discussion).
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion2->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        static::assertEquals(1, count($posts['posts']));

        // Test posts have not been marked as read.
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion1->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        foreach ($posts['posts'] as $post) {
            static::assertFalse($post['postread']);
        }

        // Test discussion tracking on tracked forum.
        $result = mod_forum_external::view_forum_discussion($discussion3->id);

        // Test posts have been marked as read.
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion3->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        foreach ($posts['posts'] as $post) {
            static::assertTrue($post['postread']);
        }
    }

    /**
     * Test calling the function for deleted posts.
     * @runInSeparateProcess
     */
    public function test_mod_forum_get_forum_discussion_posts_deleted() {
        $this->resetAfterTest();
        $generator = self::getDataGenerator()->get_plugin_generator('mod_forum');

        // Create a course and enrol some users in it.
        $course1 = self::getDataGenerator()->create_course();

        // Create users.
        $user1 = self::getDataGenerator()->create_user();
        static::getDataGenerator()->enrol_user($user1->id, $course1->id);
        $user2 = self::getDataGenerator()->create_user();
        static::getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create test data.
        $forum1 = self::getDataGenerator()->create_module('forum', (object)[
            'course' => $course1->id,
        ]);
        $forum1context = context_module::instance($forum1->cmid);

        // Add discussions to the forum.
        $discussion = $generator->create_discussion((object)[
            'course' => $course1->id,
            'userid' => $user1->id,
            'forum' => $forum1->id,
        ]);

        $discussion2 = $generator->create_discussion((object)[
            'course' => $course1->id,
            'userid' => $user2->id,
            'forum' => $forum1->id,
        ]);

        // Add replies to the discussion.
        $discussionreply1 = $generator->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $user2->id,
        ]);
        $discussionreply2 = $generator->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussionreply1->id,
            'userid' => $user2->id,
            'subject' => '',
            'message' => '',
            'messageformat' => FORMAT_PLAIN,
            'deleted' => 1,
        ]);
        $discussionreply3 = $generator->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $user2->id,
        ]);

        // Test where some posts have been marked as deleted.
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        $deletedsubject = get_string('privacy:request:delete:post:subject', 'mod_forum');
        $deletedmessage = get_string('privacy:request:delete:post:message', 'mod_forum');

        foreach ($posts['posts'] as $post) {
            if ($post['id'] == $discussionreply2->id) {
                static::assertEquals($deletedsubject, $post['subject']);
                static::assertEquals($deletedmessage, $post['message']);
            } else {
                static::assertNotEquals($deletedsubject, $post['subject']);
                static::assertNotEquals($deletedmessage, $post['message']);
            }
        }
    }

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_mod_forum_get_forum_discussion_posts_qanda() {
        global $CFG, $DB;

        $this->resetAfterTest();

        $record = new stdClass();
        $user1 = self::getDataGenerator()->create_user($record);
        $user2 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();
        static::getDataGenerator()->enrol_user($user1->id, $course1->id);
        static::getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Forum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->type = 'qanda';
        $forum1 = self::getDataGenerator()->create_module('forum', $record);
        $forum1context = context_module::instance($forum1->cmid);

        // Add discussions to the forums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->forum = $forum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        // Add 1 reply (not the actual user).
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);

        // We still see only the original post.
        $posts = mod_forum_get_forum_discussion_posts::execute($discussion1->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        static::assertEquals(1, count($posts['posts']));

        // Add a new reply, the user is going to be able to see only the original post and their new post.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user1->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);

        $posts = mod_forum_get_forum_discussion_posts::execute($discussion1->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        static::assertEquals(2, count($posts['posts']));

        // Now, we can fake the time of the user post, so he can se the rest of the discussion posts.
        $discussion1reply2->created -= $CFG->maxeditingtime * 2;
        $DB->update_record('forum_posts', $discussion1reply2);

        $posts = mod_forum_get_forum_discussion_posts::execute($discussion1->id, 'modified');
        $posts = external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts::execute_returns(), $posts);
        static::assertEquals(3, count($posts['posts']));
    }
}
