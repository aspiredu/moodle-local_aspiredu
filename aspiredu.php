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

$id = required_param('id', PARAM_INT);
$product = required_param('product', PARAM_ALPHA);

$course = get_course($id);
$context = context_course::instance($course->id);
$instance = get_config('local_aspiredu', 'instance');

if(!(bool)$instance){
    throw new coding_exception('Must create a instance');
}

require_login($course);

$url = new moodle_url('/local/aspiredu/aspiredu.php', array('id' => $id, 'product' => $product));
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');

if ($product == 'dd') {
    require_capability('local/aspiredu:viewdropoutdetective', $context);
    $dropoutdetective = new moodle_url('/local/aspiredu/aspiredu.php',['id' => $id, 'product' => 'ii']);
    $link = html_writer::link($dropoutdetective, 'Go to '.get_string('instructorinsight', 'local_aspiredu'));
    $pagetitle = get_string('dropoutdetective', 'local_aspiredu');
} else {
    require_capability('local/aspiredu:viewinstructorinsight', $context);
    $instructorinsighturl = new moodle_url('/local/aspiredu/aspiredu.php',['id' => $id, 'product' => 'dd']);
    $link = html_writer::link($instructorinsighturl, 'Go to '.get_string('dropoutdetective', 'local_aspiredu'));

    $pagetitle = get_string('instructorinsight', 'local_aspiredu');
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();

echo $link.'
<br><iframe id="contentframe" style="border: none" height="800px" width="100%" 
src="lti.php?id='.$id.'&product='.$product.'&instance='.$instance.'">
        </iframe>';

echo $OUTPUT->footer();


