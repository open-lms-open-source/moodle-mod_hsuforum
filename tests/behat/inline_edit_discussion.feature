@mod @mod_hsuforum
Feature: Teachers and students can edit discussions
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
      | teacher1 | C1 | teacher |
    And the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "admin"
    And I am on homepage
    And I follow "Courses"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Advanced Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I log out

 @javascript
  Scenario Outline: Teacher can add and edit a discussion to forum without timed posts enabled with any editor set.
    When the following config values are set as admin:
      | enabletimedposts | 0 | hsuforum |
    And the following config values are set as admin:
      | texteditors | <editororder> |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."
    And I follow "Test discussion 1"
    And I follow "Edit"
    And I set editable div " .hsuforum-discussion .hsuforum-textarea" "css_element" to "Test discussion 1 description edited"
    And I press "Submit"
    And I should see "Test discussion 1 description edited"

   Examples:
   | editororder |
   | atto,tinymce,textarea |
   | tinymce,atto,textarea |
   | textarea,tinymce,atto |

  @javascript
  Scenario: Student can add discussion to forum without timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 0 | hsuforum |
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

  @javascript
  Scenario: Teacher can add discussion to forum with timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

  @javascript
  Scenario: Student can add discussion to forum with timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I create the following inline discussions:
      | subject            | message                       |
      | Test discussion 1  | Test discussion 1 description |
    And I should see "Your post was successfully added."

  @javascript
  Scenario: Teacher can add discussion and then cancel and then finally add without timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 0 | hsuforum |
    And I log in as "teacher1"
    And I follow "Course 1"
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
  Scenario: Student can add discussion and then cancel and then finally add without timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 0 | hsuforum |
    And I log in as "student1"
    And I follow "Course 1"
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
  Scenario: Teacher can add discussion and then cancel and then finally add with timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "teacher1"
    And I follow "Course 1"
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
  Scenario: Student can add discussion and then cancel and then finally add with timed posts enabled
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "student1"
    And I follow "Course 1"
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

