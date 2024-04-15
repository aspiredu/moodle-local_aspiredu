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
 * @copyright  2024 AspirEDU
 * @author     AspirEDU
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aspiredu;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use context_course;
use context_system;
use external_api;
use externallib_advanced_testcase;
use local_aspiredu\external\core_course_get_courses_paginated;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for core_course_get_courses_paginated WS function.
 * @covers \local_aspiredu\external\core_course_get_courses_paginated
 */
class core_course_get_courses_paginated_test extends externallib_advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        require_once("$CFG->dirroot/lib/externallib.php");

        $this->resetAfterTest();
    }

    /**
     * Test calling the function.
     * @runInSeparateProcess
     */
    public function test_get_courses() {
        global $DB;

        $generatedcourses = [];
        $coursedata['idnumber'] = 'idnumbercourse1';
        // Adding tags here to check that format_string is applied.
        $coursedata['fullname'] = '<b>Course 1 for PHPunit test</b>';
        $coursedata['shortname'] = '<b>Course 1 for PHPunit test</b>';
        $coursedata['summary'] = 'Course 1 description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course1 = self::getDataGenerator()->create_course($coursedata);

        $fieldcategory = self::getDataGenerator()->create_custom_field_category(
            ['name' => 'Other fields']);

        $customfield = ['shortname' => 'test', 'name' => 'Custom field', 'type' => 'text',
            'categoryid' => $fieldcategory->get('id'), ];
        $field = self::getDataGenerator()->create_custom_field($customfield);

        $customfieldvalue = ['shortname' => 'test', 'value' => 'Test value'];

        $generatedcourses[$course1->id] = $course1;
        $course2 = self::getDataGenerator()->create_course();
        $generatedcourses[$course2->id] = $course2;
        $course3 = self::getDataGenerator()->create_course(['format' => 'topics']);
        $generatedcourses[$course3->id] = $course3;
        $course4 = self::getDataGenerator()->create_course(['customfields' => [$customfieldvalue]]);
        $generatedcourses[$course4->id] = $course4;

        // Set the required capabilities by the external function.
        $context = context_system::instance();
        $roleid = static::assignUserCapability('moodle/course:view', $context->id);
        static::assignUserCapability('moodle/course:update',
            context_course::instance($course1->id)->id, $roleid);
        static::assignUserCapability('moodle/course:update',
            context_course::instance($course2->id)->id, $roleid);
        static::assignUserCapability('moodle/course:update',
            context_course::instance($course3->id)->id, $roleid);
        static::assignUserCapability('moodle/course:update',
            context_course::instance($course4->id)->id, $roleid);

        $courses = core_course_get_courses_paginated::execute('id', 'DESC', 0, 3);

        // We need to execute the return values cleaning process to simulate the web service server.
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        // Check we retrieve the good total number of courses.
        static::assertEquals(3, count($courses));

        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course['id']);
            $dbcourse = $generatedcourses[$course['id']];
            static::assertEquals($course['idnumber'], $dbcourse->idnumber);
            static::assertEquals($course['fullname'], external_format_string($dbcourse->fullname, $coursecontext->id));
            static::assertEquals($course['displayname'], external_format_string(get_course_display_name_for_list($dbcourse),
                $coursecontext->id));
            // Summary was converted to the HTML format.
            static::assertEquals($course['summary'], format_text($dbcourse->summary, FORMAT_MOODLE, ['para' => false]));
            static::assertEquals(FORMAT_HTML, $course['summaryformat']);
            static::assertEquals($course['shortname'], external_format_string($dbcourse->shortname, $coursecontext->id));
            static::assertEquals($course['categoryid'], $dbcourse->category);
            static::assertEquals($course['format'], $dbcourse->format);
            static::assertEquals($course['showgrades'], $dbcourse->showgrades);
            static::assertEquals($course['newsitems'], $dbcourse->newsitems);
            static::assertEquals($course['startdate'], $dbcourse->startdate);
            static::assertEquals($course['enddate'], $dbcourse->enddate);
            static::assertEquals($course['numsections'], course_get_format($dbcourse)->get_last_section_number());
            static::assertEquals($course['maxbytes'], $dbcourse->maxbytes);
            static::assertEquals($course['showreports'], $dbcourse->showreports);
            static::assertEquals($course['visible'], $dbcourse->visible);
            static::assertEquals($course['hiddensections'], $dbcourse->hiddensections);
            static::assertEquals($course['groupmode'], $dbcourse->groupmode);
            static::assertEquals($course['groupmodeforce'], $dbcourse->groupmodeforce);
            static::assertEquals($course['defaultgroupingid'], $dbcourse->defaultgroupingid);
            static::assertEquals($course['completionnotify'], $dbcourse->completionnotify);
            static::assertEquals($course['lang'], $dbcourse->lang);
            static::assertEquals($course['forcetheme'], $dbcourse->theme);
            static::assertEquals($course['enablecompletion'], $dbcourse->enablecompletion);
            if ($dbcourse->format === 'topics') {
                static::assertEquals($course['courseformatoptions'], [
                    ['name' => 'hiddensections', 'value' => $dbcourse->hiddensections],
                    ['name' => 'coursedisplay', 'value' => $dbcourse->coursedisplay],
                ]);
            }

            // Assert custom field that we previously added to test course 4.
            if ($dbcourse->id == $course4->id) {
                static::assertEquals([
                    'shortname' => $customfield['shortname'],
                    'name' => $customfield['name'],
                    'type' => $customfield['type'],
                    'value' => $customfieldvalue['value'],
                    'valueraw' => $customfieldvalue['value'],
                ], $course['customfields'][0]);
            }
        }

        // Get all courses in the DB.
        $courses = core_course_get_courses_paginated::execute();

        // We need to execute the return values cleaning process to simulate the web service server.
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        static::assertEquals($DB->count_records('course') - 1, count($courses)); // Subtract one for the site home course.
    }

    /**
     * Test calling the function on courses with custom fields.
     * @runInSeparateProcess
     */
    public function test_get_courses_customfields(): void {
        $this->resetAfterTest();
        static::setAdminUser();

        $fieldcategory = static::getDataGenerator()->create_custom_field_category([]);
        $datefield = static::getDataGenerator()->create_custom_field([
            'categoryid' => $fieldcategory->get('id'),
            'shortname' => 'mydate',
            'name' => 'My date',
            'type' => 'date',
        ]);

        static::getDataGenerator()->create_course(['customfields' => [
            [
                'shortname' => $datefield->get('shortname'),
                'value' => 1580389200, // 30/01/2020 13:00 GMT.
            ],
        ], ]);

        $courses = external_api::clean_returnvalue(
            core_course_get_courses_paginated::execute_returns(),
            core_course_get_courses_paginated::execute('id', 'ASC', 0, 1)
        )['courses'];

        static::assertCount(1, $courses);
        $course = reset($courses);

        static::assertArrayHasKey('customfields', $course);
        static::assertCount(1, $course['customfields']);

        // Assert the received custom field, "value" containing a human-readable version and "valueraw" the unmodified version.
        static::assertEquals([
            'name' => $datefield->get('name'),
            'shortname' => $datefield->get('shortname'),
            'type' => $datefield->get('type'),
            'value' => userdate(1580389200),
            'valueraw' => 1580389200,
        ], reset($course['customfields']));
    }

    /**
     * Test calling the function where the user does not have required capability on any courses.
     * @runInSeparateProcess
     */
    public function test_get_courses_without_capability() {
        $this->resetAfterTest();

        $course1 = static::getDataGenerator()->create_course();
        static::setUser(static::getDataGenerator()->create_user());

        // No permissions are required to get the site course.
        $courses = core_course_get_courses_paginated::execute('id', 'DESC', 0, 1);
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        static::assertEquals(0, count($courses));
    }
}
