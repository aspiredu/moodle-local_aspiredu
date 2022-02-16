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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page

    $settings = new admin_settingpage('local_aspiredu', new lang_string('pluginname', 'local_aspiredu'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_aspiredu/dropoutdetectiveurl',
        get_string('dropoutdetectiveurl', 'local_aspiredu'), '', '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_aspiredu/instructorinsighturl',
        get_string('instructorinsighturl', 'local_aspiredu'), '', '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_aspiredu/key',
        get_string('key', 'local_aspiredu'), '', '', PARAM_RAW_TRIMMED));

    $settings->add(new admin_setting_configtext('local_aspiredu/secret',
        get_string('secret', 'local_aspiredu'), '', '', PARAM_RAW_TRIMMED));

    $options = array(
        0 => get_string('disabled', 'local_aspiredu'),
        1 => get_string('adminacccourseinstcourse', 'local_aspiredu'),
        2 => get_string('adminacccinstcourse', 'local_aspiredu'),
        3 => get_string('admincourseinstcourse', 'local_aspiredu'),
        4 => get_string('adminacccourse', 'local_aspiredu'),
        5 => get_string('adminacc', 'local_aspiredu'),
        6 => get_string('instcourse', 'local_aspiredu'),
    );
    $default = 1;

    $settings->add(new admin_setting_configselect('local_aspiredu/dropoutdetectivelinks',
        get_string('dropoutdetectivelinks', 'local_aspiredu'), '', $default, $options));

    $settings->add(new admin_setting_configselect('local_aspiredu/instructorinsightlinks',
        get_string('instructorinsightlinks', 'local_aspiredu'), '', $default, $options));


    $default = 1;
    $options = array(
        0 => get_string('no'),
        1 => get_string('yes')
    );
    $settings->add(new admin_setting_configselect('local_aspiredu/showcoursesettings',
        get_string('showcoursesettings', 'local_aspiredu'), '', $default, $options));

}