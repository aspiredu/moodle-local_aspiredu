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

defined('MOODLE_INTERNAL') || die;
const LOCAL_ASPIREDU_DISABLED = 0;
const LOCAL_ASPIREDU_ADMINACCCOURSEINSTCOURSE = 1;
const LOCAL_ASPIREDU_ADMINACCCINSTCOURSE = 2;
const LOCAL_ASPIREDU_ADMINCOURSEINSTCOURSE = 3;
const LOCAL_ASPIREDU_ADMINACCCOURSE = 4;
const LOCAL_ASPIREDU_ADMINACC = 5;
const LOCAL_ASPIREDU_INSTCOURSE = 6;


function local_aspiredu_check_links_visibility_permission($context, $settings) {
    global $COURSE;

    $contextsystem = context_system::instance();
    $isadmin = has_capability('moodle/site:config', $contextsystem) ||
        has_capability('local/aspiredu:viewdropoutdetective', $contextsystem) ||
        has_capability('local/aspiredu:viewinstructorinsight', $contextsystem);

    if (!$settings) {
        return false;
    }

    if ($isadmin && $settings == LOCAL_ASPIREDU_INSTCOURSE) {
        // Admins links disabled.
        return false;
    }

    // Course permissions.
    if ($context->contextlevel >= CONTEXT_COURSE && $COURSE->id != SITEID) {
        if ($isadmin && $settings != LOCAL_ASPIREDU_ADMINACC && $settings != LOCAL_ASPIREDU_ADMINACCCINSTCOURSE) {
            return true;
        }
        if (!$isadmin && $settings != LOCAL_ASPIREDU_ADMINACCCOURSE && $settings != LOCAL_ASPIREDU_ADMINACC) {
            return true;
        }
    }

    // Site permissions.
    if ($context->contextlevel == CONTEXT_SYSTEM or $COURSE->id == SITEID) {
        if ($isadmin && $settings != LOCAL_ASPIREDU_ADMINCOURSEINSTCOURSE) {
            return true;
        }
    }
    return false;
}

/**
 * Display the AspirEdu settings in the course settings block
 *
 * @param settings_navigation $nav The settings navigatin object
 * @param context $context Course context
 */
function local_aspiredu_extend_settings_navigation(settings_navigation $nav, context $context) {
    global $COURSE;

    $dropoutdetectivelinks = get_config('local_aspiredu', 'dropoutdetectivelinks');
    $instructorinsightlinks = get_config('local_aspiredu', 'instructorinsightlinks');
    $reportsnode = null;

    if (local_aspiredu_check_links_visibility_permission($context, $dropoutdetectivelinks)) {
        $displayed = false;
        $canview = has_capability('local/aspiredu:viewdropoutdetective', $context);
        $branch = ($nav->get('courseadmin')) ? $nav->get('courseadmin') : $nav->get('frontpage');

        if ($branch && $canview) {
            $subbranch = ($branch->get('coursereports')) ? $branch->get('coursereports') : $branch->get('frontpagereports');
            if ($subbranch) {
                $url = new moodle_url('/local/aspiredu/aspiredu.php', ['id' => $COURSE->id, 'product' => 'dd']);
                $subbranch->add(
                    get_string('dropoutdetective', 'local_aspiredu'),
                    $url,
                    $nav::TYPE_CONTAINER,
                    null,
                    'aspiredudd' . $context->instanceid,
                    new pix_icon('i/stats', '')
                );
                $displayed = true;
            }
        }

        if ($canview && !$displayed) {
            $reportsnode = $nav->add(get_string('reports'), null, $nav::NODETYPE_BRANCH, null, 'aspiredureports');
            $url = new moodle_url('/local/aspiredu/aspiredu.php', ['id' => $COURSE->id, 'product' => 'dd']);
            $reportsnode->add(
                get_string('dropoutdetective', 'local_aspiredu'),
                $url,
                $nav::TYPE_CONTAINER,
                null,
                'aspiredudd' . $context->instanceid,
                new pix_icon('i/stats', '')
            );
        }
    }

    if (local_aspiredu_check_links_visibility_permission($context, $instructorinsightlinks)) {
        $displayed = false;
        $canview = has_capability('local/aspiredu:viewinstructorinsight', $context);
        $branch = ($nav->get('courseadmin')) ? $nav->get('courseadmin') : $nav->get('frontpage');

        if ($branch && $canview) {
            $subbranch = ($branch->get('coursereports')) ? $branch->get('coursereports') : $branch->get('frontpagereports');
            if ($subbranch) {
                $url = new moodle_url('/local/aspiredu/aspiredu.php', ['id' => $COURSE->id, 'product' => 'ii']);
                $subbranch->add(
                    get_string('instructorinsight', 'local_aspiredu'),
                    $url,
                    $nav::TYPE_CONTAINER,
                    null,
                    'aspireduii' . $context->instanceid,
                    new pix_icon('i/stats', '')
                );
                $displayed = true;
            }
        }

        if ($canview && !$displayed) {
            if (!$reportsnode) {
                $reportsnode = $nav->add(get_string('reports'), null, $nav::NODETYPE_BRANCH, null, 'aspiredureports');
            }
            $url = new moodle_url('/local/aspiredu/aspiredu.php', ['id' => $COURSE->id, 'product' => 'ii']);
            $reportsnode->add(
                get_string('instructorinsight', 'local_aspiredu'),
                $url,
                $nav::TYPE_CONTAINER,
                null,
                'aspireduii' . $context->instanceid,
                new pix_icon('i/stats', '')
            );
        }
    }
}
