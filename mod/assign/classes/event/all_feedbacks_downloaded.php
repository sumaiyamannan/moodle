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
 * The mod_assign all feedbacks downloaded event.
 *
 * @package    mod_assign
 * @author     Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\event;

/**
 * The mod_assign all feedbacks downloaded event class.
 *
 * @package    mod_assign
 * @copyright  2024 Sumaiya Javed
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class all_feedbacks_downloaded extends base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param \assign $assign
     * @return all_feedbacks_downloaded
     */
    public static function create_from_assign(\assign $assign) {
        $data = [
            'context' => $assign->get_context(),
            'objectid' => $assign->get_instance()->id,
        ];
        self::$preventcreatecall = false;
        /** @var feedback_graded $event */
        $event = self::create($data);
        self::$preventcreatecall = true;
        $event->set_assign($assign);
        return $event;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has downloaded all the feedbacks for the assignment " .
            "with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventallfeedbacksdownloaded', 'mod_assign');
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'assign';
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call all_feedbacks_downloaded::create() directly,
                use all_feedbacks_downloaded::create_from_assign() instead.');
        }

        parent::validate_data();
    }

    /**
     * Get object id mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'assign', 'restore' => 'assign'];
    }
}
