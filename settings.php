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

// Needs $hassiteconfig or there is error on login page.
if ($hassiteconfig) {

    $settings = new admin_settingpage('local_aspiredu', new lang_string('pluginname', 'local_aspiredu'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_aspiredu/dropoutdetectiveurl',
        new lang_string('dropoutdetectiveurl', 'local_aspiredu'), '', '', PARAM_URL));

    $settings->add(new admin_setting_configtext('local_aspiredu/instructorinsighturl',
        new lang_string('instructorinsighturl', 'local_aspiredu'), '', '', PARAM_URL));

    $settings->add(new admin_setting_configtext('local_aspiredu/key',
        new lang_string('key', 'local_aspiredu'), '', '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_aspiredu/secret',
        new lang_string('secret', 'local_aspiredu'), '', '', PARAM_TEXT));

    $options = array(
        0 => new lang_string('disabled', 'local_aspiredu'),
        1 => new lang_string('adminacccourseinstcourse', 'local_aspiredu'),
        2 => new lang_string('adminacccinstcourse', 'local_aspiredu'),
        3 => new lang_string('admincourseinstcourse', 'local_aspiredu'),
        4 => new lang_string('adminacccourse', 'local_aspiredu'),
        5 => new lang_string('adminacc', 'local_aspiredu'),
        6 => new lang_string('instcourse', 'local_aspiredu'),
    );
    $default = 1;

    $settings->add(new admin_setting_configselect('local_aspiredu/dropoutdetectivelinks',
        new lang_string('dropoutdetectivelinks', 'local_aspiredu'), '', $default, $options));

    $settings->add(new admin_setting_configselect('local_aspiredu/instructorinsightlinks',
        new lang_string('instructorinsightlinks', 'local_aspiredu'), '', $default, $options));


    $default = 1;
    $options = array(
        0 => new lang_string('no'),
        1 => new lang_string('yes')
    );
    $settings->add(new admin_setting_configselect('local_aspiredu/showcoursesettings',
        new lang_string('showcoursesettings', 'local_aspiredu'), '', $default, $options));

    $settings->add(new admin_setting_configtext('local_aspiredu/instance',
        get_string('instance', 'local_aspiredu'),
        get_string('instancedesc', 'local_aspiredu'), 0, PARAM_TEXT));

}
