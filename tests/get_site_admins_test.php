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

use local_aspiredu\external\get_site_admins;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_site_admins test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_aspiredu\external\get_site_admins
 */
class get_site_admins_test extends \externallib_advanced_testcase {

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_site_admins() {
        $this->resetAfterTest();
        static::setAdminUser();

        $datagenerator = $this->getDataGenerator();
        $user = $datagenerator->create_user();
        $user2 = $datagenerator->create_user();

        $users = get_site_admins::execute();
        $users = \external_api::clean_returnvalue(get_site_admins::execute_returns(), $users);

        $this->assertCount(0, $users['warnings']);
        $this->assertCount(1, $users['users']);
        $this->assertEquals('admin@example.com', $users['users'][0]['email']);
    }
}
