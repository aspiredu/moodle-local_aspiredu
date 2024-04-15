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

use externallib_advanced_testcase;
use local_aspiredu\external\gradereport_user_get_grade_items;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function gradereport_user_get_grade_items test class.
 *
 * @package    local_aspiredu
 * @author     AspirEDU
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aspiredu\external\gradereport_user_get_grade_items::execute
 */
class gradereport_user_get_grade_items_test extends externallib_advanced_testcase {

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_grade_items_force_inclusion_range_percentage() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $student1 = $this->getDataGenerator()->create_user(['idnumber' => 'testidnumber']);
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);

        $assignment = $this->getDataGenerator()->create_module('assign', ['name' => "Test assign", 'course' => $course->id]);
        $modcontext = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
        $assignment->cmidnumber = $modcontext->id;

        $student1grade = ['userid' => $student1->id, 'rawgrade' => 80, 'idnumber' => 'testidnumber1'];
        $studentgrades = [$student1->id => $student1grade];
        assign_grade_item_update($assignment, $studentgrades);

        grade_set_setting($course->id, 'report_user_showpercentage', 0);
        grade_set_setting($course->id, 'report_user_showrange', 0);

        $this->setAdminUser();
        $studentgrades = gradereport_user_get_grade_items::execute($course->id);
        $studentgrades = \external_api::clean_returnvalue(gradereport_user_get_grade_items::execute_returns(), $studentgrades);
        // No warnings returned.
        $this->assertCount(0, $studentgrades['warnings']);

        // Module grades.
        $this->assertEquals(0.0, $studentgrades['usergrades'][0]['gradeitems'][0]['grademin']);
        $this->assertEquals(100.0, $studentgrades['usergrades'][0]['gradeitems'][0]['grademax']);
        $this->assertEquals('80.00 %', $studentgrades['usergrades'][0]['gradeitems'][0]['percentageformatted']);
    }
}
