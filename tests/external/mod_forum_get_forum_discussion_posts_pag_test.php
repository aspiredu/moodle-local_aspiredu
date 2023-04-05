<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aspiredu;

use local_aspiredu\external\mod_forum_get_forum_discussion_posts_pag;
use mod_forum_tests_generator_trait;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/forum/locallib.php');
require_once($CFG->dirroot . '/mod/forum/tests/generator_trait.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_courses test class.
 *
 * @package    local_aspiredu
 * @copyright  2023 3ipunt
 * @author     Carlos Vázquez Olmo <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_forum_get_forum_discussion_posts_pag_test extends \externallib_advanced_testcase {
    use mod_forum_tests_generator_trait;

    /**
     * Tests initial setup.
     *
     * @covers ::mod_forum_get_forum_discussion_posts_pag
     */
    public function test_mod_forum_get_forum_discussion_posts_pag() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();
        $user1 = $datagenerator->create_user();
        $user2 = $datagenerator->create_user();
        $user3 = $datagenerator->create_user();
        $course = $datagenerator->create_course();

        $forum = $datagenerator->create_module('forum', array('course' => $course, 'grade' => 100));
        [$discussion1, $post1] = $this->helper_post_to_forum($forum, $user1);
        $this->helper_reply_to_post($post1, $user1);
        $this->helper_reply_to_post($post1, $user2);
        $this->helper_reply_to_post($post1, $user3);
        $discussion = mod_forum_get_forum_discussion_posts_pag::execute($discussion1->id);
        $discussion = \external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts_pag::execute_returns(), $discussion);
        $this->assertCount(4, $discussion['posts']);
        $this->assertEquals($user1->id,$discussion['posts'][0]['userid']);

        set_config('maxrecordsperpage', '2', 'local_aspiredu');

        $discussion = mod_forum_get_forum_discussion_posts_pag::execute($discussion1->id);
        $discussion = \external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts_pag::execute_returns(), $discussion);
        $this->assertCount(2, $discussion['posts']);

        $discussion = mod_forum_get_forum_discussion_posts_pag::execute($discussion1->id, -1,0,0,'DESC', true);
        $discussion = \external_api::clean_returnvalue(mod_forum_get_forum_discussion_posts_pag::execute_returns(), $discussion);
        $this->assertCount(2, $discussion['posts']);

    }
}
