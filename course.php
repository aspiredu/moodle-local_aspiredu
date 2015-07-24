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

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/local/aspiredu/futurelib.php');
require_once($CFG->dirroot.'/local/aspiredu/locallib.php');
require_once($CFG->dirroot.'/local/aspiredu/course_form.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
$context = context_course::instance($course->id);

$PAGE->set_url('/local/aspiredu/course.php', array('id' => $id));
$returnurl = new moodle_url('/course/view.php', array('id' => $id));

require_login($course);
require_capability('moodle/course:update', $context);

$strheading = get_string('coursesettings', 'local_aspiredu');
$PAGE->set_context($context);

$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

$editform = new course_form(null);

if ($coursesettings = get_config('local_aspiredu', 'course' . $course->id)) {
    $coursesettings = json_decode($coursesettings);
    $editform->set_data($coursesettings);
} else {
    $coursesettings = new stdClass();
    $coursesettings->id = $course->id;
    $editform->set_data($coursesettings);
}

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {
    // Clean data.
    unset($data->submitbutton);
    set_config('course' . $course->id, json_encode($data), 'local_aspiredu');
    redirect($returnurl, get_string('changessaved'), 3);
}

echo $OUTPUT->header();
$editform->display();
echo $OUTPUT->footer();