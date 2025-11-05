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
 * Clean Grader Report
 *
 * This plugin allows teachers to print student grades more cleanly for parent conferences.
 *
 * @package    local_cleangradereport
 * @copyright  2025 Brian A. Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add clean grade report button to grade reports
 */
function local_cleangradereport_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    global $PAGE, $USER;
    
    // Only add if we're viewing grades and have permission
    if ($PAGE->url->compare(new moodle_url('/grade/report/user/index.php'), URL_MATCH_BASE)) {
        $userid = optional_param('userid', $USER->id, PARAM_INT);
        $courseid = required_param('id', PARAM_INT);
        
        if (has_capability('gradereport/user:view', $coursecontext)) {
            $url = new moodle_url('/local/cleangradereport/print_report.php', 
                array('userid' => $userid, 'courseid' => $courseid));
            
            $printnode = navigation_node::create(
                get_string('printcleanreport', 'local_cleangradereport'),
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'cleangradereport',
                new pix_icon('i/report', '')
            );
            
            $navigation->add_node($printnode);
        }
    }
}

/**
 * Inject JavaScript for print button
 */
function local_cleangradereport_before_footer() {
    global $PAGE, $CFG, $USER;
    
    if ($PAGE->url->compare(new moodle_url('/grade/report/user/index.php'), URL_MATCH_BASE)) {
        $userid = optional_param('userid', $USER->id, PARAM_INT);
        $courseid = required_param('id', PARAM_INT);
        
        // Make sure we have a valid userid
        if (empty($userid)) {
            $userid = $USER->id;
        }
        
        // Inject JavaScript directly instead of using AMD
        $js = "
        document.addEventListener('DOMContentLoaded', function() {
            var gradeContent = document.querySelector('.path-grade-report-user, .grade-report-user, [data-region=\"grade-report\"]');
            if (gradeContent) {
                var printUrl = '{$CFG->wwwroot}/local/cleangradereport/print_report.php?courseid={$courseid}&userid={$userid}';
                var printButton = document.createElement('div');
                printButton.className = 'mb-3 local-cleangradereport-print-button';
                var buttonText = '" . get_string('printcleanreport', 'local_cleangradereport') . "';
                printButton.innerHTML = '<a href=\"' + printUrl + '\" target=\"_blank\" class=\"btn btn-primary\"><i class=\"fa fa-print\" aria-hidden=\"true\"></i> ' + buttonText + '</a>';
                
                var table = document.querySelector('.grade-report-user table, .generaltable');
                if (table) {
                    table.parentNode.insertBefore(printButton, table);
                } else {
                    gradeContent.insertBefore(printButton, gradeContent.firstChild);
                }
            }
        });";
        
        $PAGE->requires->js_init_code($js);
    }
}
/**
 * Get clean grade data for a user in a course
 */
function local_cleangradereport_get_grade_data($userid, $courseid) {
    global $DB, $CFG;
    
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/grade/lib.php');
    require_once($CFG->dirroot . '/grade/report/user/lib.php');
    
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
    
    $context = context_course::instance($courseid);
    
    // Initialize grade tree normally
    $gtree = new grade_tree($courseid, false, false);
    
    $data = array();
    $data['studentname'] = fullname($user);
    $data['coursename'] = $course->fullname;
    $data['items'] = array();
    
    // Process the root element itself to get course total
    local_cleangradereport_process_grade_items($gtree->top_element, $userid, $data['items'], 0);
    
    return $data;
}
/**
 * Recursively process grade items to match the desired format
 */
function local_cleangradereport_process_grade_items($element, $userid, &$items, $level = 0) {
    global $CFG, $DB;
    
    if ($element['type'] == 'category') {
        $category = $element['object'];
        
        // Handle the root course category (course total)
        if ($level == 0 && ($category->fullname == '?' || empty(trim($category->fullname)))) {
            // Process children first
            if (isset($element['children'])) {
                foreach ($element['children'] as $child) {
                    local_cleangradereport_process_grade_items($child, $userid, $items, $level);
                }
            }
            
            // Now add the course total at the end
            $gradeitem = $category->load_grade_item();
            if (!$gradeitem->is_hidden()) {
                $grade = new grade_grade(array('itemid' => $gradeitem->id, 'userid' => $userid));
                
                if ($grade->id && !is_null($grade->finalgrade)) {
                    if ($gradeitem->grademax > 0) {
                        $percentage = ($grade->finalgrade / $gradeitem->grademax) * 100;
                        $gradestr = number_format($percentage, 2) . '% (' . 
                                   grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER) . ')';
                    } else {
                        $gradestr = grade_format_gradevalue($grade->finalgrade, $gradeitem, true) . ' (' . 
                                   grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER) . ')';
                    }
                    
                    $lettergrade = grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER);
                    $lettergrade = preg_replace('/[()]/', '', $lettergrade);
                    $lettergrade = trim($lettergrade);
                    
                    $items[] = array(
                        'type' => 'coursetotal',
                        'name' => get_string('coursetotal', 'local_cleangradereport'),
                        'weight' => '',
                        'grade' => $gradestr,
                        'lettergrade' => $lettergrade,
                        'iscategory' => false,
                        'istotal' => false,
                        'iscoursetotal' => true,
                        'level' => 0
                    );
                }
            }
            return;
        }
        
        // Check if category is hidden from students
        if ($category->is_hidden()) {
            return;
        }
        
        // Add category header
        if (!empty(trim($category->fullname)) && $category->fullname != '?') {
            $items[] = array(
                'type' => 'category',
                'name' => $category->fullname,
                'weight' => '',
                'grade' => '',
                'lettergrade' => '',
                'iscategory' => true,
                'level' => $level
            );
        }
        
        // Process child items
        if (isset($element['children'])) {
            foreach ($element['children'] as $child) {
                local_cleangradereport_process_grade_items($child, $userid, $items, $level + 1);
            }
        }
        
        // Add category total if not root level
        if ($level > 0 && !empty(trim($category->fullname)) && $category->fullname != '?') {
            $gradeitem = $category->load_grade_item();
            
            // Skip if grade item is hidden
            if ($gradeitem->is_hidden()) {
                return;
            }
            
            $grade = new grade_grade(array('itemid' => $gradeitem->id, 'userid' => $userid));
            
            if ($grade->id && !is_null($grade->finalgrade)) {
                if ($gradeitem->grademax > 0) {
                    $percentage = ($grade->finalgrade / $gradeitem->grademax) * 100;
                    $gradestr = number_format($percentage, 2) . '% (' . 
                               grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER) . ')';
                } else {
                    $gradestr = grade_format_gradevalue($grade->finalgrade, $gradeitem, true) . ' (' . 
                               grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER) . ')';
                }
                
                $lettergrade = grade_format_gradevalue($grade->finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_LETTER);
                $lettergrade = preg_replace('/[()]/', '', $lettergrade);
                $lettergrade = trim($lettergrade);
                
                $weight = '';
                if (isset($element['weight']) && $element['weight'] > 0) {
                    $weight = number_format($element['weight'], 2) . '%';
                }
                
                $items[] = array(
                    'type' => 'total',
                    'name' => get_string('categorytotal', 'local_cleangradereport', $category->fullname),
                    'weight' => $weight,
                    'grade' => $gradestr,
                    'lettergrade' => $lettergrade,
                    'iscategory' => false,
                    'istotal' => true,
                    'level' => $level
                );
            }
        }
        
    } else if ($element['type'] == 'item') {
        $item = $element['object'];
        
        // Skip category items and hidden items
        if ($item->itemtype == 'category' || $item->is_hidden()) {
            return;
        }
        
        // Check if the grade item is linked to a course module (activity)
        $isVisibleToStudent = true;
        if ($item->itemtype == 'mod' && $item->itemmodule && $item->iteminstance) {
            // Get the course module
            $cm = get_coursemodule_from_instance($item->itemmodule, $item->iteminstance, $item->courseid);
            
            if ($cm) {
                // Check if the activity is available to this specific user
                require_once($CFG->libdir . '/modinfolib.php');
                $modinfo = get_fast_modinfo($item->courseid, $userid);
                $cminfo = $modinfo->get_cm($cm->id);
                
                // If the activity is not available to this user (due to restrictions), skip it
                if (!$cminfo->available && !$cminfo->uservisible) {
                    $isVisibleToStudent = false;
                }
                
                // Also check if it's restricted by grouping/group
                if (!$cminfo->uservisible) {
                    $isVisibleToStudent = false;
                }
            }
        }
        
        // If not visible to student due to restrictions, skip this item entirely
        if (!$isVisibleToStudent) {
            return;
        }
        
        // Get the user's grade
        $grade = new grade_grade(array('itemid' => $item->id, 'userid' => $userid));
        
        $gradestr = '';
        $lettergrade = '';
        $weight = '';
        
        // Calculate weight percentage
        if (isset($element['weight']) && $element['weight'] > 0) {
            $weight = number_format($element['weight'], 2) . '%';
        }
        
        $hasGrade = false;
        if ($grade->id) {
            if (!is_null($grade->finalgrade)) {
                $hasGrade = true;
                if ($item->grademax > 0) {
                    $percentage = ($grade->finalgrade / $item->grademax) * 100;
                    $letterval = grade_format_gradevalue($grade->finalgrade, $item, true, GRADE_DISPLAY_TYPE_LETTER);
                    $gradestr = number_format($percentage, 2) . '% (' . $letterval . ')';
                    
                    $lettergrade = preg_replace('/[()]/', '', $letterval);
                    $lettergrade = trim($lettergrade);
                } else {
                    $gradestr = grade_format_gradevalue($grade->finalgrade, $item, true);
                    $lettergrade = grade_format_gradevalue($grade->finalgrade, $item, true, GRADE_DISPLAY_TYPE_LETTER);
                    $lettergrade = preg_replace('/[()]/', '', $lettergrade);
                    $lettergrade = trim($lettergrade);
                }
            } else {
                // Empty grade - format for display
                $gradestr = '-';
                $lettergrade = '-';
            }
        } else {
            // No grade record - format for display  
            $gradestr = '-';
            $lettergrade = '-';
        }
        
        // Since the item is visible to the student, always show it
        $items[] = array(
            'type' => 'item',
            'name' => $item->itemname,
            'weight' => $weight,
            'grade' => $gradestr,
            'lettergrade' => $lettergrade,
            'iscategory' => false,
            'istotal' => false,
            'level' => $level
        );
    }
}
/**
 * Hook to add CSS for the plugin
 */
function local_cleangradereport_before_http_headers() {
    global $PAGE;
    
    if ($PAGE->url->compare(new moodle_url('/grade/report/user/index.php'), URL_MATCH_BASE)) {
        $PAGE->requires->css('/local/cleangradereport/styles.css');
    }
}
