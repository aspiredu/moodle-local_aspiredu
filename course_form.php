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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/lib/formslib.php');

class course_form extends moodleform {

    // Define the form.
    public function definition () {
        global $USER, $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('header', 'settingsheader', get_string('coursesettings', 'local_aspiredu'));

        $mform->addElement('date_selector', 'coursestartdate', get_string('coursestartdate', 'local_aspiredu'),
                            array('optional' => true));
        $mform->setDefault('coursestartdate', 0);

        $mform->addElement('date_selector', 'courseenddate', get_string('courseenddate', 'local_aspiredu'),
                            array('optional' => true));
        $mform->setDefault('courseenddate', 0);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        global $COURSE, $DB, $CFG;

        $errors = parent::validation($data, $files);

        if (!empty($data['courseenddate']) and $data['courseenddate'] < $data['coursestartdate']) {
            $errors['courseenddate'] = get_string('courseenddaterror', 'local_aspiredu');
        }

        return $errors;
    }

}