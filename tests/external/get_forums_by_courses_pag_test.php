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

use local_aspiredu\external\get_forums_by_courses_pag;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_courses test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Carlos Vázquez Olmo <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_forums_by_courses_pag_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::get_forums_by_courses_pag
     * @throws \invalid_response_exception
     */
    public function test_get_forums_by_courses_pag() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();

        $course = $datagenerator->create_course();
        $datagenerator->create_module('forum', ['name' =>'Forum Test 1', 'course' => $course, 'grade' => 100]);
        $datagenerator->create_module('forum', ['name' =>'Forum Test 2', 'course' => $course, 'grade' => 100]);
        $datagenerator->create_module('forum', ['name' =>'Forum Test 3', 'course' => $course, 'grade' => 100]);
        $datagenerator->create_module('forum', ['name' =>'Forum Test 4', 'course' => $course, 'grade' => 100]);

        $forums = get_forums_by_courses_pag::execute([$course->id]);
        $forums = \external_api::clean_returnvalue(get_forums_by_courses_pag::execute_returns(), $forums);

        $this->assertCount(4, $forums);
        $this->assertEquals('Forum Test 1', $forums[0]['name']);
        $this->assertEquals('Forum Test 4', $forums[3]['name']);

        set_config('maxrecordsperpage', '2', 'local_aspiredu');
        $forums = get_forums_by_courses_pag::execute([$course->id],-1,0,0,'DESC');
        $forums = \external_api::clean_returnvalue(get_forums_by_courses_pag::execute_returns(), $forums);
        $this->assertEquals('Forum Test 4', $forums[0]['name']);
        $this->assertEquals('Forum Test 3', $forums[1]['name']);

        $forums = get_forums_by_courses_pag::execute([$course->id],2,0,0,'DESC');
        $forums = \external_api::clean_returnvalue(get_forums_by_courses_pag::execute_returns(), $forums);
        $this->assertEquals('Forum Test 2', $forums[0]['name']);
        $this->assertEquals('Forum Test 1', $forums[1]['name']);

    }
}
