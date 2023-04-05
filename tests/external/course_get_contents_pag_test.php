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

use local_aspiredu\external\course_get_contents_pag;

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
class course_get_contents_pag_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::course_get_contents_pag
     */
    public function test_course_get_contents_pag() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();

        $course = $datagenerator->create_course();
        $datagenerator->create_module('assign', array('course' => $course, 'grade' => 100));
        $datagenerator->create_module('quiz', array('course' => $course, 'grade' => 100));
        $datagenerator->create_module('forum', array('course' => $course, 'grade' => 100));
        $datagenerator->create_module('forum', array('course' => $course, 'grade' => 100));
        $datagenerator->create_module('quiz', array('course' => $course, 'grade' => 100));
        $datagenerator->create_module('assign', array('course' => $course, 'grade' => 100));

        $contents = course_get_contents_pag::execute($course->id);
        $contents = \external_api::clean_returnvalue(course_get_contents_pag::execute_returns(), $contents);
        $this->assertCount(6, $contents);
        $this->assertEquals('Quiz 1', $contents[0]['name']);
        $this->assertEquals('Assignment 1', $contents[5]['name']);

        $contents = course_get_contents_pag::execute($course->id,-1,0,0, 'desc');
        $contents = \external_api::clean_returnvalue(course_get_contents_pag::execute_returns(), $contents);
        $this->assertCount(6, $contents);
        $this->assertEquals('Quiz 1', $contents[5]['name']);
        $this->assertEquals('Assignment 1', $contents[0]['name']);
        set_config('maxrecordsperpage', '2', 'local_aspiredu');

        $contents = course_get_contents_pag::execute($course->id);
        $contents = \external_api::clean_returnvalue(course_get_contents_pag::execute_returns(), $contents);
        $this->assertCount(2, $contents);
    }
}
