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
use local_aspiredu\external\get_grades;
use local_aspiredu\external\get_grades_pag;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_grades test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Carlos Vázquez <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_grades_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::get_grades
     */
    public function test_get_grades() {
        global $CFG;
        $this->resetAfterTest();

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assignrecord = $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $assignrecord1 = $this->getDataGenerator()->create_module('quiz', array('course' => $course, 'grade' => 100));
        $assignrecord2 = $this->getDataGenerator()->create_module('forum', array('course' => $course, 'grade' => 100));
        $assignrecord3 = $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $assignrecord4 = $this->getDataGenerator()->create_module('quiz', array('course' => $course, 'grade' => 100));
        $assignrecord5 = $this->getDataGenerator()->create_module('assign', array('course' => $course, 'grade' => 100));
        $cm = get_coursemodule_from_instance('assign', $assignrecord->id);
        $assigncontext = \context_module::instance($cm->id);
        $assign = new \assign($assigncontext, $cm, $course);

        for ($i = 0; $i <= 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 5);

            $usergrade = $assign->get_user_grade($user->id, true);
            $usergrade->grade = $i;
            $assign->update_grade($usergrade);
            $giparams = array('itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assignrecord->id,
                'courseid' => $course->id, 'itemnumber' => 0);
            $gi = \grade_item::fetch($giparams);
            $gg = \grade_grade::fetch(array('userid' => $user->id, 'itemid' => $gi->id));

        }
        $grades = get_grades_pag::execute($course->id);
        $grades = \external_api::clean_returnvalue(get_grades_pag::execute_returns(), $grades);

        $this->assertCount(1, $grades);
        $activities = reset($grades);
        foreach ($activities as $activity) {
            if ($activity['name'] === 'Assignment 1') {
                $this->assertCount(11, $activity['grades']);
            }
        }
        $this->assertCount(6, $activities);
        set_config('maxrecordsperpage', '3', 'local_aspiredu');
        $grades = get_grades_pag::execute($course->id);
        $grades = \external_api::clean_returnvalue(get_grades_pag::execute_returns(), $grades);
        $activities = reset($grades);
        $this->assertCount(3, $activities);

    }
}
