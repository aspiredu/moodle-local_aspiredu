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

global $CFG;

use external_api;
use externallib_advanced_testcase;
use local_aspiredu\external\core_grades_get_course_grades;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * Tests for core_grades_get_course_grades WS function.
 * @covers \local_aspiredu\external\core_grades_get_course_grades
 */
class core_grades_get_course_grades_test extends externallib_advanced_testcase {
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_courses() {
        $course = self::getDataGenerator()->create_course();
        $student1 = static::getDataGenerator()->create_and_enrol($course);
        $student2 = static::getDataGenerator()->create_and_enrol($course);
        $teacher = static::getDataGenerator()->create_and_enrol($course, 'teacher');

        static::setUser($teacher);

        grade_regrade_final_grades($course->id);

        $response = core_grades_get_course_grades::execute($course->id, [$student1->id, $student2->id]);

        external_api::clean_returnvalue(core_grades_get_course_grades::execute_returns(), $response);

        static::assertCount(2, $response->grades);
    }
}
