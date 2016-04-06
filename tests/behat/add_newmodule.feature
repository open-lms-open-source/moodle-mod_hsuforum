@mod @mod_newmodule
Feature: Add a newmodule to a course
  In order to use mod_newmodule
  As a teacher
  I need to create a newmodule

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Newmodule" to section "1" and I fill the form with:
      | Name        | Test newmodule name        |
      | Description | Test newmodule description |
    # add any form fields required by mod_newmodule here
    And I log out

    Scenario: Student view of newmodule
    Given I log in as "student1"
    And I follow "Course 1"
    When I follow "Test newmodule name"
    # Modify the following section to suit your module
    # see the following examples for ideas:
    # mod/choice/tests/behat/add_choice.feature
    # mod/forum/tests/behat/add_forum.feature
    # mod/scorm/tests/behat/add_scorm.feature
    # mod/quiz/tests/behat/add_quiz.feature
    Then I should see "Test newmodule name"
