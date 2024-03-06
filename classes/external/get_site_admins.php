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

global $CFG;

require_once($CFG->dirroot . '/user/externallib.php');
require_once("$CFG->dirroot/lib/externallib.php");

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_warnings;
use local_aspiredu\local\lib;

/**
 * Get site admin users external function.
 *
 * @package    local_aspiredu
 * @copyright  2022 3ipunt
 * @author     Guillermo gomez Arias <3ipunt@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_site_admins extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns a list of users who are site admins
     *
     * @return array of warnings and users
     */
    public static function execute(): array {
        global $CFG, $DB;
        // Context validation.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:configview', $context);

        return [
            'users' => lib::get_users($DB->get_records_list('user', 'id', explode(',', $CFG->siteadmins))),
            'warnings' => [],
        ];
    }

    /**
     * Describes the get_site_admins return value.
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
