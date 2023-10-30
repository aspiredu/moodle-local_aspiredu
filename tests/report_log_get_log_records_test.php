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


global $CFG;

use local_aspiredu\external\report_log_get_log_records;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class report_log_get_log_records_test extends externallib_advanced_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        $this->resetAfterTest();

        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
    }

    /**
     * Test get_courses
     */
    public function test_get_courses() {
        global $DB;

        $this->resetAfterTest(true);

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $user = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($course->id);
        role_assign($studentrole->id, $user->id, $coursecontext->id);

        $response = report_log_get_log_records::execute($course->id);

        external_api::clean_returnvalue(report_log_get_log_records::execute_returns(), $response);

        $lastlog = array_shift($response['logs']);
        $this->assertEquals('\core\event\role_assigned', $lastlog['eventname']);
        $this->assertEquals($user->id, $lastlog['relateduserid']);
    }
}