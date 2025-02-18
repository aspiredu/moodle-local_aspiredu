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

namespace local_aspiredu\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/externallib.php');


use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_user_external;
use local_aspiredu\local\lib;

/**
 * Web serivce class.
 */
class get_role_users extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters (
            [
                'roleshortname' => new external_value(PARAM_TEXT),
                'contextlevel' => new external_value(PARAM_INT),
                'contextinstanceid' => new external_value(PARAM_INT),
            ]
        );
    }

    /**
     * Light wrapper for get_role_users
     *
     * @return array of users and warnings
     */
    public static function execute(string $roleshortname, int $contextlevel, int $contextinstanceid): array {
        global $DB;
        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:configview', $context);

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'roleshortname' => $roleshortname,
            'contextlevel' => $contextlevel,
            'contextinstanceid' => $contextinstanceid,
        ]);

        $roleid = $DB->get_field('role', 'id', ['shortname' => $params['roleshortname']]);

        $contextid = $DB->get_field(
            'context',
            'id',
            ['contextlevel' => $params['contextlevel'], 'instanceid' => $params['contextinstanceid']]
        );

        $context = context::instance_by_id($contextid);

        $userids = get_role_users($roleid, $context, false, 'u.id', 'u.id');

        return [
            'users' => lib::get_users($DB->get_records_list('user', 'id', array_keys($userids))),
            'warnings' => [],
        ];
    }

    /**
     * Describes the get_role_users return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'users' => new external_multiple_structure(core_user_external::user_description()),
                'warnings' => new external_warnings(),
            ]
        );
    }
}
