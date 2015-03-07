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

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/course/externallib.php");
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
            // Switch the stdClass instance for a grade item instance so we can call is_hidden() and use the ID.
            $gradeiteminstance = self::core_grades_get_grade_item(
                $course->id, $gradeitem->itemtype, $gradeitem->itemmodule, $gradeitem->iteminstance, $gradeitem->itemnumber);

            if (!$canviewhidden && $gradeiteminstance->is_hidden()) {
                continue;
            }

            // Format mixed bool/integer parameters.
            $gradeitem->hidden = (!$gradeitem->hidden)? 0 : $gradeitem->hidden;
            $gradeitem->locked = (!$gradeitem->locked)? 0 : $gradeitem->locked;

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
                    $studentgrade->hidden = (!$studentgrade->hidden)? 0 : $studentgrade->hidden;
                    $studentgrade->locked = (!$studentgrade->locked)? 0 : $studentgrade->locked;
                    $studentgrade->overridden = (!$studentgrade->overridden)? 0 : $studentgrade->overridden;

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
            }
        }

        $gradesarray['outcomes'] = array();
        foreach ($grades->outcomes as $outcome) {
            $modulecm = $cm;
            if (empty($modulecm)) {
                $modulecm = $acitivityinstances[$outcome->itemmodule][$outcome->iteminstance];
            }

            // Format mixed bool/integer parameters.
            $outcome->hidden = (!$outcome->hidden)? 0 : $outcome->hidden;
            $outcome->locked = (!$outcome->locked)? 0 : $outcome->locked;

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
                    $studentgrade->hidden = (!$studentgrade->hidden)? 0 : $studentgrade->hidden;
                    $studentgrade->locked = (!$studentgrade->locked)? 0 : $studentgrade->locked;

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
    private static function core_grades_get_grade_item($courseid, $itemtype, $itemmodule = null, $iteminstance = null, $itemnumber = null) {
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
                                'name' => new external_value(PARAM_TEXT, 'Discussion name'),
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
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
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
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
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
                    'name' => new external_value(PARAM_TEXT, 'Forum name'),
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
                'modaction' => new external_value(PARAM_PATH, 'action (view, read)', VALUE_DEFAULT, ''),
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
        $results = array(
            'logs' => $logsresult['logs'],
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

}
