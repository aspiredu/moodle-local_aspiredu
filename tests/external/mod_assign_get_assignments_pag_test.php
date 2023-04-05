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

use core_reportbuilder\local\aggregation\count;
use local_aspiredu\external\mod_assign_get_assignments_pag;

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
class mod_assign_get_assignments_pag_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::mod_assign_get_assignments_pag
     */
    public function test_mod_assign_get_assignments_pag() {
        global $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));

        $assignments = mod_assign_get_assignments_pag::execute($course->id);
        $assignments = \external_api::clean_returnvalue(mod_assign_get_assignments_pag::execute_returns(), $assignments);

        $this->assertCount(4, $assignments);
        $this->assertEquals('Assignment 1', $assignments[0]['name']);
        $this->assertEquals('Assignment 2', $assignments[1]['name']);
        $this->assertEquals('Assignment 3', $assignments[2]['name']);
        $this->assertEquals('Assignment 4', $assignments[3]['name']);

        $assignments = mod_assign_get_assignments_pag::execute($course->id,-1,0,0,'desc');
        $assignments = \external_api::clean_returnvalue(mod_assign_get_assignments_pag::execute_returns(), $assignments);
        $this->assertEquals('Assignment 4', $assignments[0]['name']);
        $this->assertEquals('Assignment 3', $assignments[1]['name']);
        $this->assertEquals('Assignment 2', $assignments[2]['name']);
        $this->assertEquals('Assignment 1', $assignments[3]['name']);




    }
}
