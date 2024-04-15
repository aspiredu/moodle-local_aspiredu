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

defined('MOODLE_INTERNAL') || die();

use local_aspiredu\external\get_users_by_capabilities;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_users_by_capabilities test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later/**
 * @covers \local_aspiredu\external\get_users_by_capabilities
 */
class get_users_by_capabilities_test extends \externallib_advanced_testcase {

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_users_by_capabilities() {
        $this->resetAfterTest();

        $datagenerator = $this->getDataGenerator();
        $user = $datagenerator->create_user();
        $course = $datagenerator->create_course();
        $role = $datagenerator->create_role();

        $context = \context_course::instance($course->id);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $role, $context->id);
        role_assign($role, $user->id, $context->id);

        $users = get_users_by_capabilities::execute(['moodle/user:viewdetails']);
        $users = \external_api::clean_returnvalue(get_users_by_capabilities::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(1, $users['users']);

        // Assign a new capability to the same user.
        assign_capability('moodle/course:manageactivities', CAP_ALLOW, $role, $context->id);

        $users = get_users_by_capabilities::execute(['moodle/course:manageactivities', 'moodle/user:viewdetails']);
        $users = \external_api::clean_returnvalue(get_users_by_capabilities::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(1, $users['users']);

        // Assign a new capability to new user.
        $user2 = $datagenerator->create_user();
        role_assign($role, $user2->id, $context->id);

        $users = get_users_by_capabilities::execute(['moodle/user:viewdetails']);
        $users = \external_api::clean_returnvalue(get_users_by_capabilities::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(2, $users['users']);
        accesslib_clear_all_caches_for_unit_testing();
    }
}
