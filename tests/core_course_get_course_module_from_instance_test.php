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
 * @package local_taxonomy
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2021, Andrew Hancox
 */

/**
 * @group local_taxonomy
 * @group opensourcelearning
 */
class core_course_get_course_module_from_instance_test extends advanced_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test() {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $feedback = $generator->create_module('feedback', array('course' => $course->id, 'name' => 'A feedback activity'));

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id);

        $this->setAdminUser();
        $result = \local_aspiredu\external\core_course_get_course_module_from_instance::execute('feedback', $feedback->id);

        $this->assertEquals('A feedback activity', $result['cm']->name);
        $this->assertEquals(1, $result['cm']->visible);

        $this->setUser($student);
        $result = \local_aspiredu\external\core_course_get_course_module_from_instance::execute('feedback', $feedback->id);
        $this->assertEquals('A feedback activity', $result['cm']->name);
        $this->assertFalse(isset($result['cm']->visible));

    }
}