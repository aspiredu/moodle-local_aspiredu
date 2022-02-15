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

/**
 * Returns grading information for one or more activities, optionally with user grades
 * Manual, course or category items can not be queried.
 * This is a fixed version of the grade_get_grades Moodle 2.7 function
 *
 * @category grade
 * @param int    $courseid ID of course
 * @param string $itemtype Type of grade item. For example, 'mod' or 'block'
 * @param string $itemmodule More specific then $itemtype. For example, 'forum' or 'quiz'. May be NULL for some item types
 * @param int    $iteminstance ID of the item module
 * @param mixed  $userIdOrIds Either a single user ID, an array of user IDs or null. If user ID or IDs are not supplied 
 *               returns information about gradeItem
 * @return array Array of grade information objects (scaleid, name, grade and locked status, etc.) indexed with itemnumbers
 */
function local_aspiredu_grade_get_grades(
    $courseid, $itemtype = null, $itemmodule = null, $iteminstance = null, $userIdOrIds=null
) {
    global $CFG;
    $return = new stdClass();
    $return->items    = array();
    $return->outcomes = array();
    $courseItem = grade_item::fetch_course_item($courseid);
    $needsupdate = array();
    if ($courseItem->needsupdate) {
        $result = grade_regrade_final_grades($courseid);
        if ($result !== true) {
            $needsupdate = array_keys($result);
        }
    }
    $params = array('courseid' => $courseid);
    if (!empty($itemtype)) {
        $params['itemtype'] = $itemtype;
    }
    if (!empty($itemmodule)) {
        $params['itemmodule'] = $itemmodule;
    }
    if (!empty($iteminstance)) {
        $params['iteminstance'] = $iteminstance;
    }
    if ($gradeItems = grade_item::fetch_all($params)) {
        foreach ($gradeItems as $gradeItem) {
            $decimalpoints = null;
            if (empty($gradeItem->outcomeid)) {
                // Prepare information about grade item.
                $item = new stdClass();
                $item->id = $gradeItem->id;
                $item->itemnumber = $gradeItem->itemnumber;
                $item->itemtype  = $gradeItem->itemtype;
                $item->itemmodule = $gradeItem->itemmodule;
                $item->iteminstance = $gradeItem->iteminstance;
                $item->scaleid    = $gradeItem->scaleid;
                $item->name       = $gradeItem->get_name();
                $item->grademin   = $gradeItem->grademin;
                $item->grademax   = $gradeItem->grademax;
                $item->gradepass  = $gradeItem->gradepass;
                $item->locked     = $gradeItem->is_locked();
                $item->hidden     = $gradeItem->is_hidden();
                $item->grades     = array();
                switch ($gradeItem->gradetype) {
                    case GRADE_TYPE_NONE:
                        break;
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
                if (empty($userIdOrIds)) {
                    $userids = array();
                } else if (is_array($userIdOrIds)) {
                    $userids = $userIdOrIds;
                } else {
                    $userids = array($userIdOrIds);
                }
                if ($userids) {
                    $gradeGrades = grade_grade::fetch_users_grades($gradeItem, $userids, true);
                    foreach ($userids as $userid) {
                        $gradeGrades[$userid]->gradeItem =& $gradeItem;
                        $grade = new stdClass();
                        $grade->grade          = $gradeGrades[$userid]->finalgrade;
                        $grade->locked         = $gradeGrades[$userid]->is_locked();
                        $grade->hidden         = $gradeGrades[$userid]->is_hidden();
                        $grade->overridden     = $gradeGrades[$userid]->overridden;
                        $grade->feedback       = $gradeGrades[$userid]->feedback;
                        $grade->feedbackformat = $gradeGrades[$userid]->feedbackformat;
                        $grade->usermodified   = $gradeGrades[$userid]->usermodified;
                        $grade->datesubmitted  = $gradeGrades[$userid]->get_datesubmitted();
                        $grade->dategraded     = $gradeGrades[$userid]->get_dategraded();
                        // Create text representation of grade.
                        if ($gradeItem->gradetype == GRADE_TYPE_TEXT or $gradeItem->gradetype == GRADE_TYPE_NONE) {
                            $grade->grade          = null;
                            $grade->str_grade      = '-';
                            $grade->str_long_grade = $grade->str_grade;
                        } else if (in_array($gradeItem->id, $needsupdate)) {
                            $grade->grade          = false;
                            $grade->str_grade      = get_string('error');
                            $grade->str_long_grade = $grade->str_grade;
                        } else if (is_null($grade->grade)) {
                            $grade->str_grade      = '-';
                            $grade->str_long_grade = $grade->str_grade;
                        } else {
                            $grade->str_grade = grade_format_gradevalue($grade->grade, $gradeItem);
                            if (
                                $gradeItem->gradetype == GRADE_TYPE_SCALE or
                                $gradeItem->get_displaytype() != GRADE_DISPLAY_TYPE_REAL
                            ) {
                                $grade->str_long_grade = $grade->str_grade;
                            } else {
                                $a = new stdClass();
                                $a->grade = $grade->str_grade;
                                $a->max   = grade_format_gradevalue($gradeItem->grademax, $gradeItem);
                                $grade->str_long_grade = get_string('gradelong', 'grades', $a);
                            }
                        }
                        // Create html representation of feedback.
                        if (is_null($grade->feedback)) {
                            $grade->str_feedback = '';
                        } else {
                            $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
                        }
                        $item->grades[$userid] = $grade;
                    }
                }
                $return->items[$item->id] = $item;
            } else {
                if (!$gradeOutcome = grade_outcome::fetch(array('id' => $gradeItem->outcomeid))) {
                    debugging('Incorect outcomeid found');
                    continue;
                }
                // Outcome info.
                $outcome = new stdClass();
                $outcome->id = $gradeItem->id;
                $outcome->itemnumber = $gradeItem->itemnumber;
                $outcome->itemtype   = $gradeItem->itemtype;
                $outcome->itemmodule = $gradeItem->itemmodule;
                $outcome->iteminstance = $gradeItem->iteminstance;
                $outcome->scaleid    = $gradeOutcome->scaleid;
                $outcome->name       = $gradeOutcome->get_name();
                $outcome->locked     = $gradeItem->is_locked();
                $outcome->hidden     = $gradeItem->is_hidden();
                if (empty($userIdOrIds)) {
                    $userids = array();
                } else if (is_array($userIdOrIds)) {
                    $userids = $userIdOrIds;
                } else {
                    $userids = array($userIdOrIds);
                }
                if ($userids) {
                    $gradeGrades = grade_grade::fetch_users_grades($gradeItem, $userids, true);
                    foreach ($userids as $userid) {
                        $gradeGrades[$userid]->gradeItem =& $gradeItem;
                        $grade = new stdClass();
                        $grade->grade          = $gradeGrades[$userid]->finalgrade;
                        $grade->locked         = $gradeGrades[$userid]->is_locked();
                        $grade->hidden         = $gradeGrades[$userid]->is_hidden();
                        $grade->feedback       = $gradeGrades[$userid]->feedback;
                        $grade->feedbackformat = $gradeGrades[$userid]->feedbackformat;
                        $grade->usermodified   = $gradeGrades[$userid]->usermodified;
                        // Create text representation of grade.
                        if (in_array($gradeItem->id, $needsupdate)) {
                            $grade->grade     = false;
                            $grade->str_grade = get_string('error');
                        } else if (is_null($grade->grade)) {
                            $grade->grade = 0;
                            $grade->str_grade = get_string('nooutcome', 'grades');
                        } else {
                            $grade->grade = (int)$grade->grade;
                            $scale = $gradeItem->load_scale();
                            $grade->str_grade = format_string($scale->scale_items[(int)$grade->grade - 1]);
                        }
                        // Create html representation of feedback.
                        if (is_null($grade->feedback)) {
                            $grade->str_feedback = '';
                        } else {
                            $grade->str_feedback = format_text($grade->feedback, $grade->feedbackformat);
                        }
                        $outcome->grades[$userid] = $grade;
                    }
                }
                if (isset($return->outcomes[$gradeItem->itemnumber])) {
                    // Fix all itemnumber duplicates.
                    $newnumber = $gradeItem->itemnumber + 1;
                    while (
                        grade_item::fetch(
                            array(
                                'itemtype' => $itemtype,
                                'itemmodule' => $itemmodule,
                                'iteminstance' => $iteminstance,
                                'courseid' => $courseid,
                                'itemnumber' => $newnumber
                            )
                        )
                    ) {
                        $newnumber++;
                    }
                    $outcome->itemnumber    = $newnumber;
                    $gradeItem->itemnumber = $newnumber;
                    $gradeItem->update('system');
                }
                $return->outcomes[$outcome->id] = $outcome;
            }
        }
    }
    // Sort results using itemnumbers.
    ksort($return->items, SORT_NUMERIC);
    ksort($return->outcomes, SORT_NUMERIC);
    return $return;
}
