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

require_once($CFG->dirroot.'/user/externallib.php');
require_once("$CFG->dirroot/lib/externallib.php");

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use local_aspiredu\local\lib;

/**
 * Get users by capability external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_users_by_capabilities extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'capabilities' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Capability name'), 'Array of capabilities name', VALUE_DEFAULT, []
                ),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Returns a list of users given a list of capabilities.
     *
     * @param array $capabilities
     * @param int|null $page current page
     * @param int|null $perpage items per page
     * @return array
     */
    public static function execute(array $capabilities, ?int $page = -1, ?int $perpage = 0): array {

        $warnings = [];

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'capabilities' => $capabilities,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $users = lib::get_users_by_capabilities($params['capabilities'], $params['page'], $params['perpage']);

        return [
            'users' => $users,
            'warnings' => $warnings,
        ];
    }

    /**
     * Describes the get_users_by_capabilities return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'users' => new external_multiple_structure(\core_user_external::user_description()),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
