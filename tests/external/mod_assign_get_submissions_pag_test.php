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

use local_aspiredu\external\mod_assign_get_submissions_pag;

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
class mod_assign_get_submissions_pag_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::mod_assign_get_submissions_pag
     */
    public function test_mod_assign_get_submissions_pag() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $datagenerator = $this->getDataGenerator();
        $course = $datagenerator->create_course();
        $assign = $datagenerator->create_module('assign', array('course' => $course, 'grade' => 100));

        // Create a student with an online text submission.
        // First attempt.
        $student = self::getDataGenerator()->create_user();
        $teacher = self::getDataGenerator()->create_user();
        $submission = new \stdClass();
        $submission->assignment = $assign->id;
        $submission->userid = $student->id;
        $submission->timecreated = time();
        $submission->timemodified = $submission->timecreated;
        $submission->timestarted = $submission->timecreated;
        $submission->status = 'draft';
        $submission->attemptnumber = 0;
        $submission->latest = 0;
        $sid1 = $DB->insert_record('assign_submission', $submission);

        // Second attempt.
        $now = time();
        $submission = new \stdClass();
        $submission->assignment = $assign->id;
        $submission->userid = $student->id;
        $submission->timecreated = $now;
        $submission->timemodified = $now;
        $submission->timestarted = $now;
        $submission->status = 'submitted';
        $submission->attemptnumber = 1;
        $submission->latest = 1;
        $sid2 = $DB->insert_record('assign_submission', $submission);
        $submission->id = $sid2;

        $onlinetextsubmission = new \stdClass();
        $onlinetextsubmission->onlinetext = "<p>online test text</p>";
        $onlinetextsubmission->onlineformat = 1;
        $onlinetextsubmission->submission = $submission->id;
        $onlinetextsubmission->assignment = $assign->id;
        $DB->insert_record('assignsubmission_onlinetext', $onlinetextsubmission);

        // Enrol the teacher in the course.
        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->setUser($teacher);

        $submissions = mod_assign_get_submissions_pag::execute($assign->id);
        $submissions = \external_api::clean_returnvalue(mod_assign_get_submissions_pag::execute_returns(), $submissions);
        $this->assertCount(2, $submissions);
        $this->assertEquals($sid1, $submissions[0]['submissionid']);
        $this->assertEquals($sid2, $submissions[1]['submissionid']);

        set_config('maxrecordsperpage', '1', 'local_aspiredu');
        $submissions = mod_assign_get_submissions_pag::execute($assign->id);
        $submissions = \external_api::clean_returnvalue(mod_assign_get_submissions_pag::execute_returns(), $submissions);
        $this->assertCount(1, $submissions);

        $submissions = mod_assign_get_submissions_pag::execute($assign->id,'',-1,0,0,'DESC');
        $submissions = \external_api::clean_returnvalue(mod_assign_get_submissions_pag::execute_returns(), $submissions);
        $this->assertCount(1, $submissions);
        $this->assertEquals($sid2, $submissions[0]['submissionid']);

        $submissions = mod_assign_get_submissions_pag::execute($assign->id,'',-1,0,0,'ASC');
        $submissions = \external_api::clean_returnvalue(mod_assign_get_submissions_pag::execute_returns(), $submissions);
        $this->assertCount(1, $submissions);
        $this->assertEquals($sid1, $submissions[0]['submissionid']);

//        echo '<pre><br><br><br>';
//        var_dump($submissions);
//        echo '</pre>';

    }
}
