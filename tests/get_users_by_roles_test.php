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

use local_aspiredu\external\get_users_by_roles;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_users_by_roles test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aspiredu\external\get_users_by_roles
 */
class get_users_by_roles_test extends \externallib_advanced_testcase {

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_users_by_capabilities() {
        $this->resetAfterTest();

        $datagenerator = $this->getDataGenerator();
        $user = $datagenerator->create_user();
        $course = $datagenerator->create_course();

        // Assign role to the user in a course context.
        $context = \context_course::instance($course->id);
        $roleid = $datagenerator->create_role();
        $datagenerator->role_assign($roleid, $user->id, $context->id);

        $users = get_users_by_roles::execute([$roleid]);
        $users = \external_api::clean_returnvalue(get_users_by_roles::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(1, $users['users']);

        // Assign a new role to the same user.
        $roleid2 = $datagenerator->create_role();
        $datagenerator->role_assign($roleid2, $user->id, $context->id);

        $users = get_users_by_roles::execute([$roleid, $roleid2]);
        $users = \external_api::clean_returnvalue(get_users_by_roles::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(1, $users['users']);

        // Assign new role to a new user.
        $user2 = $datagenerator->create_user();
        $datagenerator->role_assign($roleid2, $user2->id, $context->id);

        $users = get_users_by_roles::execute([$roleid, $roleid2]);
        $users = \external_api::clean_returnvalue(get_users_by_roles::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(2, $users['users']);
    }
}
