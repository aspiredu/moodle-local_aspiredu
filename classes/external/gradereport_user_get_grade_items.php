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
require_once("$CFG->dirroot/lib/externallib.php");

use cache;
use external_api;
use external_function_parameters;
use external_single_structure;
use gradereport_user\external\user;

/**
 * Web serivce class.
 */
class gradereport_user_get_grade_items extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return user::get_grade_items_parameters();
    }

    /**
     * Forces some report settings for the scope of this request and then calls
     * \gradereport_user\external\user::get_grade_items
     *
     * @return array of warnings and info
     */
    public static function execute(int $courseid, int $userid = 0, int $groupid = 0): array {
        $cache = cache::make('core', 'gradesetting');
        $gradesetting = $cache->get($courseid) ?: [];
        $gradesetting['report_user_showpercentage'] = true;
        $gradesetting['report_user_showrange'] = true;
        $cache->set($courseid, $gradesetting);

        $retval = user::get_grade_items($courseid, $userid, $groupid);

        unset($gradesetting['report_user_showpercentage']);
        unset($gradesetting['report_user_showrange']);
        $cache->set($courseid, $gradesetting);

        return $retval;
    }

    /**
     * Describes the get_plugin_info return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return user::get_grade_items_returns();
    }
}
