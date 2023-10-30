<?php

namespace local_aspiredu\external;

use context_course;
use context_user;
use core_component;
use Exception;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use grade_grade;
use grade_item;
use moodle_exception;
use stdClass;

global $CFG;
require_once("$CFG->dirroot/course/externallib.php");

class core_grades_get_grades extends external_api {

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'items' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber' => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'modname' => new external_value(PARAM_RAW, 'The module name', VALUE_OPTIONAL),
                            'instance' => new external_value(PARAM_INT, 'module instance id', VALUE_OPTIONAL),
                            'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                            'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                            'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                            'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(
                                            PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                                        'hidden' => new external_value(
                                            PARAM_INT, '0 means not hidden, 1 hidden, > 1 is a date to hide until'),
                                        'overridden' => new external_value(
                                            PARAM_INT, '0 means not overridden, > 1 means overridden'),
                                        'feedback' => new external_value(
                                            PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_value(
                                            PARAM_INT, 'The format of the feedback'),
                                        'usermodified' => new external_value(
                                            PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'datesubmitted' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the student submitted the activity'),
                                        'dategraded' => new external_value(
                                            PARAM_INT, 'A timestamp indicating when the assignment was grades'),
                                        'str_grade' => new external_value(
                                            PARAM_RAW, 'A string representation of the grade'),
                                        'str_long_grade' => new external_value(
                                            PARAM_RAW, 'A nicely formatted string representation of the grade'),
                                        'str_feedback' => new external_value(
                                            PARAM_RAW, 'A formatted string representation of the feedback from the grader'),
                                    ]
                                )
                            ),
                        ]
                    )
                ),
                'outcomes' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber' => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                        'locked' => new external_value(
                                            PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                                        'hidden' => new external_value(
                                            PARAM_INT, '0 means not hidden, 1 hidden, > 1 is a date to hide until'),
                                        'feedback' => new external_value(
                                            PARAM_RAW, 'Feedback from the grader'),
                                        'feedbackformat' => new external_value(
                                            PARAM_INT, 'The feedback format'),
                                        'usermodified' => new external_value(
                                            PARAM_INT, 'The ID of the last user to modify this student grade'),
                                        'str_grade' => new external_value(
                                            PARAM_RAW, 'A string representation of the grade'),
                                        'str_feedback' => new external_value(
                                            PARAM_RAW, 'A formatted string representation of the feedback from the grader'),
                                    ]
                                )
                            ),
                        ]
                    ), 'An array of outcomes associated with the grade items', VALUE_OPTIONAL
                )
            ]
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'component' => new external_value(
                    PARAM_COMPONENT, 'A component, for example mod_forum or mod_quiz', VALUE_DEFAULT, ''),
                'activityid' => new external_value(PARAM_INT, 'The activity ID', VALUE_DEFAULT, null),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'),
                    'An array of user IDs, leave empty to just retrieve grade item information', VALUE_DEFAULT, []
                )
            ]
        );
    }

    /**
     * Retrieve grade items and, optionally, student grades
     *
     * @param int $courseid Course id
     * @param string $component Component name
     * @param int $activityid Activity id
     * @param array $userids Array of user ids
     * @return array                Array of grades
     * @since Moodle 2.7
     */
    public static function execute($courseid, $component = null, $activityid = null, $userids = []) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        $params = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'component' => $component, 'activityid' => $activityid, 'userids' => $userids]);

        $coursecontext = context_course::instance($params['courseid']);

        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid', 'webservice', '', $exceptionparam);
        }

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        $access = false;
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            // Can view all user's grades in this course.
            $access = true;

        } else if ($course->showgrades && count($params['userids']) == 1) {
            // Course showgrades == students/parents can access grades.

            if ($params['userids'][0] == $USER->id && has_capability('moodle/grade:view', $coursecontext)) {
                // Student can view their own grades in this course.
                $access = true;

            } else if (has_capability('moodle/grade:viewall', context_user::instance($params['userids'][0]))) {
                // User can view the grades of this user. Parent most probably.
                $access = true;
            }
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error');
        }

        $itemtype = null;
        $itemmodule = null;
        if (!empty($params['component'])) {
            list($itemtype, $itemmodule) = core_component::normalize_component($params['component']);
        }

        $cm = null;
        if (!empty($itemmodule) && !empty($activityid)) {
            if (!$cm = get_coursemodule_from_id($itemmodule, $activityid)) {
                throw new moodle_exception('invalidcoursemodule');
            }
        }

        $cminstanceid = null;
        if (!empty($cm)) {
            $cminstanceid = $cm->instance;
        }

        $grades = grade_get_grades($params['courseid'], $itemtype, $itemmodule, $cminstanceid, $params['userids']);

        $acitivityinstances = null;
        if (empty($cm)) {
            // If we're dealing with multiple activites load all the module info.
            $modinfo = get_fast_modinfo($params['courseid']);
            $acitivityinstances = $modinfo->get_instances();
        }

        foreach ($grades->items as $gradeitem) {
            if (!empty($cm)) {
                // If they only requested one activity we will already have the cm.
                $modulecm = $cm;
            } else if (!empty($gradeitem->itemmodule)) {
                $modulecm = $acitivityinstances[$gradeitem->itemmodule][$gradeitem->iteminstance];
            } else {
                // Course grade item.
                continue;
            }

            // Make student feedback ready for output.
            foreach ($gradeitem->grades as $studentgrade) {
                if (!empty($studentgrade->feedback)) {
                    list($studentgrade->feedback, $studentgrade->feedbackformat) =
                        external_format_text($studentgrade->feedback, $studentgrade->feedbackformat,
                            $modulecm->id, $params['component'], 'feedback');
                }
            }
        }

        // Convert from objects to arrays so all web service clients are supported.
        // While we're doing that we also remove grades the current user can't see due to hiding.
        $gradesarray = [];
        $canviewhidden = has_capability('moodle/grade:viewhidden', context_course::instance($params['courseid']));

        $gradesarray['items'] = [];

        foreach ($grades->items as $gradeitem) {
            // Avoid to process manual or category items (they are going to fail).
            if ($gradeitem->itemtype != 'course' && $gradeitem->itemtype != 'mod') {
                continue;
            }
            // Switch the stdClass instance for a grade item instance so we can call is_hidden() and use the ID.
            $gradeiteminstance = self::core_grades_get_grade_item(
                $course->id, $gradeitem->itemtype, $gradeitem->itemmodule, $gradeitem->iteminstance, $gradeitem->itemnumber);

            if (!$canviewhidden && $gradeiteminstance->is_hidden()) {
                continue;
            }

            // Format mixed bool/integer parameters.
            $gradeitem->hidden = (!$gradeitem->hidden) ? 0 : $gradeitem->hidden;
            $gradeitem->locked = (!$gradeitem->locked) ? 0 : $gradeitem->locked;

            $gradeitemarray = (array)$gradeitem;
            $gradeitemarray['grades'] = [];

            if (!empty($gradeitem->grades)) {
                foreach ($gradeitem->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradegradeinstance = grade_grade::fetch(
                            [
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            ]
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance) && $gradegradeinstance->is_hidden()) {
                            continue;
                        }
                    }

                    // Format mixed bool/integer parameters.
                    $studentgrade->hidden = (!$studentgrade->hidden) ? 0 : $studentgrade->hidden;
                    $studentgrade->locked = (!$studentgrade->locked) ? 0 : $studentgrade->locked;
                    $studentgrade->overridden = (!$studentgrade->overridden) ? 0 : $studentgrade->overridden;

                    $gradeitemarray['grades'][$studentid] = (array)$studentgrade;
                    // Add the student ID as some WS clients can't access the array key.
                    $gradeitemarray['grades'][$studentid]['userid'] = $studentid;
                }
            }

            // If they requested grades for multiple activities load the cm object now.
            $modulecm = $cm;
            if (empty($modulecm) && !empty($gradeiteminstance->itemmodule)) {
                $modulecm = $acitivityinstances[$gradeiteminstance->itemmodule][$gradeiteminstance->iteminstance];
            }
            if ($gradeiteminstance->itemtype == 'course') {
                $gradesarray['items']['course'] = $gradeitemarray;
                $gradesarray['items']['course']['activityid'] = 'course';
            } else {
                $gradesarray['items'][$modulecm->id] = $gradeitemarray;
                // Add the activity ID as some WS clients can't access the array key.
                $gradesarray['items'][$modulecm->id]['activityid'] = $modulecm->id;
                $gradesarray['items'][$modulecm->id]['instance'] = $modulecm->instance;
                $gradesarray['items'][$modulecm->id]['modname'] = $modulecm->modname;
            }
        }

        $gradesarray['outcomes'] = [];
        foreach ($grades->outcomes as $outcome) {
            $modulecm = $cm;
            if (empty($modulecm)) {
                $modulecm = $acitivityinstances[$outcome->itemmodule][$outcome->iteminstance];
            }

            // Format mixed bool/integer parameters.
            $outcome->hidden = (!$outcome->hidden) ? 0 : $outcome->hidden;
            $outcome->locked = (!$outcome->locked) ? 0 : $outcome->locked;

            $gradesarray['outcomes'][$modulecm->id] = (array)$outcome;
            $gradesarray['outcomes'][$modulecm->id]['activityid'] = $modulecm->id;

            $gradesarray['outcomes'][$modulecm->id]['grades'] = [];
            if (!empty($outcome->grades)) {
                foreach ($outcome->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradeiteminstance = self::core_grades_get_grade_item(
                            $course->id, $outcome->itemtype, $outcome->itemmodule, $outcome->iteminstance, $outcome->itemnumber);
                        $gradegradeinstance = grade_grade::fetch(
                            [
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            ]
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance) && $gradegradeinstance->is_hidden()) {
                            continue;
                        }
                    }

                    // Format mixed bool/integer parameters.
                    $studentgrade->hidden = (!$studentgrade->hidden) ? 0 : $studentgrade->hidden;
                    $studentgrade->locked = (!$studentgrade->locked) ? 0 : $studentgrade->locked;

                    $gradesarray['outcomes'][$modulecm->id]['grades'][$studentid] = (array)$studentgrade;

                    // Add the student ID into the grade structure as some WS clients can't access the key.
                    $gradesarray['outcomes'][$modulecm->id]['grades'][$studentid]['userid'] = $studentid;
                }
            }
        }

        return $gradesarray;
    }


    /**
     * Get a grade item
     * @param int $courseid Course id
     * @param string $itemtype Item type
     * @param string $itemmodule Item module
     * @param int $iteminstance Item instance
     * @param int $itemnumber Item number
     * @return grade_item           A gradeItem instance
     */
    private static function core_grades_get_grade_item($courseid, $itemtype, $itemmodule = null, $iteminstance = null,
                                                       $itemnumber = null) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        if ($itemtype == 'course') {
            $gradeiteminstance = grade_item::fetch(['courseid' => $courseid, 'itemtype' => $itemtype]);
        } else {
            $gradeiteminstance = grade_item::fetch(
                ['courseid' => $courseid, 'itemtype' => $itemtype,
                    'itemmodule' => $itemmodule, 'iteminstance' => $iteminstance, 'itemnumber' => $itemnumber]);
        }
        return $gradeiteminstance;
    }
}