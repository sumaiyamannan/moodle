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

declare(strict_types=1);

namespace mod_feedback\reportbuilder\datasource;

use core_course\reportbuilder\local\entities\{course_category, course_module};
use mod_feedback\reportbuilder\local\entities\feedback_completed;
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};

/**
 * Feedbacks responses datasource
 *
 * @package    mod_feedback
 * @copyright  2026 Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedbacks extends datasource {
    /**
     * Return user friendly name of the report source
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('feedbacks', 'mod_feedback');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {

        // Our main entity.
        $mainentity = new feedback_completed();
        [
            'feedback_completed' => $mainalias,
            'course_modules' => $coursemodulesalias,
            'feedback' => $feedbackalias,
        ] = $mainentity->get_table_aliases();

        $this->set_main_table('feedback_completed', $mainalias);
        $this->add_entity($mainentity
            ->add_join("LEFT JOIN {feedback} {$feedbackalias} ON {$feedbackalias}.id = {$mainalias}.feedback"));

        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity
            ->add_joins($mainentity->get_joins())
            ->add_join("LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$feedbackalias}.course"));

        // Join the course category entity.
        $coursecatentity = new course_category();
        $coursecatalias = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_joins($courseentity->get_joins())
            ->add_join("LEFT JOIN {course_categories} {$coursecatalias} ON {$coursecatalias}.id = {$coursealias}.category"));

        // Join the course module entity.
        $coursemodentity = (new course_module())
            ->set_table_alias('course_modules', $coursemodulesalias);
        $this->add_entity($coursemodentity
            ->add_joins($mainentity->get_joins()));

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$mainalias}.userid"));

        // Add report elements from each of the entities we added to the report.
        $this->add_all_from_entities([
            $mainentity->get_entity_name(),
            $coursecatentity->get_entity_name(),
            $courseentity->get_entity_name(),
            $coursemodentity->get_entity_name(),
            $userentity->get_entity_name(),
        ]);
    }

    /**
     * Return the columns that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'feedback_completed:feedbackname',
            'feedback_completed:timemodified',
            'course:fullname',
            'user:fullname',
        ];
    }

    /**
     * Return the column sorting that will be added to the report upon creation
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [];
    }

    /**
     * Return the filters that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'feedback_completed:feedbackname',
            'feedback_completed:timemodified',
            'course:fullname',
        ];
    }

    /**
     * Return the conditions that will be added to the report upon creation
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
