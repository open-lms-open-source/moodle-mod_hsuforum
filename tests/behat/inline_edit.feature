@mod @mod_hsuforum
Feature: Teachers and students can add discussions inline

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | teacher1 | C1 | editingteacher |
    And the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I log out

  @javascript
  Scenario: Student can add discussion to forum with inline form
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I wait until the page is ready
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

  @javascript
  Scenario: Teacher can add discussion inline and then cancel and then finally add
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And I should see "Add your discussion"
    And I set the field "subject" to "Test discussion 1 to be cancelled"
    And ".hsuforum-textarea" "css_element" should exist
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion 1 to be cancelled description"
    And I follow "Cancel"
    And I should not see "Add your discussion"
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

  @javascript
  Scenario: Student can add discussion inline and then cancel and then finally add
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And I should see "Add your discussion"
    And I set the field "subject" to "Test discussion 1 to be cancelled"
    And ".hsuforum-textarea" "css_element" should exist
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion 1 to be cancelled description"
    And I follow "Cancel"
    And I should not see "Add your discussion"
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

