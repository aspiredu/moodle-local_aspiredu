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

use external_api;
use externallib_advanced_testcase;
use local_aspiredu\external\get_plugin_info;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for get_plugin_info WS function.
 * @covers \local_aspiredu\external\get_plugin_info
 */
class get_plugin_info_test extends externallib_advanced_testcase {
    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_plugin_info() {
        $response = get_plugin_info::execute();

        external_api::clean_returnvalue(get_plugin_info::execute_returns(), $response);

        $releaseelems = explode('.', $response['release']);
        foreach ($releaseelems as $elem) {
            $this->assertIsNumeric($elem);
        }
    }
}
