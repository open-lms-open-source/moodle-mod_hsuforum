@mod @mod_hsuforum
Feature: Teachers and students can create time released discussions
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
    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Moodlerooms Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I log out

 @javascript
  Scenario: Teacher can add timed discussion to forum and student can only see the ones that are currently accessible.
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready

    And I create the following inline discussions:
        | subject                          | message     | timestart    | timeend      |
        | Currently accessible discussion  | testing 123 | now - 1 week | now + 1 week |
        | Currently restricted discussion  | testing abc | now + 1 week | now + 2 week |

    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I should see "Currently accessible discussion"
    And I should not see "Currently restricted discussion"

  @javascript
  Scenario: Dates are not carried over when creating several discussions.
    When the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And the following config values are set as admin:
      | texteditors | atto, textarea |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And the following fields do not match these values:
      |timestart[enabled]| 1 |
      |timeend[enabled]  | 1 |
    And I set the field "subject" to "Test discussion"
    And I set editable div ".hsuforum-textarea" "css_element" to "Text..."
    And I set the field "timestart[enabled]" to "1"
    And I set the field "timeend[enabled]" to "1"
    And I set the date field "timestart" to "10 September 2000"
    And I set the date field "timeend" to "11 September 2000"
    And I press "Submit"
    And I should see "Your post was successfully added."
    And I press "Add a new discussion"
    And I check the date field "timestart" is set to "today"
    And I check the date field "timeend" is set to "today"
    And the following fields do not match these values:
      |timestart[enabled]|   1  |
      |timeend[enabled]  |   1  |
