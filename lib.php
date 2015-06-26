<?php

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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Display the AspirEdu settings in the course settings block
 *
 * @param  settings_navigation $nav     The settings navigatin object
 * @param  stdclass            $context Course context
 */
function local_aspiredu_extends_settings_navigation(settings_navigation $nav, $context) {
    if ($context->contextlevel >= CONTEXT_COURSE and ($branch = $nav->get('courseadmin'))
            and has_capability('moodle/course:update', $context)) {

        $url = new moodle_url('/local/aspiredu/course.php', array('id' => $context->instanceid));
        $branch->add(get_string('coursesettings', 'local_aspiredu'), $url, $nav::TYPE_CONTAINER, null, 'aspiredu'.$context->instanceid);
    }

    if ($context->contextlevel >= CONTEXT_COURSE and ($branch = $nav->get('courseadmin'))
            and has_capability('local/aspiredu:viewdropoutdetective', $context)) {

        $url = new moodle_url('/local/aspiredu/aspiredu.php', array('id' => $context->instanceid, 'product' => 'dd'));
        $branch->add(get_string('dropoutdetective', 'local_aspiredu'), $url, $nav::TYPE_CONTAINER, null, 'aspiredudd'.$context->instanceid);
    }
    if ($context->contextlevel >= CONTEXT_COURSE and ($branch = $nav->get('courseadmin'))
            and has_capability('local/aspiredu:viewinstructorinsight', $context)) {

        $url = new moodle_url('/local/aspiredu/aspiredu.php', array('id' => $context->instanceid, 'product' => 'ii'));
        $branch->add(get_string('instructorinsight', 'local_aspiredu'), $url, $nav::TYPE_CONTAINER, null, 'aspireduii'.$context->instanceid);
    }
}