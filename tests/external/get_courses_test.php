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

use local_aspiredu\external\get_courses;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_courses test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courses_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::get_courses
     */
    public function test_get_courses() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();
        $course1 = $datagenerator->create_course();
        $course2 = $datagenerator->create_course();
        $course3 = $datagenerator->create_course();
        $course4 = $datagenerator->create_course();

        $courses = get_courses::execute(['ids' => [$course1->id, $course2->id, $course3->id, $course4->id]]);
        $courses = \external_api::clean_returnvalue(get_courses::execute_returns(), $courses);
        $courses[0] = $courses;
        $this->assertCount(4, $courses);

        // Test pagination.
        $courses = get_courses::execute(['ids' => [$course1->id, $course2->id, $course3->id, $course4->id]], 1, 2);
        $courses = \external_api::clean_returnvalue(get_courses::execute_returns(), $courses);
        $courses[0] = $courses;
        $this->assertCount(2, $courses);
    }
}
