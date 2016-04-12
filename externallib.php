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

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/course/externallib.php");
require_once("$CFG->dirroot/local/aspiredu/locallib.php");
require_once("$CFG->dirroot/local/aspiredu/futurelib.php");

class local_aspiredu_external extends external_api {


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'component' => new external_value(
                    PARAM_COMPONENT, 'A component, for example mod_forum or mod_quiz', VALUE_DEFAULT, ''),
                'activityid' => new external_value(PARAM_INT, 'The activity ID', VALUE_DEFAULT, null),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'),
                    'An array of user IDs, leave empty to just retrieve grade item information', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Retrieve grade items and, optionally, student grades
     *
     * @param  int $courseid        Course id
     * @param  string $component    Component name
     * @param  int $activityid      Activity id
     * @param  array  $userids      Array of user ids
     * @return array                Array of grades
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades($courseid, $component = null, $activityid = null, $userids = array()) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir  . "/gradelib.php");
        require_once($CFG->dirroot . "/local/aspiredu/locallib.php");

        $params = self::validate_parameters(self::core_grades_get_grades_parameters(),
            array('courseid' => $courseid, 'component' => $component, 'activityid' => $activityid, 'userids' => $userids));

        $coursecontext = context_course::instance($params['courseid']);

        try {
            self::validate_context($coursecontext);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }

        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);

        $access = false;
        if (has_capability('moodle/grade:viewall', $coursecontext)) {
            // Can view all user's grades in this course.
            $access = true;

        } else if ($course->showgrades && count($params['userids']) == 1) {
            // Course showgrades == students/parents can access grades.

            if ($params['userids'][0] == $USER->id and has_capability('moodle/grade:view', $coursecontext)) {
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
            list($itemtype, $itemmodule) = normalize_component($params['component']);
        }

        $cm = null;
        if (!empty($itemmodule) && !empty($activityid)) {
            if (! $cm = get_coursemodule_from_id($itemmodule, $activityid)) {
                throw new moodle_exception('invalidcoursemodule');
            }
        }

        $cminstanceid = null;
        if (!empty($cm)) {
            $cminstanceid = $cm->instance;
        }

        $grades = local_aspiredu_grade_get_grades($params['courseid'], $itemtype, $itemmodule, $cminstanceid, $params['userids']);

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
                    list($studentgrade->feedback, $categoryinfo->feedbackformat) =
                        external_format_text($studentgrade->feedback, $studentgrade->feedbackformat,
                        $modulecm->id, $params['component'], 'feedback', null);
                }
            }
        }

        // Convert from objects to arrays so all web service clients are supported.
        // While we're doing that we also remove grades the current user can't see due to hiding.
        $gradesarray = array();
        $canviewhidden = has_capability('moodle/grade:viewhidden', context_course::instance($params['courseid']));

        $gradesarray['items'] = array();

        foreach ($grades->items as $gradeitem) {
            // Avoid to process manual or category items (they are going to fail).
            if ($gradeitem->itemtype != 'course' and $gradeitem->itemtype != 'mod') {
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
            $gradeitemarray['grades'] = array();

            if (!empty($gradeitem->grades)) {
                foreach ($gradeitem->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradegradeinstance = grade_grade::fetch(
                            array(
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            )
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance ) && $gradegradeinstance->is_hidden()) {
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

        $gradesarray['outcomes'] = array();
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

            $gradesarray['outcomes'][$modulecm->id]['grades'] = array();
            if (!empty($outcome->grades)) {
                foreach ($outcome->grades as $studentid => $studentgrade) {
                    if (!$canviewhidden) {
                        // Need to load the grade_grade object to check visibility.
                        $gradeiteminstance = self::core_grades_get_grade_item(
                            $course->id, $outcome->itemtype, $outcome->itemmodule, $outcome->iteminstance, $outcome->itemnumber);
                        $gradegradeinstance = grade_grade::fetch(
                            array(
                                'userid' => $studentid,
                                'itemid' => $gradeiteminstance->id
                            )
                        );
                        // The grade grade may be legitimately missing if the student has no grade.
                        if (!empty($gradegradeinstance ) && $gradegradeinstance->is_hidden()) {
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
     * @param  int $courseid        Course id
     * @param  string $itemtype     Item type
     * @param  string $itemmodule   Item module
     * @param  int $iteminstance    Item instance
     * @param  int $itemnumber      Item number
     * @return grade_item           A grade_item instance
     */
    private static function core_grades_get_grade_item($courseid, $itemtype, $itemmodule = null, $iteminstance = null,
                                                        $itemnumber = null) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeiteminstance = null;
        if ($itemtype == 'course') {
            $gradeiteminstance = grade_item::fetch(array('courseid' => $courseid, 'itemtype' => $itemtype));
        } else {
            $gradeiteminstance = grade_item::fetch(
                array('courseid' => $courseid, 'itemtype' => $itemtype,
                    'itemmodule' => $itemmodule, 'iteminstance' => $iteminstance, 'itemnumber' => $itemnumber));
        }
        return $gradeiteminstance;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.7
     */
    public static function core_grades_get_grades_returns() {
        return new external_single_structure(
            array(
                'items'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
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
                                    array(
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
                                    )
                                )
                            ),
                        )
                    )
                ),
                'outcomes'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
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
                                    )
                                )
                            ),
                        )
                    ), 'An array of outcomes associated with the grade items', VALUE_OPTIONAL
                )
            )
        );
    }


    /**
     * Describes the parameters for mod_forum_get_forum_discussions_paginated.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function mod_forum_get_forum_discussions_paginated_parameters() {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'forum ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }


    /**
     * Returns a list of forum discussions as well as a summary of the discussion in a provided list of forums.
     *
     * @param array $forumids the forum ids
     * @param int $limitfrom limit from SQL data
     * @param int $limitnum limit number SQL data
     *
     * @return array the forum discussion details
     * @since Moodle 2.8
     */
    public static function mod_forum_get_forum_discussions_paginated($forumid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                    $page = -1, $perpage = 0) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        $warnings = array();

        $params = self::validate_parameters(self::mod_forum_get_forum_discussions_paginated_parameters(),
            array(
                'forumid' => $forumid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            )
        );

        // Compact/extract functions are not recommended.
        $forumid        = $params['forumid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forum');

        $sort = 'd.' . $sortby . ' ' . $sortdirection;
        $discussions = forum_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage);

        if ($discussions) {
            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forum_tp_can_track_forums($forum)) {
                if ($forumtracked = forum_tp_is_tracked($forum)) {
                    $unreads = forum_get_discussions_unread($cm);
                }
            }
            // The forum function returns the replies for all the discussions in a given forum.
            $replies = forum_count_discussion_replies($forumid, $sort, -1, $page, $perpage);

            foreach ($discussions as $did => $discussion) {
                // This function checks for qanda forums.
                if (!forum_user_can_see_discussion($forum, $discussion, $modcontext)) {
                    $warning = array();
                    // Function forum_get_discussions returns forum_posts ids not forum_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && $forumtracked) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int) $unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int) $replies[$discussion->discussion]->replies;
                }

                // Load user objects from the results of the query.
                $user = new stdclass();
                $user->id = $discussion->userid;
                $user = username_load_fields_from_object($user, $discussion);
                $discussion->userfullname = fullname($user, $canviewfullname);
                $discussion->userpictureurl = moodle_url::make_pluginfile_url(
                    context_user::instance($user->id)->id, 'user', 'icon', null, '/', 'f1');
                // Fix the pluginfile.php link.
                $discussion->userpictureurl = str_replace("pluginfile.php", "webservice/pluginfile.php",
                    $discussion->userpictureurl);

                $usermodified = new stdclass();
                $usermodified->id = $discussion->usermodified;
                $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um');
                $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);
                $discussion->usermodifiedpictureurl = moodle_url::make_pluginfile_url(
                    context_user::instance($usermodified->id)->id, 'user', 'icon', null, '/', 'f1');
                // Fix the pluginfile.php link.
                $discussion->usermodifiedpictureurl = str_replace("pluginfile.php", "webservice/pluginfile.php",
                    $discussion->usermodifiedpictureurl);

                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                                            $modcontext->id, 'mod_forum', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = array();

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment',
                                                        $discussion->id, "filename", false)) {
                        foreach ($files as $file) {
                            $filename = $file->get_filename();

                            $discussion->attachments[] = array(
                                'filename' => $filename,
                                'mimetype' => $file->get_mimetype(),
                                'fileurl'  => file_encode_url($CFG->wwwroot.'/webservice/pluginfile.php',
                                                '/'.$modcontext->id.'/mod_forum/attachment/'.$discussion->id.'/'.$filename)
                            );
                        }
                    }
                }

                $discussions[$did] = (array) $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the mod_forum_get_forum_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function mod_forum_get_forum_discussions_paginated_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'name' => new external_value(PARAM_RAW, 'Discussion name'),
                                'groupid' => new external_value(PARAM_INT, 'Group id'),
                                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                                'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                                'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                                'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_RAW, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                                'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                                'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                                'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                                    not available due to forum settings.')
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }


    /**
     * Describes the parameters for get_forum_discussion_posts.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            )
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the forum post details
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts($discussionid, $sortby = "created", $sortdirection = "DESC") {
        global $CFG, $DB, $USER;

        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::mod_forum_get_forum_discussion_posts_parameters(),
            array(
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection));

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/forum/discuss.php.
        require_once($CFG->dirroot . "/mod/forum/lib.php");

        // Check they have the view forum capability.
        require_capability('mod/forum:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forum');

        if (! $post = forum_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'forum');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'forum');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = forum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

        $forumtracked = forum_tp_is_tracked($forum);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $posts = forum_get_all_discussion_posts($discussion->id, $sort, $forumtracked);

        foreach ($posts as $pid => $post) {

            if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function forum_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $posts[$pid]->postread = false;
            } else {
                $posts[$pid]->postread = true;
            }

            $posts[$pid]->canreply = $canreply;
            if (!empty($posts[$pid]->children)) {
                $posts[$pid]->children = array_keys($posts[$pid]->children);
            } else {
                $posts[$pid]->children = array();
            }

            $user = new stdclass();
            $user->id = $post->userid;
            $user = username_load_fields_from_object($user, $post);
            $post->userfullname = fullname($user, $canviewfullname);
            $post->userpictureurl = moodle_url::make_pluginfile_url(
                    context_user::instance($user->id)->id, 'user', 'icon', null, '/', 'f1');
            // Fix the pluginfile.php link.
            $post->userpictureurl = str_replace("pluginfile.php", "webservice/pluginfile.php",
                $post->userpictureurl);

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forum', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = array();

                $fs = get_file_storage();
                if ($files = $fs->get_area_files($modcontext->id, 'mod_forum', 'attachment', $post->id, "filename", false)) {
                    foreach ($files as $file) {
                        $filename = $file->get_filename();

                        $post->attachments[] = array(
                            'filename' => $filename,
                            'mimetype' => $file->get_mimetype(),
                            'fileurl'  => file_encode_url($CFG->wwwroot.'/webservice/pluginfile.php',
                                            '/'.$modcontext->id.'/mod_forum/attachment/'.$post->id.'/'.$filename)
                        );
                    }
                }
            }

            $posts[$pid] = (array) $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_forum_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function mod_forum_get_forum_discussion_posts_returns() {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_RAW, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure(
                                        array (
                                            'filename' => new external_value(PARAM_FILE, 'file name'),
                                            'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                            'fileurl'  => new external_value(PARAM_URL, 'file download url')
                                        )
                                    ), 'attachments', VALUE_OPTIONAL
                                ),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                                'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                                'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL),
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_forum.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function mod_forum_get_forums_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        '', VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forums in a provided list of courses,
     * if no list is provided all forums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forum details
     * @since Moodle 2.5
     */
    public static function mod_forum_get_forums_by_courses($courseids = array()) {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        $params = self::validate_parameters(self::mod_forum_get_forums_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            // Get all the courses the user can view.
            $courseids = array_keys(enrol_get_my_courses());
        } else {
            $courseids = $params['courseids'];
        }

        // Array to store the forums to return.
        $arrforums = array();

        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Go through the courseids and return the forums.
            foreach ($courseids as $cid) {
                // Get the course context.
                $context = context_course::instance($cid);
                // Check the user can function in this context.
                self::validate_context($context);
                // Get the forums in this course.
                if ($forums = $DB->get_records('forum', array('course' => $cid))) {
                    // Get the modinfo for the course.
                    $modinfo = get_fast_modinfo($cid);
                    // Get the forum instances.
                    $foruminstances = $modinfo->get_instances_of('forum');
                    // Loop through the forums returned by modinfo.
                    foreach ($foruminstances as $forumid => $cm) {
                        // If it is not visible or present in the forums get_records call, continue.
                        if (!$cm->uservisible || !isset($forums[$forumid])) {
                            continue;
                        }
                        // Set the forum object.
                        $forum = $forums[$forumid];
                        // Get the module context.
                        $context = context_module::instance($cm->id);
                        // Check they have the view forum capability.
                        require_capability('mod/forum:viewdiscussion', $context);
                        // Format the intro before being returning using the format setting.
                        list($forum->intro, $forum->introformat) = external_format_text($forum->intro, $forum->introformat,
                            $context->id, 'mod_forum', 'intro', 0);
                        // Add the course module id to the object, this information is useful.
                        $forum->cmid = $cm->id;

                        // Discussions count. This function does static request cache.
                        $forum->numdiscussions = forum_count_discussions($forum, $cm, $modinfo->get_course());

                        // Add the forum to the array to return.
                        $arrforums[$forum->id] = (array) $forum;
                    }
                }
            }
        }

        return $arrforums;
    }

    /**
     * Describes the get_forum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function mod_forum_get_forums_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_TEXT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forum type'),
                    'name' => new external_value(PARAM_RAW, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The forum intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions in the forum', VALUE_OPTIONAL)
                ), 'forum'
            )
        );
    }


    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function core_group_get_course_user_groups_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'userid' => new external_value(PARAM_INT, 'id of user')
            )
        );
    }

    /**
     * Get all groups in the specified course for the specified user
     *
     * @param int $courseid id of course
     * @param int $userid id of user
     * @return array of group objects (id, name ...)
     */
    public static function core_group_get_course_user_groups($courseid, $userid) {
        global $USER;

        // Warnings array, it can be empty at the end but is mandatory.
        $warnings = array();

        $params = array(
            'courseid' => $courseid,
            'userid' => $userid
        );
        $params = self::validate_parameters(self::core_group_get_course_user_groups_parameters(), $params);
        $courseid = $params['courseid'];
        $userid = $params['userid'];

        // Validate course and user. get_course throws an exception if the course does not exists.
        $course = get_course($courseid);
        $user = core_user::get_user($userid, 'id', MUST_EXIST);

        // Security checks.
        $context = context_course::instance($courseid);
        self::validate_context($context);

         // Check if we have permissions for retrieve the information.
        if ($userid != $USER->id) {
            if (!has_capability('moodle/course:managegroups', $context)) {
                throw new moodle_exception('accessdenied', 'admin');
            }
            // Validate if the user is enrolled in the course.
            if (!is_enrolled($context, $userid)) {
                // We return a warning because the function does not fail for not enrolled users.
                $warning['item'] = 'course';
                $warning['itemid'] = $courseid;
                $warning['warningcode'] = '1';
                $warning['message'] = "User $userid is not enrolled in course $courseid";
                $warnings[] = $warning;
            }
        }

        $usergroups = array();
        if (empty($warnings)) {
            $groups = groups_get_all_groups($courseid, $userid, 0, 'g.id, g.name, g.description, g.descriptionformat');

            foreach ($groups as $group) {
                list($group->description, $group->descriptionformat) =
                    external_format_text($group->description, $group->descriptionformat,
                            $context->id, 'group', 'description', $group->id);
                $usergroups[] = (array)$group;
            }
        }

        $results = array(
            'groups' => $usergroups,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function core_group_get_course_user_groups_returns() {
        return new external_single_structure(
            array(
                'groups' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'group record id'),
                            'name' => new external_value(PARAM_TEXT, 'multilang compatible name, course unique'),
                            'description' => new external_value(PARAM_RAW, 'group description text'),
                            'descriptionformat' => new external_format_value('description')
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }


    /**
     * Describes the parameters for get_grades_table.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table_parameters() {
        return new external_function_parameters (
            array(
                'courseid' => new external_value(PARAM_INT, 'Course Id', VALUE_REQUIRED),
                'userid'   => new external_value(PARAM_INT, 'Return grades only for this user (optional)', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns a list of grades tables for users in a course.
     *
     * @param int $courseid Course Id
     * @param int $userid   Only this user (optional)
     *
     * @return array the grades tables
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table($courseid, $userid = 0) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir  . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/grade/report/user/lib.php');

        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::gradereport_user_get_grades_table_parameters(),
            array(
                'courseid' => $courseid,
                'userid' => $userid)
            );

        // Compact/extract functions are not recommended.
        $courseid = $params['courseid'];
        $userid   = $params['userid'];

        // Function get_course internally throws an exception if the course doesn't exist.
        $course = get_course($courseid);

        $context = context_course::instance($courseid);
        self::validate_context($context);

        // Specific capabilities.
        require_capability('gradereport/user:view', $context);

        $user = null;

        if (empty($userid)) {
            require_capability('moodle/grade:viewall', $context);
        } else {
            $user = core_user::get_user($userid, '*', MUST_EXIST);
        }

        $access = false;

        if (has_capability('moodle/grade:viewall', $context)) {
            // Can view all course grades.
            $access = true;
        } else if ($userid == $USER->id and has_capability('moodle/grade:view', $context) and $course->showgrades) {
            // View own grades.
            $access = true;
        } else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) and $course->showgrades) {
            // Can view grades of this user, parent most probably.
            $access = true;
        }

        if (!$access) {
            throw new moodle_exception('nopermissiontoviewgrades', 'error',  $CFG->wwwroot.  '/course/view.php?id=' . $courseid);
        }

        $gpr = new grade_plugin_return(
            array(
                'type' => 'report',
                'plugin' => 'user',
                'courseid' => $courseid,
                'userid' => $userid)
            );

        $tables = array();

        // Just one user.
        if ($user) {
            $report = new grade_report_user($courseid, $gpr, $context, $userid);
            $report->fill_table();

            // Notice that we use array_filter for deleting empty elements in the array.
            // Those elements are items or category not visible by the user.
            $tables[] = array(
                'courseid'      => $courseid,
                'userid'        => $user->id,
                'userfullname'  => fullname($user),
                'maxdepth'      => $report->maxdepth,
                'tabledata'     => $report->tabledata
            );

        } else {
            $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
            $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);
            $showonlyactiveenrol = $showonlyactiveenrol || !has_capability('moodle/course:viewsuspendedusers', $context);

            $gui = new graded_users_iterator($course);
            $gui->require_active_enrolment($showonlyactiveenrol);
            $gui->init();

            while ($userdata = $gui->next_user()) {
                $currentuser = $userdata->user;
                $report = new grade_report_user($courseid, $gpr, $context, $currentuser->id);
                $report->fill_table();

                // Notice that we use array_filter for deleting empty elements in the array.
                // Those elements are items or category not visible by the user.
                $tables[] = array(
                    'courseid'      => $courseid,
                    'userid'        => $currentuser->id,
                    'userfullname'  => fullname($currentuser),
                    'maxdepth'      => $report->maxdepth,
                    'tabledata'     => $report->tabledata
                );
            }
            $gui->close();
        }

        $result = array();
        $result['tables'] = $tables;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Creates a table column structure
     *
     * @return array
     * @since  Moodle 2.8
     */
    private static function grades_table_column() {
        return array (
            'class'   => new external_value(PARAM_RAW, 'class'),
            'content' => new external_value(PARAM_RAW, 'cell content'),
            'headers' => new external_value(PARAM_RAW, 'headers')
        );
    }

    /**
     * Describes tget_grades_table return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function gradereport_user_get_grades_table_returns() {
        return new external_single_structure(
            array(
                'tables' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'userid'   => new external_value(PARAM_INT, 'user id'),
                            'userfullname' => new external_value(PARAM_TEXT, 'user fullname'),
                            'maxdepth'   => new external_value(PARAM_INT, 'table max depth (needed for printing it)'),
                            'tabledata' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'itemname' => new external_single_structure(
                                            array (
                                                'class' => new external_value(PARAM_RAW, 'file name'),
                                                'colspan' => new external_value(PARAM_INT, 'mime type'),
                                                'content'  => new external_value(PARAM_RAW, ''),
                                                'celltype'  => new external_value(PARAM_RAW, ''),
                                                'id'  => new external_value(PARAM_ALPHANUMEXT, '')
                                            ), 'The item returned data', VALUE_OPTIONAL
                                        ),
                                        'leader' => new external_single_structure(
                                            array (
                                                'class' => new external_value(PARAM_RAW, 'file name'),
                                                'rowspan' => new external_value(PARAM_INT, 'mime type')
                                            ), 'The item returned data', VALUE_OPTIONAL
                                        ),
                                        'weight' => new external_single_structure(
                                            self::grades_table_column(), 'weight column', VALUE_OPTIONAL
                                        ),
                                        'grade' => new external_single_structure(
                                            self::grades_table_column(), 'grade column', VALUE_OPTIONAL
                                        ),
                                        'range' => new external_single_structure(
                                            self::grades_table_column(), 'range column', VALUE_OPTIONAL
                                        ),
                                        'percentage' => new external_single_structure(
                                            self::grades_table_column(), 'percentage column', VALUE_OPTIONAL
                                        ),
                                        'lettergrade' => new external_single_structure(
                                            self::grades_table_column(), 'lettergrade column', VALUE_OPTIONAL
                                        ),
                                        'rank' => new external_single_structure(
                                            self::grades_table_column(), 'rank column', VALUE_OPTIONAL
                                        ),
                                        'average' => new external_single_structure(
                                            self::grades_table_column(), 'average column', VALUE_OPTIONAL
                                        ),
                                        'feedback' => new external_single_structure(
                                            self::grades_table_column(), 'feedback column', VALUE_OPTIONAL
                                        ),
                                        'contributiontocoursetotal' => new external_single_structure(
                                            self::grades_table_column(), 'contributiontocoursetotal column', VALUE_OPTIONAL
                                        ),
                                    ), 'table'
                                )
                            )
                        )
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function core_course_get_category_courses_parameters() {
        return new external_function_parameters(
            array(
                'categoryid' => new external_value(PARAM_INT, 'id of the category')
            )
        );
    }

    /**
     * Get all courses in the specified category
     *
     * @param int $categoryid id of the category
     * @return array of course objects (id, name ...) and warnings
     */
    public static function core_course_get_category_courses($categoryid) {
        $warnings = array();

        $params = array(
            'categoryid' => $categoryid
        );
        $params = self::validate_parameters(self::core_course_get_category_courses_parameters(), $params);
        $categoryid = $params['categoryid'];

        // Security checks.
        $context = context_coursecat::instance($categoryid);
        self::validate_context($context);

        $courses = get_courses($categoryid, 'c.sortorder ASC', 'c.id');
        $courseids = array_keys($courses);

        foreach ($courseids as $key => $cid) {
            $coursecontext = context_course::instance($cid);
            try {
                self::validate_context($coursecontext);
            } catch (Exception $e) {
                unset($courseids[$key]);
                continue;
            }
            if (!has_capability('moodle/course:view', $coursecontext)) {
                unset($courseids[$key]);
            }
        }

        $options = array(
            'ids' => $courseids
        );
        // Call the core function for retrieving the complete course information.
        $courses = core_course_external::get_courses($options);

        $results = array(
            'courses' => $courses,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function core_course_get_category_courses_returns() {
        return new external_single_structure(
            array(
                'courses' => core_course_external::get_courses_returns(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function core_get_legacy_logs_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id (0 for site logs)', VALUE_DEFAULT, 0),
                'userid' => new external_value(PARAM_INT, 'user id, 0 for alls', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id (for filtering by groups)', VALUE_DEFAULT, 0),
                'date' => new external_value(PARAM_INT, 'timestamp for date, 0 all days', VALUE_DEFAULT, 0),
                'modname' => new external_value(PARAM_PLUGIN, 'module name', VALUE_DEFAULT, ''),
                'modid' => new external_value(PARAM_FILE, 'mod id or "site_errors"', VALUE_DEFAULT, 0),
                'modaction' => new external_value(PARAM_NOTAGS, 'action (view, read)', VALUE_DEFAULT, ''),
                'page' => new external_value(PARAM_INT, 'page to show', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'entries per page', VALUE_DEFAULT, 100),
                'order' => new external_value(PARAM_ALPHA, 'time order (ASC or DESC)', VALUE_DEFAULT, 'ASC'),
            )
        );
    }

    /**
     * Return log entries
     *
     * @param int $categoryid id of the category
     * @return array of course objects (id, name ...) and warnings
     */
    public static function core_get_legacy_logs($courseid = 0, $userid = 0, $groupid = 0, $date = 0, $modname = '',
                                                $modid = 0, $modaction = '', $page = 0, $perpage = 100, $order = 'ASC') {
        global $CFG;

        $warnings = array();

        $params = array(
          'courseid' => $courseid,
          'userid' => $userid,
          'groupid' => $groupid,
          'date' => $date,
          'modname' => $modname,
          'modid' => $modid,
          'modaction' => $modaction,
          'page' => $page,
          'perpage' => $perpage,
          'order' => $order,
        );
        $params = self::validate_parameters(self::core_get_legacy_logs_parameters(), $params);

        if ($params['order'] != 'ASC' and $params['order'] != 'DESC') {
            throw new invalid_parameter_exception('Invalid order parameter');
        }

        if (empty($params['courseid'])) {
            $site = get_site();
            $params['courseid'] = $site->id;
            $context = context_system::instance();
        } else {
            $context = context_course::instance($params['courseid']);
        }

        $course = get_course($params['courseid']);

        self::validate_context($context);
        require_capability('report/log:view', $context);

        require_once($CFG->dirroot . '/course/lib.php');

        $logsresult = build_logs_array($course, $params['userid'], $params['date'],
                                                "l.time " . $params['order'], $params['page'] * $params['perpage'],
                                                $params['perpage'], $params['modname'], $params['modid'], $params['modaction'],
                                                $params['groupid']);

        $arraycast = function($el) {
            return (array) $el;
        };

        $results = array(
            'logs' => array_map($arraycast, $logsresult['logs']),
            'total' => $logsresult['totalcount'],
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function core_get_legacy_logs_returns() {
        return new external_single_structure(
            array(
                'logs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, ''),
                            'time' => new external_value(PARAM_INT, ''),
                            'userid' => new external_value(PARAM_INT, ''),
                            'ip' => new external_value(PARAM_RAW, ''),
                            'course' => new external_value(PARAM_INT, ''),
                            'module' => new external_value(PARAM_RAW, ''),
                            'cmid' => new external_value(PARAM_RAW, ''),
                            'action' => new external_value(PARAM_TEXT, ''),
                            'url' => new external_value(PARAM_RAW, ''),
                            'info' => new external_value(PARAM_RAW, ''),
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'total number of logs'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function report_log_get_log_records_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id (0 for site logs)', VALUE_DEFAULT, 0),
                'userid' => new external_value(PARAM_INT, 'user id, 0 for alls', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id (for filtering by groups)', VALUE_DEFAULT, 0),
                'date' => new external_value(PARAM_INT, 'timestamp for date, 0 all days', VALUE_DEFAULT, 0),
                'modid' => new external_value(PARAM_ALPHANUMEXT, 'mod id or "site_errors"', VALUE_DEFAULT, 0),
                'modaction' => new external_value(PARAM_NOTAGS, 'action (view, read)', VALUE_DEFAULT, ''),
                'logreader' => new external_value(PARAM_COMPONENT, 'Reader to be used for displaying logs', VALUE_DEFAULT, ''),
                'edulevel' => new external_value(PARAM_INT, 'educational level (1 teaching, 2 participating)', VALUE_DEFAULT, -1),
                'page' => new external_value(PARAM_INT, 'page to show', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'entries per page', VALUE_DEFAULT, 100),
                'order' => new external_value(PARAM_ALPHA, 'time order (ASC or DESC)', VALUE_DEFAULT, 'DESC'),
            )
        );
    }

    /**
     * Return log entries
     *
     * @param int $categoryid id of the category
     * @return array of course objects (id, name ...) and warnings
     */
    public static function report_log_get_log_records($courseid = 0, $userid = 0, $groupid = 0, $date = 0, $modid = 0,
                                                    $modaction = '', $logreader = '', $edulevel = -1, $page = 0,
                                                    $perpage = 100, $order = 'DESC') {
        global $CFG;
        require_once($CFG->dirroot.'/lib/tablelib.php');

        $warnings = array();
        $logsrecords = array();

        $params = array(
          'courseid' => $courseid,
          'userid' => $userid,
          'groupid' => $groupid,
          'date' => $date,
          'modid' => $modid,
          'modaction' => $modaction,
          'logreader' => $logreader,
          'edulevel' => $edulevel,
          'page' => $page,
          'perpage' => $perpage,
          'order' => $order,
        );
        $params = self::validate_parameters(self::report_log_get_log_records_parameters(), $params);

        if ($params['logreader'] == 'logstore_legacy') {
            $params['edulevel'] = -1;
        }

        if ($params['order'] != 'ASC' and $params['order'] != 'DESC') {
            throw new invalid_parameter_exception('Invalid order parameter');
        }

        if (empty($params['courseid'])) {
            $site = get_site();
            $params['courseid'] = $site->id;
            $context = context_system::instance();
        } else {
            $context = context_course::instance($params['courseid']);
        }

        $course = get_course($params['courseid']);

        self::validate_context($context);
        require_capability('report/log:view', $context);

        // Check if we are in 2.7 or above.
        if (class_exists('report_log_renderable')) {
            $reportlog = new report_log_renderable($params['logreader'], $course, $params['userid'], $params['modid'],
                                                    $params['modaction'],
                                                    $params['group'], $params['edulevel'], true, true,
                                                    false, true, '', $params['date'], '',
                                                    $params['page'], $params['perpage'], 'timecreated ' . $params['order']);
            $readers = $reportlog->get_readers();

            if (empty($readers)) {
                throw new moodle_exception('nologreaderenabled', 'report_log');
            }
            $reportlog->setup_table();
            $reportlog->tablelog->setup();
            $reportlog->tablelog->query_db($params['perpage'], false);

            foreach ($reportlog->tablelog->rawdata as $row) {
                $logsrecords[] = array(
                    'eventname' => $row->eventname,
                    'name' => $row->get_name(),
                    'description' => $row->get_description(),
                    'component' => $row->component,
                    'action' => $row->action,
                    'target' => $row->target,
                    'objecttable' => $row->objecttable,
                    'objectid' => $row->objectid,
                    'crud' => $row->crud,
                    'edulevel' => $row->edulevel,
                    'contextid' => $row->contextid,
                    'contextlevel' => $row->contextlevel,
                    'contextinstanceid' => $row->contextinstanceid,
                    'userid' => $row->userid,
                    'courseid' => $row->courseid,
                    'relateduserid' => $row->relateduserid,
                    'anonymous' => $row->anonymous,
                    'other' => json_encode($row->other),
                    'timecreated' => $row->timecreated,
                );
            }
        }

        $results = array(
            'logs' => $logsrecords,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function report_log_get_log_records_returns() {
        return new external_single_structure(
            array(
                'logs' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'eventname' => new external_value(PARAM_RAW, 'eventname'),
                            'name' => new external_value(PARAM_RAW, 'get_name()'),
                            'description' => new external_value(PARAM_RAW, 'get_description()'),
                            'component' => new external_value(PARAM_COMPONENT, 'component'),
                            'action' => new external_value(PARAM_RAW, 'action'),
                            'target' => new external_value(PARAM_RAW, 'target'),
                            'objecttable' => new external_value(PARAM_RAW, 'objecttable'),
                            'objectid' => new external_value(PARAM_RAW, 'objectid'),
                            'crud' => new external_value(PARAM_ALPHA, 'crud'),
                            'edulevel' => new external_value(PARAM_INT, 'edulevel'),
                            'contextid' => new external_value(PARAM_INT, 'contextid'),
                            'contextlevel' => new external_value(PARAM_INT, 'contextlevel'),
                            'contextinstanceid' => new external_value(PARAM_INT, 'contextinstanceid'),
                            'userid' => new external_value(PARAM_INT, 'userid'),
                            'courseid' => new external_value(PARAM_INT, 'courseid'),
                            'relateduserid' => new external_value(PARAM_INT, 'relateduserid'),
                            'anonymous' => new external_value(PARAM_INT, 'anonymous'),
                            'other' => new external_value(PARAM_RAW, 'other'),
                            'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since  Moodle 2.4
     */
    public static function mod_assign_get_assignments_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    '0 or more course ids',
                    VALUE_DEFAULT, array()
                ),
                'capabilities'  => new external_multiple_structure(
                    new external_value(PARAM_CAPABILITY, 'capability'),
                    'list of capabilities used to filter courses',
                    VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Returns an array of courses the user is enrolled in, and for each course all of the assignments that the user can
     * view within that course.
     *
     * @param array $courseids An optional array of course ids. If provided only assignments within the given course
     * will be returned. If the user is not enrolled in a given course a warning will be generated and returned.
     * @param array $capabilities An array of additional capability checks you wish to be made on the course context.
     * @return An array of courses and warnings.
     * @since  Moodle 2.4
     */
    public static function mod_assign_get_assignments($courseids = array(), $capabilities = array()) {
        global $USER, $DB, $CFG;
        require_once("$CFG->dirroot/mod/assign/locallib.php");

        $params = self::validate_parameters(
            self::mod_assign_get_assignments_parameters(),
            array('courseids' => $courseids, 'capabilities' => $capabilities)
        );

        $warnings = array();

        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $fields = 'sortorder,shortname,fullname,timemodified';
            $courses = enrol_get_users_courses($USER->id, true, $fields);
            $courseids = array_keys($courses);
        }

        foreach ($courseids as $cid) {

            try {
                $context = context_course::instance($cid);
                self::validate_context($context);

                // Check if this course was already loaded (by enrol_get_users_courses).
                if (!isset($courses[$cid])) {
                    $courses[$cid] = get_course($cid);
                }
            } catch (Exception $e) {
                unset($courses[$cid]);
                $warnings[] = array(
                    'item' => 'course',
                    'itemid' => $cid,
                    'warningcode' => '1',
                    'message' => 'No access rights in course context '.$e->getMessage()
                );
                continue;
            }
            if (count($params['capabilities']) > 0 && !has_all_capabilities($params['capabilities'], $context)) {
                unset($courses[$cid]);
            }
        }

        $extrafields = 'm.id as assignmentid, m.course, m.nosubmissions, m.submissiondrafts, m.sendnotifications, '.
                     'm.sendlatenotifications, m.duedate, m.allowsubmissionsfromdate, m.grade, m.timemodified, '.
                     'm.completionsubmit, m.cutoffdate, m.teamsubmission, m.requireallteammemberssubmit, '.
                     'm.teamsubmissiongroupingid, m.blindmarking, m.revealidentities, m.requiresubmissionstatement';
        $coursearray = array();
        foreach ($courses as $id => $course) {
            $assignmentarray = array();
            // Get a list of assignments for the course.
            if ($modules = get_coursemodules_in_course('assign', $courses[$id]->id, $extrafields)) {
                foreach ($modules as $module) {
                    $context = context_module::instance($module->id);
                    try {
                        self::validate_context($context);
                        require_capability('mod/assign:view', $context);
                    } catch (Exception $e) {
                        $warnings[] = array(
                            'item' => 'module',
                            'itemid' => $module->id,
                            'warningcode' => '1',
                            'message' => 'No access rights in module context'
                        );
                        continue;
                    }
                    $configrecords = $DB->get_recordset('assign_plugin_config', array('assignment' => $module->assignmentid));
                    $configarray = array();
                    foreach ($configrecords as $configrecord) {
                        $configarray[] = array(
                            'id' => $configrecord->id,
                            'assignment' => $configrecord->assignment,
                            'plugin' => $configrecord->plugin,
                            'subtype' => $configrecord->subtype,
                            'name' => $configrecord->name,
                            'value' => $configrecord->value
                        );
                    }
                    $configrecords->close();

                    $assignmentarray[]= array(
                        'id' => $module->assignmentid,
                        'cmid' => $module->id,
                        'course' => $module->course,
                        'name' => $module->name,
                        'nosubmissions' => $module->nosubmissions,
                        'submissiondrafts' => $module->submissiondrafts,
                        'sendnotifications' => $module->sendnotifications,
                        'sendlatenotifications' => $module->sendlatenotifications,
                        'duedate' => $module->duedate,
                        'allowsubmissionsfromdate' => $module->allowsubmissionsfromdate,
                        'grade' => $module->grade,
                        'timemodified' => $module->timemodified,
                        'completionsubmit' => $module->completionsubmit,
                        'cutoffdate' => $module->cutoffdate,
                        'teamsubmission' => $module->teamsubmission,
                        'requireallteammemberssubmit' => $module->requireallteammemberssubmit,
                        'teamsubmissiongroupingid' => $module->teamsubmissiongroupingid,
                        'blindmarking' => $module->blindmarking,
                        'revealidentities' => $module->revealidentities,
                        'requiresubmissionstatement' => $module->requiresubmissionstatement,
                        'configs' => $configarray
                    );
                }
            }
            $coursearray[] = array(
                'id' => $courses[$id]->id,
                'fullname' => $courses[$id]->fullname,
                'shortname' => $courses[$id]->shortname,
                'timemodified' => $courses[$id]->timemodified,
                'assignments' => $assignmentarray
            );
        }

        $result = array(
            'courses' => $coursearray,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Creates an assignment external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function mod_assign_get_assignments_assignment_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'assignment id'),
                'course' => new external_value(PARAM_INT, 'course id'),
                'name' => new external_value(PARAM_RAW, 'assignment name'),
                'nosubmissions' => new external_value(PARAM_INT, 'no submissions'),
                'submissiondrafts' => new external_value(PARAM_INT, 'submissions drafts'),
                'sendnotifications' => new external_value(PARAM_INT, 'send notifications'),
                'sendlatenotifications' => new external_value(PARAM_INT, 'send notifications'),
                'duedate' => new external_value(PARAM_INT, 'assignment due date'),
                'allowsubmissionsfromdate' => new external_value(PARAM_INT, 'allow submissions from date'),
                'grade' => new external_value(PARAM_INT, 'grade type'),
                'timemodified' => new external_value(PARAM_INT, 'last time assignment was modified'),
                'completionsubmit' => new external_value(PARAM_INT, 'if enabled, set activity as complete following submission'),
                'cutoffdate' => new external_value(PARAM_INT, 'date after which submission is not accepted without an extension'),
                'teamsubmission' => new external_value(PARAM_INT, 'if enabled, students submit as a team'),
                'requireallteammemberssubmit' => new external_value(PARAM_INT, 'if enabled, all team members must submit'),
                'teamsubmissiongroupingid' => new external_value(PARAM_INT, 'the grouping id for the team submission groups'),
                'blindmarking' => new external_value(PARAM_INT, 'if enabled, hide identities until reveal identities actioned'),
                'revealidentities' => new external_value(PARAM_INT, 'show identities for a blind marking assignment'),
                'requiresubmissionstatement' => new external_value(PARAM_INT, 'student must accept submission statement'),
                'configs' => new external_multiple_structure(self::mod_assign_get_assignments_config_structure(), 'configuration settings')
            ), 'assignment information object');
    }

    /**
     * Creates an assign_plugin_config external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function mod_assign_get_assignments_config_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'assign_plugin_config id'),
                'assignment' => new external_value(PARAM_INT, 'assignment id'),
                'plugin' => new external_value(PARAM_TEXT, 'plugin'),
                'subtype' => new external_value(PARAM_TEXT, 'subtype'),
                'name' => new external_value(PARAM_TEXT, 'name'),
                'value' => new external_value(PARAM_TEXT, 'value')
            ), 'assignment configuration object'
        );
    }

    /**
     * Creates a course external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    private static function mod_assign_get_assignments_course_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'course id'),
                'fullname' => new external_value(PARAM_TEXT, 'course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'course short name'),
                'timemodified' => new external_value(PARAM_INT, 'last time modified'),
                'assignments' => new external_multiple_structure(self::mod_assign_get_assignments_assignment_structure(), 'assignment info')
              ), 'course information object'
        );
    }

    /**
     * Describes the return value for get_assignments
     *
     * @return external_single_structure
     * @since Moodle 2.4
     */
    public static function mod_assign_get_assignments_returns() {
        return new external_single_structure(
            array(
                'courses' => new external_multiple_structure(self::mod_assign_get_assignments_course_structure(), 'list of courses'),
                'warnings'  => new external_warnings('item can be \'course\' (errorcode 1 or 2) or \'module\' (errorcode 1)',
                    'When item is a course then itemid is a course id. When the item is a module then itemid is a module id',
                    'errorcode can be 1 (no access rights) or 2 (not enrolled or no permissions)')
            )
        );
    }

    /**
     * Describes the parameters for get_submissions
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function mod_assign_get_submissions_parameters() {
        return new external_function_parameters(
            array(
                'assignmentids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'assignment id'),
                    '1 or more assignment ids',
                    VALUE_REQUIRED),
                'status' => new external_value(PARAM_ALPHA, 'status', VALUE_DEFAULT, ''),
                'since' => new external_value(PARAM_INT, 'submitted since', VALUE_DEFAULT, 0),
                'before' => new external_value(PARAM_INT, 'submitted before', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns submissions for the requested assignment ids
     *
     * @param array of ints $assignmentids
     * @param string $status only return submissions with this status
     * @param int $since only return submissions with timemodified >= since
     * @param int $before only return submissions with timemodified <= before
     * @return array of submissions for each requested assignment
     * @since Moodle 2.5
     */
    public static function mod_assign_get_submissions($assignmentids, $status = '', $since = 0, $before = 0) {
        global $DB, $CFG;
        require_once("$CFG->dirroot/mod/assign/locallib.php");
        $params = self::validate_parameters(self::mod_assign_get_submissions_parameters(),
                        array('assignmentids' => $assignmentids,
                              'status' => $status,
                              'since' => $since,
                              'before' => $before));

        $warnings = array();
        $assignments = array();

        // Check the user is allowed to get the submissions for the assignments requested.
        $placeholders = array();
        list($inorequalsql, $placeholders) = $DB->get_in_or_equal($params['assignmentids'], SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} md ON md.id = cm.module ".
               "WHERE md.name = :modname AND cm.instance ".$inorequalsql;
        $placeholders['modname'] = 'assign';
        $cms = $DB->get_records_sql($sql, $placeholders);
        $assigns = array();
        foreach ($cms as $cm) {
            try {
                $context = context_module::instance($cm->id);
                self::validate_context($context);
                require_capability('mod/assign:grade', $context);
                $assign = new assign($context, null, null);
                $assigns[] = $assign;
            } catch (Exception $e) {
                $warnings[] = array(
                    'item' => 'assignment',
                    'itemid' => $cm->instance,
                    'warningcode' => '1',
                    'message' => 'No access rights in module context'
                );
            }
        }

        foreach ($assigns as $assign) {
            $submissions = array();
            $submissionplugins = $assign->get_submission_plugins();
            $placeholders = array('assignid1' => $assign->get_instance()->id,
                                  'assignid2' => $assign->get_instance()->id);

            $sql = "SELECT mas.id, mas.assignment,mas.userid,".
                   "mas.timecreated,mas.timemodified,mas.status,mas.groupid ".
                   "FROM {assign_submission} mas ".
                   "WHERE mas.assignment = :assignid2";

            if (!empty($params['status'])) {
                $placeholders['status'] = $params['status'];
                $sql = $sql." AND mas.status = :status";
            }
            if (!empty($params['before'])) {
                $placeholders['since'] = $params['since'];
                $placeholders['before'] = $params['before'];
                $sql = $sql." AND mas.timemodified BETWEEN :since AND :before";
            } else {
                $placeholders['since'] = $params['since'];
                $sql = $sql." AND mas.timemodified >= :since";
            }

            $submissionrecords = $DB->get_records_sql($sql, $placeholders);

            if (!empty($submissionrecords)) {
                $fs = get_file_storage();
                foreach ($submissionrecords as $submissionrecord) {
                    $submission = array(
                        'id' => $submissionrecord->id,
                        'userid' => $submissionrecord->userid,
                        'timecreated' => $submissionrecord->timecreated,
                        'timemodified' => $submissionrecord->timemodified,
                        'status' => $submissionrecord->status,
                        'groupid' => $submissionrecord->groupid
                    );
                    foreach ($submissionplugins as $submissionplugin) {
                        $plugin = array(
                            'name' => $submissionplugin->get_name(),
                            'type' => $submissionplugin->get_type()
                        );
                        // Subtype is 'assignsubmission', type is currently 'file' or 'onlinetext'.
                        $component = $submissionplugin->get_subtype().'_'.$submissionplugin->get_type();

                        $fileareas = $submissionplugin->get_file_areas();
                        foreach ($fileareas as $filearea => $name) {
                            $fileareainfo = array('area' => $filearea);
                            $files = $fs->get_area_files(
                                $assign->get_context()->id,
                                $component,
                                $filearea,
                                $submissionrecord->id,
                                "timemodified",
                                false
                            );
                            foreach ($files as $file) {
                                $filepath = array('filepath' => $file->get_filepath().$file->get_filename());
                                $fileareainfo['files'][] = $filepath;
                            }
                            $plugin['fileareas'][] = $fileareainfo;
                        }

                        $editorfields = $submissionplugin->get_editor_fields();
                        foreach ($editorfields as $name => $description) {
                            $editorfieldinfo = array(
                                'name' => $name,
                                'description' => $description,
                                'text' => $submissionplugin->get_editor_text($name, $submissionrecord->id),
                                'format' => $submissionplugin->get_editor_format($name, $submissionrecord->id)
                            );
                            $plugin['editorfields'][] = $editorfieldinfo;
                        }

                        $submission['plugins'][] = $plugin;
                    }
                    $submissions[] = $submission;
                }
            } else {
                $warnings[] = array(
                    'item' => 'module',
                    'itemid' => $assign->get_instance()->id,
                    'warningcode' => '3',
                    'message' => 'No submissions found'
                );
            }

            $assignments[] = array(
                'assignmentid' => $assign->get_instance()->id,
                'submissions' => $submissions
            );

        }

        $result = array(
            'assignments' => $assignments,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Creates an assign_submissions external_single_structure
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    private static function mod_assign_get_submissions_structure() {
        return new external_single_structure(
            array (
                'assignmentid' => new external_value(PARAM_INT, 'assignment id'),
                'submissions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'submission id'),
                            'userid' => new external_value(PARAM_INT, 'student id'),
                            'timecreated' => new external_value(PARAM_INT, 'submission creation time'),
                            'timemodified' => new external_value(PARAM_INT, 'submission last modified time'),
                            'status' => new external_value(PARAM_TEXT, 'submission status'),
                            'groupid' => new external_value(PARAM_INT, 'group id'),
                            'plugins' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'type' => new external_value(PARAM_TEXT, 'submission plugin type'),
                                        'name' => new external_value(PARAM_TEXT, 'submission plugin name'),
                                        'fileareas' => new external_multiple_structure(
                                            new external_single_structure(
                                                array (
                                                    'area' => new external_value (PARAM_TEXT, 'file area'),
                                                    'files' => new external_multiple_structure(
                                                        new external_single_structure(
                                                            array (
                                                                'filepath' => new external_value (PARAM_TEXT, 'file path')
                                                            )
                                                        ), 'files', VALUE_OPTIONAL
                                                    )
                                                )
                                            ), 'fileareas', VALUE_OPTIONAL
                                        ),
                                        'editorfields' => new external_multiple_structure(
                                            new external_single_structure(
                                                array(
                                                    'name' => new external_value(PARAM_TEXT, 'field name'),
                                                    'description' => new external_value(PARAM_TEXT, 'field description'),
                                                    'text' => new external_value (PARAM_RAW, 'field value'),
                                                    'format' => new external_format_value ('text')
                                                )
                                            )
                                            , 'editorfields', VALUE_OPTIONAL
                                        )
                                    )
                                )
                                , 'plugins', VALUE_OPTIONAL
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Describes the get_submissions return value
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function mod_assign_get_submissions_returns() {
        return new external_single_structure(
            array(
                'assignments' => new external_multiple_structure(self::mod_assign_get_submissions_structure(), 'assignment submissions'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_custom_course_settings_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course, 0 for all', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get all custom course settings
     *
     * @param int $courseid id of course
     * @return array of settings
     */
    public static function get_custom_course_settings($courseid = 0) {

        // Warnings array, it can be empty at the end but is mandatory.
        $warnings = array();
        $settings = array();

        $params = array(
            'courseid' => $courseid
        );
        $params = self::validate_parameters(self::get_custom_course_settings_parameters(), $params);
        $courseid = $params['courseid'];

        if ($courseid) {
            $courses[] = $courseid;
            $settings[$courseid] = get_config('local_aspiredu', 'course' . $courseid);
        } else {
            $coursesettings = get_config('local_aspiredu');
            foreach ($coursesettings as $key => $val) {
                if (strpos($key, 'course') !== false) {
                    $courseid = str_replace('course', '', $key);
                    $courses[] = $courseid;
                    $settings[$courseid] = $val;
                }
            }
        }

        foreach ($courses as $id) {
            try {
                $context = context_course::instance($id);
                self::validate_context($context);
                require_capability('moodle/course:update', $context);

            } catch (Exception $e) {
                $warnings[] = array(
                    'item' => 'course',
                    'itemid' => $id,
                    'warningcode' => '1',
                    'message' => 'No access rights in course context'
                );
                unset($settings[$id]);
            }
        }

        $finalsettings = array();
        foreach ($settings as $courseid => $settingjson) {
            $settingjson = json_decode($settingjson);
            foreach ($settingjson as $name => $value) {
                $finalsettings[] = array(
                    'courseid' => $courseid,
                    'name' => $name,
                    'value' => $value,
                );
            }
        }

        $results = array(
            'settings' => $finalsettings,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_custom_course_settings_returns() {
        return new external_single_structure(
            array(
                'settings' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'course id'),
                            'name' => new external_value(PARAM_NOTAGS, 'setting name'),
                            'value' => new external_value(PARAM_NOTAGS, 'setting value'),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function core_course_get_course_module_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'The course module id')
            )
        );
    }

    /**
     * Return information about a course module.
     *
     * @param int $cmid the course module id
     * @return array of warnings and the course module
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function core_course_get_course_module($cmid) {

        $params = self::validate_parameters(self::core_course_get_course_module_parameters(),
                                            array(
                                                'cmid' => $cmid,
                                            ));

        $warnings = array();

        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, true, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // If the user has permissions to manage the activity, return all the information.
        if (has_capability('moodle/course:manageactivities', $context)) {
            $info = $cm;
        } else {
            // Return information is safe to show to any user.
            $info = new stdClass();
            $info->id = $cm->id;
            $info->course = $cm->course;
            $info->module = $cm->module;
            $info->modname = $cm->modname;
            $info->instance = $cm->instance;
            $info->section = $cm->section;
            $info->sectionnum = $cm->sectionnum;
            $info->groupmode = $cm->groupmode;
            $info->groupingid = $cm->groupingid;
            $info->completion = $cm->completion;
        }
        // Format name.
        $info->name = format_string($cm->name, true, array('context' => $context));

        $result = array();
        $result['cm'] = $info;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function core_course_get_course_module_returns() {
        return new external_single_structure(
            array(
                'cm' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'The course module id'),
                        'course' => new external_value(PARAM_INT, 'The course id'),
                        'module' => new external_value(PARAM_INT, 'The module type id'),
                        'name' => new external_value(PARAM_RAW, 'The activity name'),
                        'modname' => new external_value(PARAM_COMPONENT, 'The module component name (forum, assign, etc..)'),
                        'instance' => new external_value(PARAM_INT, 'The activity instance id'),
                        'section' => new external_value(PARAM_INT, 'The module section id'),
                        'sectionnum' => new external_value(PARAM_INT, 'The module section number'),
                        'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                        'groupingid' => new external_value(PARAM_INT, 'Grouping id'),
                        'completion' => new external_value(PARAM_INT, 'If completion is enabled'),
                        'idnumber' => new external_value(PARAM_RAW, 'Module id number', VALUE_OPTIONAL),
                        'added' => new external_value(PARAM_INT, 'Time added', VALUE_OPTIONAL),
                        'score' => new external_value(PARAM_INT, 'Score', VALUE_OPTIONAL),
                        'indent' => new external_value(PARAM_INT, 'Indentation', VALUE_OPTIONAL),
                        'visible' => new external_value(PARAM_INT, 'If visible', VALUE_OPTIONAL),
                        'visibleold' => new external_value(PARAM_INT, 'Visible old', VALUE_OPTIONAL),
                        'completiongradeitemnumber' => new external_value(PARAM_INT, 'Completion grade item', VALUE_OPTIONAL),
                        'completionview' => new external_value(PARAM_INT, 'Completion view setting', VALUE_OPTIONAL),
                        'completionexpected' => new external_value(PARAM_INT, 'Completion time expected', VALUE_OPTIONAL),
                        'showdescription' => new external_value(PARAM_INT, 'If the description is showed', VALUE_OPTIONAL),
                        'availability' => new external_value(PARAM_RAW, 'Availability settings', VALUE_OPTIONAL),
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function core_course_get_course_module_from_instance_parameters() {
        return new external_function_parameters(
            array(
                'module' => new external_value(PARAM_COMPONENT, 'The module name'),
                'instance' => new external_value(PARAM_INT, 'The module instance id')
            )
        );
    }

    /**
     * Return information about a course module.
     *
     * @param int $module the module name
     * @param int $instance the module instance
     * @return array of warnings and the course module
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function core_course_get_course_module_from_instance($module, $instance) {

        $params = self::validate_parameters(self::core_course_get_course_module_from_instance_parameters(),
                                            array(
                                                'module' => $module,
                                                'instance' => $instance,
                                            ));

        $warnings = array();

        $cm = get_coursemodule_from_instance($params['module'], $params['instance'], 0, true, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // If the user has permissions to manage the activity, return all the information.
        if (has_capability('moodle/course:manageactivities', $context)) {
            $info = $cm;
        } else {
            // Return information is safe to show to any user.
            $info = new stdClass();
            $info->id = $cm->id;
            $info->course = $cm->course;
            $info->module = $cm->module;
            $info->modname = $cm->modname;
            $info->instance = $cm->instance;
            $info->section = $cm->section;
            $info->sectionnum = $cm->sectionnum;
            $info->groupmode = $cm->groupmode;
            $info->groupingid = $cm->groupingid;
            $info->completion = $cm->completion;
        }
        // Format name.
        $info->name = format_string($cm->name, true, array('context' => $context));

        $result = array();
        $result['cm'] = $info;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function core_course_get_course_module_from_instance_returns() {
        return new external_single_structure(
            array(
                'cm' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'The course module id'),
                        'course' => new external_value(PARAM_INT, 'The course id'),
                        'module' => new external_value(PARAM_INT, 'The module type id'),
                        'name' => new external_value(PARAM_RAW, 'The activity name'),
                        'modname' => new external_value(PARAM_COMPONENT, 'The module component name (forum, assign, etc..)'),
                        'instance' => new external_value(PARAM_INT, 'The activity instance id'),
                        'section' => new external_value(PARAM_INT, 'The module section id'),
                        'sectionnum' => new external_value(PARAM_INT, 'The module section number'),
                        'groupmode' => new external_value(PARAM_INT, 'Group mode'),
                        'groupingid' => new external_value(PARAM_INT, 'Grouping id'),
                        'completion' => new external_value(PARAM_INT, 'If completion is enabled'),
                        'idnumber' => new external_value(PARAM_RAW, 'Module id number', VALUE_OPTIONAL),
                        'added' => new external_value(PARAM_INT, 'Time added', VALUE_OPTIONAL),
                        'score' => new external_value(PARAM_INT, 'Score', VALUE_OPTIONAL),
                        'indent' => new external_value(PARAM_INT, 'Indentation', VALUE_OPTIONAL),
                        'visible' => new external_value(PARAM_INT, 'If visible', VALUE_OPTIONAL),
                        'visibleold' => new external_value(PARAM_INT, 'Visible old', VALUE_OPTIONAL),
                        'completiongradeitemnumber' => new external_value(PARAM_INT, 'Completion grade item', VALUE_OPTIONAL),
                        'completionview' => new external_value(PARAM_INT, 'Completion view setting', VALUE_OPTIONAL),
                        'completionexpected' => new external_value(PARAM_INT, 'Completion time expected', VALUE_OPTIONAL),
                        'showdescription' => new external_value(PARAM_INT, 'If the description is showed', VALUE_OPTIONAL),
                        'availability' => new external_value(PARAM_RAW, 'Availability settings', VALUE_OPTIONAL),
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function core_grades_get_course_grades_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'id of course'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'),
                    'An array of user IDs, leave empty to just retrieve grade item information', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Retrieve the final course grade for the given users.
     *
     * @param  int $courseid        Course id
     * @param  array  $userids      Array of user ids
     * @return array                Array of grades
     */
    public static function core_grades_get_course_grades($courseid, $userids = array()) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir  . "/gradelib.php");

        $params = self::validate_parameters(self::core_grades_get_grades_parameters(),
            array('courseid' => $courseid, 'userids' => $userids));

        $courseid = $params['courseid'];
        $userids = $params['userids'];

        $coursecontext = context_course::instance($courseid);
        self::validate_context($coursecontext);

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        require_capability('moodle/grade:viewall', $coursecontext);

        $grade_item = grade_item::fetch_course_item($courseid);

        if ($grade_item->needsupdate) {
            grade_regrade_final_grades($courseid);
        }

        $item = new stdClass();
        $item->scaleid    = $grade_item->scaleid;
        $item->name       = $grade_item->get_name();
        $item->grademin   = $grade_item->grademin;
        $item->grademax   = $grade_item->grademax;
        $item->gradepass  = $grade_item->gradepass;
        $item->locked     = $grade_item->is_locked();
        $item->locked     = (empty($item->locked)) ? 0 : 1;
        $item->hidden     = $grade_item->is_hidden();
        $item->hidden     = (empty($item->hidden)) ? 0 : 1;
        $item->grades     = array();

        switch ($grade_item->gradetype) {
            case GRADE_TYPE_NONE:
                continue;

            case GRADE_TYPE_VALUE:
                $item->scaleid = 0;
                break;

            case GRADE_TYPE_TEXT:
                $item->scaleid   = 0;
                $item->grademin   = 0;
                $item->grademax   = 0;
                $item->gradepass  = 0;
                break;
        }

        if ($userids) {
            $grade_grades = grade_grade::fetch_users_grades($grade_item, $userids, true);
            foreach ($userids as $userid) {
                $grade_grades[$userid]->grade_item =& $grade_item;

                $grade = new stdClass();
                $grade->grade          = $grade_grades[$userid]->finalgrade;
                $grade->locked         = $grade_grades[$userid]->is_locked();
                $grade->hidden         = $grade_grades[$userid]->is_hidden();
                $grade->locked         = (empty($grade->locked)) ? 0 : 1;
                $grade->hidden         = (empty($grade->hidden)) ? 0 : 1;
                $grade->overridden     = $grade_grades[$userid]->overridden;
                $grade->feedback       = $grade_grades[$userid]->feedback;
                $grade->feedbackformat = $grade_grades[$userid]->feedbackformat;
                $grade->usermodified   = $grade_grades[$userid]->usermodified;
                $grade->dategraded     = $grade_grades[$userid]->get_dategraded();
                $grade->datesubmitted  = $grade_grades[$userid]->get_datesubmitted();
                $grade->grademax       = $item->grademax;

                // create text representation of grade
                if ($grade_item->needsupdate) {
                    $grade->grade          = false;
                    $grade->str_grade      = get_string('error');
                    $grade->str_long_grade = $grade->str_grade;

                } else if (is_null($grade->grade)) {
                    $grade->str_grade      = '-';
                    $grade->str_long_grade = $grade->str_grade;

                } else {

                    // 2.8.7 and 2.9.1 onwards.
                    if (method_exists($grade_grades[$userid], 'get_grade_max')) {
                        $grade_item->grademax = $grade_grades[$userid]->get_grade_max();
                        $grade_item->grademin = $grade_grades[$userid]->get_grade_min();
                    }

                    if (isset($grade_item->grademax)) {
                        $grade->grademax = $grade_item->grademax;
                    }

                    $grade->str_grade = grade_format_gradevalue($grade->grade, $grade_item);
                    if ($grade_item->gradetype == GRADE_TYPE_SCALE or $grade_item->get_displaytype() != GRADE_DISPLAY_TYPE_REAL) {
                        $grade->str_long_grade = $grade->str_grade;
                    } else {
                        $a = new stdClass();
                        $a->grade = $grade->str_grade;
                        $a->max   = grade_format_gradevalue($grade_item->grademax, $grade_item);
                        $grade->str_long_grade = get_string('gradelong', 'grades', $a);
                    }
                }

                // create html representation of feedback
                if (is_null($grade->feedback)) {
                    $grade->str_feedback = '';
                } else {
                    $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
                }
                $grade->userid = $userid;

                $item->grades[] = $grade;
            }
        }
        return $item;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function core_grades_get_course_grades_returns() {
        return new external_single_structure(
            array(
                'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                'name' => new external_value(PARAM_RAW, 'The module name'),
                'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                'grades' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'userid' => new external_value(
                                PARAM_INT, 'Student ID'),
                            'grade' => new external_value(
                                PARAM_FLOAT, 'Student grade'),
                            'grademax' => new external_value(
                                PARAM_FLOAT, 'Max student grade'),
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
                        )
                    ), 'user grades', VALUE_OPTIONAL
                ),
            )
        );
    }

}
