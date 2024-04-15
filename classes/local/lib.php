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

namespace local_aspiredu\local;

use context_system;
use moodle_exception;

/**
 * Library functions.
 */
class lib {
    /**
     * Disabled
     */
    const ASPIREDU_DISABLED = 0;
    /**
     * Administrators with account plus course and Instructors with course.
     */
    const ASPIREDU_ADMINACCCOURSEINSTCOURSE = 1;
    /**
     * Administrators with account and Instructors with course,
     */
    const ASPIREDU_ADMINACCCINSTCOURSE = 2;
    /**
     * Administrators with course and instructors with course.
     */
    const ASPIREDU_ADMINCOURSEINSTCOURSE = 3;
    /**
     * Only Administrators with account plus course.
     */
    const ASPIREDU_ADMINACCCOURSE = 4;
    /**
     * 'Only Administrators with account.
     */
    const ASPIREDU_ADMINACC = 5;
    /**
     * Only instructors with course.
     */
    const ASPIREDU_INSTCOURSE = 6;

    /**
     * Check visibility of links.
     *
     * @param $context
     * @param $settings
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function links_visibility_permission($context, $settings) {
        global $COURSE;

        $contextsystem = context_system::instance();
        $isadmin = has_capability('moodle/site:config', $contextsystem) ||
            has_capability('local/aspiredu:viewdropoutdetective', $contextsystem) ||
            has_capability('local/aspiredu:viewinstructorinsight', $contextsystem);

        if (!$settings) {
            return false;
        }

        if ($isadmin && $settings == self::ASPIREDU_INSTCOURSE) {
            // Admins links disabled.
            return false;
        }

        // Course permissions.
        if ($context->contextlevel >= CONTEXT_COURSE && $COURSE->id != SITEID) {
            if ($isadmin && $settings != self::ASPIREDU_ADMINACC && $settings != self::ASPIREDU_ADMINACCCINSTCOURSE) {
                return true;
            }
            if (!$isadmin && $settings != self::ASPIREDU_ADMINACCCOURSE && $settings != self::ASPIREDU_ADMINACC) {
                return true;
            }
        }

        // Site permissions.
        if ($context->contextlevel == CONTEXT_SYSTEM || $COURSE->id == SITEID) {
            if ($isadmin && $settings != self::ASPIREDU_ADMINCOURSEINSTCOURSE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get users by capabilities paginated.
     *
     * @param array $capabilities
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_users_by_capabilities(array $capabilities, int $page = -1, int $perpage = 0): array {
        global $DB;

        $capabilitiesids = '';
        foreach ($capabilities as $capability) {
            $capabilitycheck = $DB->get_record('capabilities', ['name' => $capability], 'id');
            if ($capabilitycheck) {
                $capabilitiesids .= $capabilitycheck->id . ',';
            }
        }

        // Remove last comma.
        if (strlen($capabilitiesids) > 1) {
            $capabilitiesids = substr($capabilitiesids, 0, -1);

            $sql = 'SELECT * FROM {user} WHERE id IN (SELECT ra.userid
                      FROM {role_assignments} ra
                      JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                      JOIN {capabilities} c ON c.name = rc.capability
                     WHERE c.id IN (' . $capabilitiesids . ')
                     GROUP BY ra.userid
                     )';

            return self::get_users($DB->get_recordset_sql($sql, [], $page * $perpage, $perpage));
        }

        return [];
    }

    /**
     * Get users by role paginated.
     *
     * @param array $roleids
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function get_users_by_roles(array $roleids, int $page = -1, int $perpage = 0): array {
        global $DB;

        $roleids = implode(',', $roleids);

        $sql = "SELECT * FROM {user} WHERE id IN (SELECT ra.userid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.id in ($roleids)
                 GROUP BY ra.userid
                 )";

        return self::get_users($DB->get_recordset_sql($sql, [], $page * $perpage, $perpage));
    }

    /**
     * Retrieve matching user.
     *
     * @throws moodle_exception
     * @param array $userids the user ids to fetch.
     * @return array An array of arrays containing user profiles.
     * @since Moodle 2.5
     */
    public static function get_users($users): array {
        global $CFG;

        require_once($CFG->dirroot . "/user/lib.php");

        // Finally retrieve each users information.
        $returnedusers = [];
        foreach ($users as $user) {
            $details = user_get_user_details_courses($user);

            if (empty($details)) {
                continue;
            }

            $returnedusers[] = (object)$details;
        }

        return $returnedusers;
    }
}
