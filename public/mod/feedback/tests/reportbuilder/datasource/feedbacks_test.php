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

use core\clock;
use core_reportbuilder_generator;
use core_reportbuilder\local\filters\{date, text};
use core_reportbuilder\tests\core_reportbuilder_testcase;
use mod_feedback_generator;

/**
 * Unit tests for feedbacks datasource
 *
 * @package     mod_feedback
 * @covers      \mod_feedback\reportbuilder\datasource\feedbacks
 * @copyright   2026 Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedbacks_test extends core_reportbuilder_testcase {
    /** @var clock $clock */
    private readonly clock $clock;

    /**
     * Mock the clock
     */
    protected function setUp(): void {
        parent::setUp();
        $this->clock = $this->mock_clock_with_frozen(1622502000);
    }

    /**
     * Test default datasource
     */
    public function test_datasource_default(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);

        /** @var mod_feedback_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_feedback');
        $feedback = $generator->create_instance(['course' => $course->id, 'name' => 'My feedback']);
        $cm = get_coursemodule_from_instance('feedback', $feedback->id);
        $item = $generator->create_question(['cmid' => $cm->id, 'questiontype' => 'textfield']);
        $completed = $generator->create_response(['cmid' => $cm->id, 'userid' => $user->id, $item->name => 'My answer']);
        $DB->update_record('feedback_completed', [
            'id' => $completed->id,
            'timemodified' => $this->clock->now()->getTimestamp(),
        ]);
        /** @var core_reportbuilder_generator $reportgenerator */
        $reportgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $reportgenerator->create_report(['name' => 'Feedbacks', 'source' => feedbacks::class, 'default' => 1]);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertEquals(
            [
                [
                    'My feedback',
                    'Tuesday, 1 June 2021, 7:00 AM',
                    $course->fullname,
                    fullname($user),
                ],
            ],
            array_map('array_values', $content)
        );
    }

    /**
     * Test datasource columns that aren't added by default
     */
    public function test_datasource_non_default_columns(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course);

        /** @var mod_feedback_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_feedback');
        $feedback = $generator->create_instance(['course' => $course->id, 'idnumber' => 'FB1', 'name' => 'My feedback']);
        $cm = get_coursemodule_from_instance('feedback', $feedback->id);
        $item = $generator->create_question(['cmid' => $cm->id, 'questiontype' => 'textfield']);
        $generator->create_response(['cmid' => $cm->id, 'userid' => $user->id, $item->name => 'My answer']);

        /** @var core_reportbuilder_generator $reportgenerator */
        $reportgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $reportgenerator->create_report(['name' => 'Feedbacks', 'source' => feedbacks::class, 'default' => 0]);

        // Course category.
        $reportgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_category:name']);

        // Course.
        $reportgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course:fullname']);

        // Course module.
        $reportgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'course_module:idnumber']);

        // Feedback completed.
        $reportgenerator->create_column(['reportid' => $report->get('id'),
            'uniqueidentifier' => 'feedback_completed:feedbackname']);
        $reportgenerator->create_column(['reportid' => $report->get('id'),
            'uniqueidentifier' => 'feedback_completed:timemodified']);

        // User.
        $reportgenerator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullname']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        [$catname, $coursename, $idnumber, $feedbackname, $timemodified, $username] = array_values($content[0]);
        $this->assertEquals($category->get_formatted_name(), $catname);
        $this->assertEquals($course->fullname, $coursename);
        $this->assertEquals('FB1', $idnumber);
        $this->assertEquals('My feedback', $feedbackname);
        $this->assertEquals(fullname($user), $username);
        $this->assertNotEmpty($timemodified);
    }

    /**
     * Data provider for {@see test_datasource_filters}
     *
     * @return array[]
     */
    public static function datasource_filters_provider(): array {
        return [
            // Course category.
            'Course category name' => ['course_category:text', [
                'course_category:text_operator' => text::IS_EQUAL_TO,
                'course_category:text_value' => 'My category',
            ], true],
            'Course category name (no match)' => ['course_category:text', [
                'course_category:text_operator' => text::IS_EQUAL_TO,
                'course_category:text_value' => 'Another category',
            ], false],

            // Course.
            'Course fullname' => ['course:fullname', [
                'course:fullname_operator' => text::IS_EQUAL_TO,
                'course:fullname_value' => 'My course',
            ], true],
            'Course fullname (no match)' => ['course:fullname', [
                'course:fullname_operator' => text::IS_EQUAL_TO,
                'course:fullname_value' => 'Another course',
            ], false],

            // Course module.
            'Course module ID number' => ['course_module:idnumber', [
                'course_module:idnumber_operator' => text::IS_EQUAL_TO,
                'course_module:idnumber_value' => 'FB1',
            ], true],
            'Course module ID number (no match)' => ['course_module:idnumber', [
                'course_module:idnumber_operator' => text::IS_EQUAL_TO,
                'course_module:idnumber_value' => 'FB2',
            ], false],

            // Feedback completed.
            'Feedback name' => ['feedback_completed:feedbackname', [
                'feedback_completed:feedbackname_operator' => text::IS_EQUAL_TO,
                'feedback_completed:feedbackname_value' => 'My feedback',
            ], true],
            'Feedback name (no match)' => ['feedback_completed:feedbackname', [
                'feedback_completed:feedbackname_operator' => text::IS_EQUAL_TO,
                'feedback_completed:feedbackname_value' => 'Another feedback',
            ], false],
            'Feedback time modified' => ['feedback_completed:timemodified', [
                'feedback_completed:timemodified_operator' => date::DATE_RANGE,
                'feedback_completed:timemodified_from' => 1622502000,
            ], true],
            'Feedback time modified (no match)' => ['feedback_completed:timemodified', [
                'feedback_completed:timemodified_operator' => date::DATE_RANGE,
                'feedback_completed:timemodified_to' => 1622502000,
            ], false],

            // User.
            'User firstname' => ['user:firstname', [
                'user:firstname_operator' => text::IS_EQUAL_TO,
                'user:firstname_value' => 'Zoe',
            ], true],
            'User firstname (no match)' => ['user:firstname', [
                'user:firstname_operator' => text::IS_EQUAL_TO,
                'user:firstname_value' => 'Alice',
            ], false],
        ];
    }

    /**
     * Test datasource filters
     *
     * @param string $filtername
     * @param array $filtervalues
     * @param bool $expectmatch
     *
     * @dataProvider datasource_filters_provider
     */
    public function test_datasource_filters(string $filtername, array $filtervalues, bool $expectmatch): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category(['name' => 'My category']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id, 'fullname' => 'My course']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Zoe']);

        /** @var mod_feedback_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_feedback');
        $feedback = $generator->create_instance([
            'course' => $course->id,
            'idnumber' => 'FB1',
            'name' => 'My feedback',
        ]);
        $cm = get_coursemodule_from_instance('feedback', $feedback->id);
        $item = $generator->create_question(['cmid' => $cm->id, 'questiontype' => 'textfield']);
        $completed = $generator->create_response(['cmid' => $cm->id, 'userid' => $user->id, $item->name => 'My answer']);
        $DB->update_record('feedback_completed', [
            'id' => $completed->id,
            'timemodified' => $this->clock->time() + DAYSECS,
        ]);
        /** @var core_reportbuilder_generator $reportgenerator */
        $reportgenerator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $reportgenerator->create_report(['name' => 'Feedbacks', 'source' => feedbacks::class, 'default' => 0]);
        $reportgenerator->create_column(['reportid' => $report->get('id'),
            'uniqueidentifier' => 'feedback_completed:feedbackname']);
        $reportgenerator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filtername]);
        $content = $this->get_custom_report_content($report->get('id'), 0, $filtervalues);

        if ($expectmatch) {
            $this->assertEquals([['My feedback']], array_map('array_values', $content));
        } else {
            $this->assertEmpty($content);
        }
    }

    /**
     * Stress test datasource
     *
     * In order to execute this test PHPUNIT_LONGTEST should be defined as true in phpunit.xml or directly in config.php
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course);

        /** @var mod_feedback_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_feedback');
        $feedback = $generator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('feedback', $feedback->id);
        $item = $generator->create_question(['cmid' => $cm->id, 'questiontype' => 'textfield']);
        $generator->create_response(['cmid' => $cm->id, 'userid' => $user->id, $item->name => 'My answer']);

        $this->datasource_stress_test_columns(feedbacks::class);
        $this->datasource_stress_test_columns_aggregation(feedbacks::class);
        $this->datasource_stress_test_conditions(feedbacks::class, 'feedback_completed:feedbackname');
    }
}
