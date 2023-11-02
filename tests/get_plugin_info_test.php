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
 * The external function get_plugin_info test class.
 *
 * @package    local_aspiredu
 * @copyright  2022 AspirEDU
 * @author     Tim Schilling <tim@aspiredu.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;

use local_aspiredu\external\get_plugin_info;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class get_plugin_info_test extends externallib_advanced_testcase {
    public function test_get_plugin_info() {
        $response = get_plugin_info::execute();

        external_api::clean_returnvalue(get_plugin_info::execute_returns(), $response);

        $releaseelems = explode('.', $response['release']);
        foreach ($releaseelems as $elem) {
            $this->assertIsNumeric($elem);
        }
    }
}
