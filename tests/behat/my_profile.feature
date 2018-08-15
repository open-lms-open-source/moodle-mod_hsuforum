@mod @mod_hsuforum
Feature: A user can see a link to their Open Forum posts in their profile
  In order to view my own or others advanced for posts
  As a user
  I need to click on the link in the profile page

Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |

  Scenario: A user can navigate between discussions
    Given the following "activities" exist:
      | activity | name            | intro           | course | idnumber | groupmode |
      | hsuforum | Test forum name | intro text      | C1     | hsuforum | 0         |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 3 |
      | Message | Test post message |
    And I follow "Discussion 1"
    When I follow "Profile" in the user menu
    And I follow "Open Forum discussions"
    Then I should see "Discussion 1"
    And I should see "Discussion 2"
    And I should see "Discussion 3"
    When I follow "Profile" in the user menu
    And I follow "Open Forum posts"
    Then I should see "Discussion 1"
    And I should see "Discussion 2"
    And I should see "Discussion 3"

