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
require_once($CFG->dirroot.'/local/aspiredu/futurelib.php');

use moodle\mod\lti as lti;

$id = required_param('id', PARAM_INT);
$product = required_param('product', PARAM_ALPHA);

$course = get_course($id);
$context = context_course::instance($course->id);

require_login($course);

$url = new moodle_url('/local/aspiredu/aspiredu.php', array('id' => $id, 'product' => $product));
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');

if ($product == 'dd') {
    require_capability('local/aspiredu:viewdropoutdetective', $context);
    $pagetitle = get_string('dropoutdetective', 'local_aspiredu');
} else {
    require_capability('local/aspiredu:viewinstructorinsight', $context);
    $pagetitle = get_string('instructorinsight', 'local_aspiredu');
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

echo '<iframe id="contentframe" style="border: none" height="800px" width="100%" src="lti.php?id='.$id.'&product='.$product.'">
        </iframe>';

echo $OUTPUT->footer();


