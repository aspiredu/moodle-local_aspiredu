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

namespace local_aspiredu\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/externallib.php');
require_once("$CFG->dirroot/lib/externallib.php");

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;

/**
 * Get users by role external function.
 *
 * @package    local_aspiredu
 * @copyright  2024 AspirEDU
 * @author     Tim Schilling <tim@aspiredu.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_plugin_info extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([]);
    }

    /**
     * Returns a collection of information about the local_aspiredu plugin.
     *
     * @return array of warnings and info
     */
    public static function execute(): array {
        $warnings = [];

        $pluginmanager = \core_plugin_manager::instance();
        $plugin = $pluginmanager->get_plugin_info('local_aspiredu');

        return [
            'release' => $plugin->release,
            'warnings' => $warnings,
        ];
    }

    /**
     * Describes the get_plugin_info return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'release' => new external_value(PARAM_TEXT, 'Plugin release'),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
