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


global $CFG;

use local_aspiredu\external\core_course_get_courses_paginated;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class core_course_get_courses_paginated_test extends externallib_advanced_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test get_courses
     */
    public function test_get_courses() {
        global $DB;

        $this->resetAfterTest(true);

        $generatedcourses = array();
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
            'categoryid' => $fieldcategory->get('id')];
        $field = self::getDataGenerator()->create_custom_field($customfield);

        $customfieldvalue = ['shortname' => 'test', 'value' => 'Test value'];

        $generatedcourses[$course1->id] = $course1;
        $course2 = self::getDataGenerator()->create_course();
        $generatedcourses[$course2->id] = $course2;
        $course3 = self::getDataGenerator()->create_course(array('format' => 'topics'));
        $generatedcourses[$course3->id] = $course3;
        $course4 = self::getDataGenerator()->create_course(['customfields' => [$customfieldvalue]]);
        $generatedcourses[$course4->id] = $course4;

        // Set the required capabilities by the external function.
        $context = context_system::instance();
        $roleid = $this->assignUserCapability('moodle/course:view', $context->id);
        $this->assignUserCapability('moodle/course:update',
            context_course::instance($course1->id)->id, $roleid);
        $this->assignUserCapability('moodle/course:update',
            context_course::instance($course2->id)->id, $roleid);
        $this->assignUserCapability('moodle/course:update',
            context_course::instance($course3->id)->id, $roleid);
        $this->assignUserCapability('moodle/course:update',
            context_course::instance($course4->id)->id, $roleid);

        $courses = core_course_get_courses_paginated::execute('id', 'DESC', 0, 3);

        // We need to execute the return values cleaning process to simulate the web service server.
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        // Check we retrieve the good total number of courses.
        $this->assertEquals(3, count($courses));

        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course['id']);
            $dbcourse = $generatedcourses[$course['id']];
            $this->assertEquals($course['idnumber'], $dbcourse->idnumber);
            $this->assertEquals($course['fullname'], external_format_string($dbcourse->fullname, $coursecontext->id));
            $this->assertEquals($course['displayname'], external_format_string(get_course_display_name_for_list($dbcourse),
                $coursecontext->id));
            // Summary was converted to the HTML format.
            $this->assertEquals($course['summary'], format_text($dbcourse->summary, FORMAT_MOODLE, array('para' => false)));
            $this->assertEquals($course['summaryformat'], FORMAT_HTML);
            $this->assertEquals($course['shortname'], external_format_string($dbcourse->shortname, $coursecontext->id));
            $this->assertEquals($course['categoryid'], $dbcourse->category);
            $this->assertEquals($course['format'], $dbcourse->format);
            $this->assertEquals($course['showgrades'], $dbcourse->showgrades);
            $this->assertEquals($course['newsitems'], $dbcourse->newsitems);
            $this->assertEquals($course['startdate'], $dbcourse->startdate);
            $this->assertEquals($course['enddate'], $dbcourse->enddate);
            $this->assertEquals($course['numsections'], course_get_format($dbcourse)->get_last_section_number());
            $this->assertEquals($course['maxbytes'], $dbcourse->maxbytes);
            $this->assertEquals($course['showreports'], $dbcourse->showreports);
            $this->assertEquals($course['visible'], $dbcourse->visible);
            $this->assertEquals($course['hiddensections'], $dbcourse->hiddensections);
            $this->assertEquals($course['groupmode'], $dbcourse->groupmode);
            $this->assertEquals($course['groupmodeforce'], $dbcourse->groupmodeforce);
            $this->assertEquals($course['defaultgroupingid'], $dbcourse->defaultgroupingid);
            $this->assertEquals($course['completionnotify'], $dbcourse->completionnotify);
            $this->assertEquals($course['lang'], $dbcourse->lang);
            $this->assertEquals($course['forcetheme'], $dbcourse->theme);
            $this->assertEquals($course['enablecompletion'], $dbcourse->enablecompletion);
            if ($dbcourse->format === 'topics') {
                $this->assertEquals($course['courseformatoptions'], array(
                    array('name' => 'hiddensections', 'value' => $dbcourse->hiddensections),
                    array('name' => 'coursedisplay', 'value' => $dbcourse->coursedisplay),
                ));
            }

            // Assert custom field that we previously added to test course 4.
            if ($dbcourse->id == $course4->id) {
                $this->assertEquals([
                    'shortname' => $customfield['shortname'],
                    'name' => $customfield['name'],
                    'type' => $customfield['type'],
                    'value' => $customfieldvalue['value'],
                    'valueraw' => $customfieldvalue['value'],
                ], $course['customfields'][0]);
            }
        }

        // Get all courses in the DB
        $courses = core_course_get_courses_paginated::execute();

        // We need to execute the return values cleaning process to simulate the web service server.
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        $this->assertEquals($DB->count_records('course') - 1, count($courses)); // Subtract one for the site home course.
    }

    /**
     * Test retrieving courses returns custom field data
     */
    public function test_get_courses_customfields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $fieldcategory = $this->getDataGenerator()->create_custom_field_category([]);
        $datefield = $this->getDataGenerator()->create_custom_field([
            'categoryid' => $fieldcategory->get('id'),
            'shortname' => 'mydate',
            'name' => 'My date',
            'type' => 'date',
        ]);

        $this->getDataGenerator()->create_course(['customfields' => [
            [
                'shortname' => $datefield->get('shortname'),
                'value' => 1580389200, // 30/01/2020 13:00 GMT.
            ],
        ]]);

        $courses = external_api::clean_returnvalue(
            core_course_get_courses_paginated::execute_returns(),
            core_course_get_courses_paginated::execute('id', 'ASC', 0,1)
        )['courses'];

        $this->assertCount(1, $courses);
        $course = reset($courses);

        $this->assertArrayHasKey('customfields', $course);
        $this->assertCount(1, $course['customfields']);

        // Assert the received custom field, "value" containing a human-readable version and "valueraw" the unmodified version.
        $this->assertEquals([
            'name' => $datefield->get('name'),
            'shortname' => $datefield->get('shortname'),
            'type' => $datefield->get('type'),
            'value' => userdate(1580389200),
            'valueraw' => 1580389200,
        ], reset($course['customfields']));
    }

    /**
     * Test get_courses without capability
     */
    public function test_get_courses_without_capability() {
        $this->resetAfterTest(true);

        $course1 = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_user());

        // No permissions are required to get the site course.
        $courses = core_course_get_courses_paginated::execute('id', 'DESC', 0, 1);
        $courses = external_api::clean_returnvalue(core_course_get_courses_paginated::execute_returns(), $courses)['courses'];

        $this->assertEquals(0, count($courses));
    }
}