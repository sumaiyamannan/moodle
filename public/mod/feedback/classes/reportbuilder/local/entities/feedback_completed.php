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

namespace mod_feedback\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, text};
use core_reportbuilder\local\helpers\database;

/**
 * Feedback completed entity class implementation
 *
 * Defines all the columns and filters that can be added to reports that use this entity.
 *
 * @package    mod_feedback
 * @copyright  2026 Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_completed extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'feedback_completed',
            'feedback',
            'feedback_item',
            'feedback_value',
            'course_modules',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityfeedbackcompleted', 'mod_feedback');
    }

    /**
     * Initialize the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        // Add course_modules join.
        $talias = $this->get_table_alias('feedback_completed');
        $cmalias = $this->get_table_alias('course_modules');
        $falias = $this->get_table_alias('feedback');
        $this->add_join("LEFT JOIN {feedback} {$falias} ON {$falias}.id = {$talias}.feedback")
            ->add_join("LEFT JOIN {course_modules} {$cmalias} ON {$cmalias}.instance = {$falias}.id AND {$cmalias}.module = "
                . "(SELECT id FROM {modules} WHERE name = 'feedback')");

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        global $DB;

        $talias = $this->get_table_alias('feedback_completed');
        $falias = $this->get_table_alias('feedback');
        $qalias = $this->get_table_alias('feedback_item');
        $ralias = $this->get_table_alias('feedback_value');

        // Time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'mod_feedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_fields("{$talias}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        // Feedback name column.
        $columns[] = (new column(
            'feedbackname',
            new lang_string('feedbackname', 'mod_feedback'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_feedback_join())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$falias}.name")
            ->set_is_sortable(true);

        $questions = $DB->get_record_sql("SELECT MAX(position) FROM {feedback_item}");
        if (isset ($questions->max)) {
            [$param] = database::generate_param_names(1);
            for ($pos = 1; $pos <= $questions->max; $pos++) {
                // Question column.
                $sql = "(SELECT {$qalias}.name FROM {feedback_item} {$qalias}
                        JOIN {feedback_value} {$ralias} ON {$qalias}.id = {$ralias}.item
                        WHERE {$ralias}.completed = {$talias}.id AND {$qalias}.position = :{$param})";
                $columns[] = (new column(
                    "question{$pos}",
                    new lang_string('questionno', 'mod_feedback', ['pos' => $pos]),
                    $this->get_entity_name()
                ))
                    ->add_joins($this->get_joins())
                    ->set_type(column::TYPE_TEXT)
                    ->add_field($sql, "question{$pos}", [$param => $pos])
                    ->set_is_sortable(true);

                // Answer column.
                $sql = "(SELECT {$ralias}.value || '_' || {$qalias}.typ || '_' || {$qalias}.id
                        FROM {feedback_item} {$qalias}
                        JOIN {feedback_value} {$ralias} ON {$qalias}.id = {$ralias}.item
                        WHERE {$ralias}.completed = {$talias}.id AND {$qalias}.position = :{$param})";
                $columns[] = (new column(
                    "response{$pos}",
                    new lang_string('responseno', 'mod_feedback', ['pos' => $pos]),
                    $this->get_entity_name()
                ))
                    ->add_joins($this->get_joins())
                    ->set_type(column::TYPE_TEXT)
                    ->add_field($sql, "response{$pos}", [$param => $pos])
                    ->add_callback(static function ($value): string {
                        global $DB, $CFG;
                        if (empty($value)) {
                            return '';
                        }
                        [$response, $typ, $itemid] = explode('_', $value);
                        require_once($CFG->dirroot . '/mod/feedback/lib.php');
                        $itemobj = feedback_get_item_class($typ);
                        $item = $DB->get_record('feedback_item', ['id' => $itemid]);
                        $printval = $itemobj->get_printval($item, (object) ['value' => $response]);
                        $value = trim($printval);
                        return $value;
                    });
            }
        }

        return $columns;
    }

    /**
     * Return context join used by columns
     *
     * @return string
     */
    private function get_feedback_join(): string {

        $talias = $this->get_table_alias('feedback_completed');

        // If the context table is already joined, we don't need to do that again.
        if ($this->has_table_join_alias('feedback')) {
            return '';
        }

        $falias = $this->get_table_alias('feedback');

        return "LEFT JOIN {feedback} {$falias} ON {$falias}.id = {$talias}.feedback";
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $talias = $this->get_table_alias('feedback_completed');
        $falias = $this->get_table_alias('feedback');

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'mod_feedback'),
            $this->get_entity_name(),
            "{$talias}.timemodified"
        ))
            ->add_joins($this->get_joins())
            ->set_limited_operators([
                date::DATE_ANY,
                date::DATE_RANGE,
                date::DATE_PREVIOUS,
                date::DATE_CURRENT,
            ]);

        // Feedback name filter.
        $filters[] = (new filter(
            text::class,
            'feedbackname',
            new lang_string('feedbackname', 'mod_feedback'),
            $this->get_entity_name(),
            "{$falias}.name"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
