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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once(dirname(__FILE__) . '/../../config.php');

    global $CFG, $USER, $SITE, $PAGE, $OUTPUT;

    require_once($CFG->dirroot.'/local/aspiredu/futurelib.php');
    require_once($CFG->libdir.'/completionlib.php');
    require_once($CFG->dirroot.'/mod/lti/lib.php');
    require_once($CFG->dirroot.'/mod/lti/locallib.php');


    $id = required_param('id', PARAM_INT);
    $product = required_param('product', PARAM_ALPHA);
    $instance = required_param('instance', PARAM_INT);

    if ($id == SITEID) {
        $course = get_site();
        $context = context_system::instance();
        require_login();
    } else {
        $course = get_course($id);
        $context = context_course::instance($course->id);
        require_login($course);
    }

    if ($product == 'dd') {
        require_capability('local/aspiredu:viewdropoutdetective', $context);
        $launchurl = get_config('local_aspiredu', 'dropoutdetectiveurl');
    } else {
        require_capability('local/aspiredu:viewinstructorinsight', $context);
        $launchurl = get_config('local_aspiredu', 'instructorinsighturl');
    }

    $lti = new stdClass();
    $lti->instructorchoicesendname = 0;
    $lti->instructorchoicesendemailaddr = 0;
    $lti->instructorcustomparameters = 0;
    $lti->instructorchoiceacceptgrades = 0;
    $lti->instructorchoiceallowroster = 0;

    $config = lti_get_type_type_config($instance);
    if ($config->lti_ltiversion === LTI_VERSION_1P3) {
        if (!isset($SESSION->lti_initiatelogin_status)) {
            echo lti_initiate_login($course->id, $id, $lti, $config);
            exit;
        } else {
            unset($SESSION->lti_initiatelogin_status);
        }
    }

    lti_launch_tool($lti);

