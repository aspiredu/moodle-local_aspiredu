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
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use context_course;
use external_api;
use externallib_advanced_testcase;
use local_aspiredu\external\report_log_get_log_records;

/**
 * Tests for report_log_get_log_records WS function.
 * @covers \local_aspiredu\external\report_log_get_log_records
 */
class report_log_get_log_records_test extends externallib_advanced_testcase {
    /**
     * Basic setup for these tests.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
    }

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_courses() {
        global $DB;

        static::setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $user = static::getDataGenerator()->create_user();
        $coursecontext = context_course::instance($course->id);
        role_assign($studentrole->id, $user->id, $coursecontext->id);

        $response = report_log_get_log_records::execute($course->id);

        external_api::clean_returnvalue(report_log_get_log_records::execute_returns(), $response);

        $lastlog = array_shift($response['logs']);
        static::assertEquals('\core\event\role_assigned', $lastlog['eventname']);
        static::assertEquals($user->id, $lastlog['relateduserid']);
    }
}
