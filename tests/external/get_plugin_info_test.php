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

use local_aspiredu\external\get_plugin_info;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The external function get_plugin_info test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 AspirEDU
 * @author     Tim Schilling <tim@aspiredu.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_plugin_info_test extends \externallib_advanced_testcase {

    /**
     * Tests initial setup.
     *
     * @covers ::get_plugin_info
     */
    public function test_get_plugin_info() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $response = \external_api::clean_returnvalue(
            get_plugin_info::execute_returns(),
            get_plugin_info::execute()
        );
        $info = $response["info"];
        $this->assertEquals("4.0.0", $info["release"]);
    }
}
