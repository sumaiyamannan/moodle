@mod @mod_quiz
Feature: Students can review the history of an attempt
  As a student
  In order to learn better
  I need to be able to see the response history as part of the quiz review

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                |
      | student1  | Student   | One      | student1@example.com |
      | teacher   | Teacher   | One      | teacher@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | teacher  | C1     | teacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | First question  |
      | Test questions   | truefalse | TF2  | Second question |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber | responsehistory |
      | quiz     | Quiz 1 | Quiz 1 description | C1     | quiz1    |                 |
    And quiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 1               |
      | TF2      | 2    | 1               |
    And I am on the "Quiz 1" "mod_quiz > View" page logged in as "student1"
    And I press "Attempt quiz"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I log out   

  @javascript
  Scenario: As a student I can see the response history
    Given I am on the "Quiz 1 > student1 > Attempt 1" "mod_quiz > Attempt review" page logged in as "student1"
    Then I should see "Response history"

