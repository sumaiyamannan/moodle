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
 * Utility functions that don't fit elsewhere.
 *
 * @package    local_xmlsync
 * @author     David Thompson <david.thompson@catalyst.net.nz>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_xmlsync;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

class util {
    /**
     * Hook for enrol_database to set course visibility for first course creation
     *
     * If an xmlsync record with a matching idnumber is found, set course visibility accordingly.
     *
     * Required core change injected into enrol/database/lib.php sync_courses:
     *     \local_xmlsync\util::enrol_database_course_hook($course);
     *
     * WR#371794
     *
     * @param stdClass $course
     * @return void
     */
    public static function enrol_database_course_hook(&$course, $trace) {
        global $DB;
        $select = $DB->sql_like('course_idnumber', ':idnum', false); // Case insensitive.
        $params = array('idnum' => $course->idnumber);
        $matchingrecord = $DB->get_record_select('local_xmlsync_crsimport', $select, $params);
        if ($matchingrecord && isset($matchingrecord->course_visibility)) {
            $course->visible = $matchingrecord->course_visibility;
            $course->timemodified = time();
            $trace->output("Setting course settings for {$course->fullname}, shortname:{$course->shortname}, idnumber:{$course->idnumber}");
        }
    }

    /**
     * Hook for enrol_database to set course visibility.
     *
     * If an xmlsync record with a matching idnumber is found, set course visibility accordingly.
     *
     * Required core change injected into enrol/database/lib.php sync_courses:
     *     \local_xmlsync\util::enrol_database_course_hook($course);
     *
     * WR#371794
     *
     * @param stdClass $course
     * @return void
     */
    public static function enrol_database_course_update_hook(&$course, $trace) {
        global $DB;
        //No idnumber, means no update
        if(!isset($course->idnumber) || $course->idnumber == "") {
            return;
        }
        $select = $DB->sql_like('course_idnumber', ':idnum', false); // Case insensitive.
        $params = array('idnum' => $course->idnumber);
        $matchingrecord = $DB->get_record_select('local_xmlsync_crsimport', $select, $params);
        if ($matchingrecord && isset($matchingrecord->course_visibility) && $course->visible != $matchingrecord->course_visibility) {
            $course->visible = $matchingrecord->course_visibility;
            $course->timemodified = time();
            $trace->output("Updating course settings for {$course->fullname}, shortname:{$course->shortname}, idnumber:{$course->idnumber}");
            $DB->update_record('course', $course);
        }

    }

    /**
     * Hook for enrol_database: Check whether course with idnumber has entry in course import.
     *
     * WR#371793
     *
     * @param string $idnumber
     * @return boolean
     */
    public static function enrol_database_template_check($idnumber) : bool {
        global $DB;
        $select = $DB->sql_like('course_idnumber', ':idnum', false); // Case insensitive.
        $params = array('idnum' => $idnumber);
        $matchingrecord = $DB->get_record_select('local_xmlsync_crsimport', $select, $params);

        if ($matchingrecord && $matchingrecord->course_template != '') {
            return true;
        }

        return false;
    }

    /**
     * Hook for enrol_database: clone course from template.
     *
     * If:
     * - an xmlsync record with a matching idnumber is found
     * - its template field is a valid course idnumber
     * Then clone the template course content into the new course, minus user data.
     *
     * Required core change injected into enrol/database/lib.php sync_courses:
     *     \local_xmlsync\util::enrol_database_template_hook($course);
     *
     * WR#371793
     *
     * @param stdClass $course
     * @return void
     */
    public static function enrol_database_template_hook($course, $trace) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $select = $DB->sql_like('course_idnumber', ':idnum', false); // Case insensitive.
        $params = array('idnum' => $course->idnumber);
        $matchingrecord = $DB->get_record_select('local_xmlsync_crsimport', $select, $params);
        if ($matchingrecord) {
            $templatecourse = $DB->get_record('course', array('idnumber' => $matchingrecord->course_template));
            if ($templatecourse && (!isset($matchingrecord->copy_task_controllers) || $matchingrecord->copy_task_controllers == null)) {
                $trace->output("Cloning from '{$templatecourse->fullname}' into '{$course->fullname}':\n");


               $roles = explode(',', get_config('local_xmlsync', 'roles_to_keep'));
               
                // Make a fake course copy form.
                $dummyform = array(
                    'courseid' => $templatecourse->id,  // Copying from here.
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'category' => $course->category,
                    'visible' => $matchingrecord->course_visibility,
                    'startdate' => $course->startdate,
                    'enddate' => $course->enddate,
                    'idnumber' => $course->idnumber,
                    'userdata' => '0',  // Do not copy user data.
                );

                foreach($roles as $role) {
                    //This has to both be set to role_{id} and then the value be the role {id}
                    //As the copy API code uses the value in the kept roles list
                    $dummyform['role_'.$role] = $role;
                }
               
                // Cast to stdClass object.
                $mdata = (object) $dummyform;

                $backupcopy = new \core_backup\copy\copy($mdata);
                $matchingrecord->copy_task_controllers = json_encode($backupcopy->create_copy(False));
                $DB->update_record('local_xmlsync_crsimport', $matchingrecord);
                return True;
            }
            else if ($templatecourse) {
                $trace->output("Copy is already in progress for {$course->fullname}, skipping");
                return False;
            }
            else {
                $trace->output("We could not find a matching template for {$course->fullname}, the template idnumber was {$matchingrecord->course_template}");
            }
        }
        return False;
    }
}
