@mod @mod_hsuforum
Feature: Teachers can edit or delete any Open Forum post
  In order to refine the forum contents
  As a teacher
  I need to edit or delete any user's forum posts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Description | Test forum description |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Teacher post subject |
      | Message | Teacher post message |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I reply "Teacher post subject" post from "Test forum name" Open Forum with:
      | Subject | Student post subject |
      | Message | Student post message |

  @javascript
  Scenario: A teacher can delete another user's posts
    Given I log out
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Teacher post subject"
    And I follow link "Delete" ignoring js onclick
    And I press "Continue"
    And I should see "Test forum description"
    Then I should not see "Student post subject"
    And I should not see "Student post message"

  @javascript
  Scenario: A teacher can edit another user's posts
    Given I log out
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Teacher post subject"
    And I click on "Edit" "link" in the "//li[contains(concat(' ', normalize-space(@class), ' '), ' hsuforum-post ')][contains(., 'Student post subject')]" "xpath_element"
    And I follow "Use advanced editor and additional options"
    And I set the following fields to these values:
      | Subject | Edited student subject |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited student subject"
    And I should see "Edited by Teacher 1 - original submission"

  @javascript
  Scenario: A student can't edit or delete another user's posts
    When I follow "Teacher post subject"
    Then I should not see "Edit" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' hsuforum-thread-tools ')]" "xpath_element"
    And I should not see "Delete" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' hsuforum-thread-tools ')]" "xpath_element"
