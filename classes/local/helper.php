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

namespace local_aspiredu\local;

/**
 * Class helper.
 *
 * @package     local_aspiredu
 * @copyright   2022 3&Punt
 * @author      Guillermo Gomez Arias <guillermo@treipunt.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

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

            $sql = 'SELECT distinct ra.userid
                      FROM {role_assignments} ra
                      JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                      JOIN {capabilities} c ON c.name = rc.capability
                     WHERE c.id IN (' . $capabilitiesids . ')';

            $usersbycapabilities = $DB->get_recordset_sql($sql, [], $page * $perpage, $perpage);
            return self::get_users_external($usersbycapabilities);
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

        $sql = "SELECT distinct ra.userid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.id in ($roleids)";

        $usersbyroles = $DB->get_recordset_sql($sql, [], $page * $perpage, $perpage);
        return self::get_users_external($usersbyroles);
    }

    /**
     * Call get_users external function.
     *
     * @param \moodle_recordset $useridsset
     * @return array
     */
    private static function get_users_external(\moodle_recordset $useridsset): array {
        $users = [];
        foreach ($useridsset as $useridindex => $userid) {
            $searchparams = [['key' => 'id', 'value' => $useridindex], ];
            $user = \core_user_external::get_users($searchparams)['users'];

            if (isset($user[0])) {
                $users[] = $user[0];
            }
        }
        $useridsset->close();
        return $users;
    }

    /**
     * Get list of courses paginated.
     *
     * @param array $options
     * @param int $page
     * @param int $perpage
     * @return mixed
     */
    public static function get_courses(array $options = [], int $page = -1, int $perpage = 0): array {
        global $DB;

        $courseids = '';
        if (!empty($options)) {
            foreach ($options['ids'] as $courseid) {
                $courseids .= $courseid . ',';
            }

            // Remove last comma.
            if (strlen($courseids) > 1) {
                $courseids = substr($courseids, 0, -1);
            }
        }
        $filter = '';
        if ($courseids !== '') {
            $filter = " WHERE c.id IN ($courseids)";
        }

        $sql = "SELECT c.id
                  FROM {course} c
                 $filter";

        $courses = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);
        return self::get_courses_external($courses);
    }

    /**
     * Call get_courses external function.
     *
     * @param array $courses
     * @return array
     */
    private static function get_courses_external(array $coursesset) {
        $courseslist = [];

        foreach ($coursesset as $courseindex => $course) {
            $courseslist['ids'][] = $courseindex;
        }

        if (!empty($courseslist)) {
            // Call external function.
            return \core_course_external::get_courses($courseslist);
        }

        return [];
    }
}
