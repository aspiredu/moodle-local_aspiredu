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

use context_course;
use context_user;
use core_grades_external;
use external_api;
use externallib_advanced_testcase;
use grade_item;
use grade_outcome;
use grade_scale;
use local_aspiredu\external\core_grades_get_grades;
use moodle_exception;
use stdClass;

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for core_grades_get_grades WS function.
 * @covers \local_aspiredu\external\core_grades_get_grades
 */
class core_grades_get_grades_test extends externallib_advanced_testcase {

    /**
     * Load initial test information
     *
     * @param string $assignmentname Assignment name
     * @param int $student1rawgrade Student 1 grade
     * @param int $student2rawgrade Student 2 grade
     * @return array                    Array of vars with test information
     */
    protected function load_test_data($assignmentname, $student1rawgrade, $student2rawgrade) {
        global $DB;

        // Adds a course, a teacher, 2 students, an assignment and grades for the students.
        $course = static::getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $student1 = static::getDataGenerator()->create_user();
        static::getDataGenerator()->enrol_user($student1->id, $course->id, $studentrole->id);

        $student2 = static::getDataGenerator()->create_user();
        static::getDataGenerator()->enrol_user($student2->id, $course->id, $studentrole->id);

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $teacher = static::getDataGenerator()->create_user();
        static::getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);

        $parent = static::getDataGenerator()->create_user();
        static::setUser($parent);
        $student1context = context_user::instance($student1->id);
        // Creates a new role, gives it the capability and gives $USER that role.
        $parentroleid = static::assignUserCapability('moodle/grade:viewall', $student1context->id);
        // Enrol the user in the course using the new role.
        static::getDataGenerator()->enrol_user($parent->id, $course->id, $parentroleid);

        $assignment = static::getDataGenerator()->create_module('assign', ['name' => $assignmentname, 'course' => $course->id]);
        $modcontext = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
        $assignment->cmidnumber = $modcontext->id;

        $student1grade = ['userid' => $student1->id, 'rawgrade' => $student1rawgrade];
        $student2grade = ['userid' => $student2->id, 'rawgrade' => $student2rawgrade];
        $studentgrades = [$student1->id => $student1grade, $student2->id => $student2grade];
        assign_grade_item_update($assignment, $studentgrades);

        // Insert a custom grade scale to be used by an outcome.
        $gradescale = new grade_scale();
        $gradescale->name = 'unittestscale3';
        $gradescale->courseid = $course->id;
        $gradescale->userid = 0;
        $gradescale->scale = 'Distinction, Very Good, Good, Pass, Fail';
        $gradescale->description = 'This scale is used to mark standard assignments.';
        $gradescale->insert();

        // Insert an outcome.
        $data = new stdClass();
        $data->courseid = $course->id;
        $data->fullname = 'Team work';
        $data->shortname = 'Team work';
        $data->scaleid = $gradescale->id;
        $outcome = new grade_outcome($data, false);
        $outcome->insert();

        $outcomegradeitem = new grade_item();
        $outcomegradeitem->itemname = $outcome->shortname;
        $outcomegradeitem->itemtype = 'mod';
        $outcomegradeitem->itemmodule = 'assign';
        $outcomegradeitem->iteminstance = $assignment->id;
        $outcomegradeitem->outcomeid = $outcome->id;
        $outcomegradeitem->cmid = 0;
        $outcomegradeitem->courseid = $course->id;
        $outcomegradeitem->aggregationcoef = 0;
        $outcomegradeitem->itemnumber = 1; // The activity's original grade item will be 0.
        $outcomegradeitem->gradetype = GRADE_TYPE_SCALE;
        $outcomegradeitem->scaleid = $outcome->scaleid;
        // This next two values for testing that returns parameters are correcly formatted.
        $outcomegradeitem->set_locked(true);
        $outcomegradeitem->hidden = '';
        $outcomegradeitem->insert();

        $assignmentgradeitem = grade_item::fetch(
            [
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assignment->id,
                'itemnumber' => 0,
                'courseid' => $course->id,
            ]
        );
        $outcomegradeitem->set_parent($assignmentgradeitem->categoryid);
        $outcomegradeitem->move_after_sortorder($assignmentgradeitem->sortorder);

        return [$course, $assignment, $student1, $student2, $teacher, $parent];
    }

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_grades() {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;

        $assignmentname = 'The assignment';
        $student1rawgrade = 10;
        $student2rawgrade = 20;
        list($course, $assignment, $student1, $student2, $teacher, $parent) =
            $this->load_test_data($assignmentname, $student1rawgrade, $student2rawgrade);
        $assigmentcm = get_coursemodule_from_id('assign', $assignment->cmid, 0, false, MUST_EXIST);

        // Teacher requesting a student grade for the assignment.
        static::setUser($teacher);
        $grades = core_grades_get_grades::execute(
            $course->id,
            'mod_assign',
            $assigmentcm->id,
            [$student1->id]
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertEquals($student1rawgrade, $this->get_activity_student_grade($grades, $assigmentcm->id, $student1->id));

        // Teacher requesting all the grades of student1 in a course.
        $grades = core_grades_get_grades::execute(
            $course->id,
            null,
            null,
            [$student1->id]
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertTrue(count($grades['items']) == 2);
        static::assertEquals($student1rawgrade, $this->get_activity_student_grade($grades, $assigmentcm->id, $student1->id));
        static::assertEquals($student1rawgrade, $this->get_activity_student_grade($grades, 'course', $student1->id));

        $outcome = $this->get_outcome($grades, $assigmentcm->id);
        static::assertEquals('Team work', $outcome['name']);
        static::assertEquals(0, $this->get_outcome_student_grade($grades, $assigmentcm->id, $student1->id));

        // Teacher requesting all the grades of all the students in a course.
        $grades = core_grades_get_grades::execute(
            $course->id,
            null,
            null,
            [$student1->id, $student2->id]
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertTrue(count($grades['items']) == 2);
        static::assertTrue(count($grades['items'][0]['grades']) == 2);
        static::assertTrue(count($grades['items'][1]['grades']) == 2);

        // Student requesting another student's grade for the assignment (should fail).
        static::setUser($student1);
        try {
            $grades = core_grades_get_grades::execute(
                $course->id,
                'mod_assign',
                $assigmentcm->id,
                [$student2->id]
            );
            static::fail('moodle_exception expected');
        } catch (moodle_exception $ex) {
            static::assertTrue(true);
        }

        // Parent requesting another student's grade for the assignment(should fail).
        try {
            $grades = core_grades_get_grades::execute(
                $course->id,
                'mod_assign',
                $assigmentcm->id,
                [$student2->id]
            );
            static::fail('moodle_exception expected');
        } catch (moodle_exception $ex) {
            static::assertTrue(true);
        }

        // Student requesting all other student grades for the assignment (should fail).
        try {
            $grades = core_grades_get_grades::execute(
                $course->id,
                'mod_assign',
                $assigmentcm->id,
                [$student1->id, $student2->id]
            );
            static::fail('moodle_exception expected');
        } catch (moodle_exception $ex) {
            static::assertTrue(true);
        }

        // Student requesting only grade item information (should fail).
        try {
            $grades = core_grades_get_grades::execute(
                $course->id,
                'mod_assign',
                $assigmentcm->id
            );
            static::fail('moodle_exception expected');
        } catch (moodle_exception $ex) {
            static::assertTrue(true);
        }

        // Teacher requesting student grades for a course.
        static::setUser($teacher);
        $grades = core_grades_get_grades::execute(
            $course->id,
            'mod_assign',
            $assigmentcm->id,
            [$student1->id, $student2->id]
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertEquals($student1rawgrade, $this->get_activity_student_grade($grades, $assigmentcm->id, $student1->id));
        static::assertEquals($student2rawgrade, $this->get_activity_student_grade($grades, $assigmentcm->id, $student2->id));

        // Teacher requesting grade item information.
        $grades = core_grades_get_grades::execute(
            $course->id,
            'mod_assign',
            $assigmentcm->id
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        $activity = $this->get_activity($grades, $assigmentcm->id);
        static::assertEquals($activity['name'], $assignmentname);
        static::assertEquals(0, count($activity['grades']));

        // Teacher requesting all grade items in a course.
        $grades = core_grades_get_grades::execute(
            $course->id
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertTrue(count($grades['items']) == 2);

        $activity = $this->get_activity($grades, $assigmentcm->id);
        static::assertEquals($activity['name'], $assignmentname);
        static::assertEquals(0, count($activity['grades']));

        $outcome = $this->get_outcome($grades, $assigmentcm->id);
        static::assertEquals('Team work', $outcome['name']);

        // Hide a grade item then have student request it.
        $result = core_grades_external::update_grades(
            'test',
            $course->id,
            'mod_assign',
            $assigmentcm->id,
            0,
            [],
            ['hidden' => 1]
        );
        $result = external_api::clean_returnvalue(core_grades_external::update_grades_returns(), $result);
        static::assertTrue($result == GRADE_UPDATE_OK);

        // Check it's definitely hidden.
        $grades = grade_get_grades($course->id, 'mod', 'assign', $assignment->id);
        static::assertEquals(1, $grades->items[0]->hidden);

        // Teacher should still be able to see the hidden grades.
        static::setUser($teacher);
        $grades = core_grades_get_grades::execute(
            $course->id,
            'mod_assign',
            $assigmentcm->id,
            [$student1->id]
        );
        $grades = external_api::clean_returnvalue(core_grades_get_grades::execute_returns(), $grades);
        static::assertEquals($student1rawgrade, $this->get_activity_student_grade($grades, $assigmentcm->id, $student1->id));
    }

    /**
     * Get an activity
     *
     * @param array $grades Array of grades
     * @param int $cmid Activity course module id
     * @return stdClass        Activity object
     */
    private function get_activity($grades, $cmid) {
        foreach ($grades['items'] as $item) {
            if ($item['activityid'] == $cmid) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Get a grade for an activity
     *
     * @param array $grades Array of grades
     * @param int $cmid Activity course module id
     * @param int $studentid Student it
     * @return stdClass         Activity Object
     */
    private function get_activity_student_grade($grades, $cmid, $studentid) {
        $item = $this->get_activity($grades, $cmid);
        foreach ($item['grades'] as $grade) {
            if ($grade['userid'] == $studentid) {
                return $grade['grade'];
            }
        }
        return null;
    }

    /**
     * Get an ouctome
     *
     * @param array $grades Array of grades
     * @param int $cmid Activity course module id
     * @return stdClass         Outcome object
     */
    private function get_outcome($grades, $cmid) {
        foreach ($grades['outcomes'] as $outcome) {
            if ($outcome['activityid'] == $cmid) {
                return $outcome;
            }
        }
        return null;
    }

    /**
     * Get a grade from an outcome
     *
     * @param array $grades Array of grades
     * @param int $cmid Activity course module id
     * @param int $studentid Student id
     * @return stdClass         Outcome object
     */
    private function get_outcome_student_grade($grades, $cmid, $studentid) {
        $outcome = $this->get_outcome($grades, $cmid);
        foreach ($outcome['grades'] as $grade) {
            if ($grade['userid'] == $studentid) {
                return $grade['grade'];
            }
        }
        return null;
    }

}
