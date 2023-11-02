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
 * @author     AspirEDU
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aspiredu\local;

use context_system;

class lib {
    const ASPIREDU_DISABLED = 0;
    const ASPIREDU_ADMINACCCOURSEINSTCOURSE = 1;
    const ASPIREDU_ADMINACCCINSTCOURSE = 2;
    const ASPIREDU_ADMINCOURSEINSTCOURSE = 3;
    const ASPIREDU_ADMINACCCOURSE = 4;
    const ASPIREDU_ADMINACC = 5;
    const ASPIREDU_INSTCOURSE = 6;

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
        if ($context->contextlevel == CONTEXT_SYSTEM or $COURSE->id == SITEID) {
            if ($isadmin && $settings != self::ASPIREDU_ADMINCOURSEINSTCOURSE) {
                return true;
            }
        }
        return false;
    }
}


