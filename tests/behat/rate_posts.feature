@mod @mod_hsuforum
Feature: Users can rate other users forum posts
  In order to rate forum posts
  As a user
  I need to choose a rating on their forum posts

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name     | Test forum name        |
      | Description    | Test forum description |
      | Aggregate type | Average of ratings     |

  @javascript
  Scenario: A teacher can edit another user's posts
    Given I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Student post subject |
      | Message | Student post message |
    And I log out
    And I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Student post subject"
    And I select "1" from the "rating" singleselect
    Then I should see "1 (1)"
    And I wait until the page is ready
    And I reply "Student post subject" post from "Test forum name" Open Forum with:
      | Subject | Teacher reply subject |
      | Message | Teacher reply message |
    Then I should see "1 (1)"
    Then I should not see "Rate" in the ".ratingsubmit" "css_element"
