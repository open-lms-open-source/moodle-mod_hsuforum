@mod @mod_hsuforum
Feature: A user can navigate to previous and next discussions in Open Forums
  In order to get go the previous discussion
  As a user
  I need to click on the previous discussion link

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
    And the following "groups" exist:
      | name | course | idnumber |
      | Group 1 | C1 | G1 |
      | Group 2 | C1 | G2 |
    And the following "group members" exist:
      | user | group |
      | teacher1 | G1 |
      | teacher1 | G2 |
      | student1 | G1 |
      | student2 | G2 |

  @javascript
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
    And I wait "1" seconds
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 |
      | Message | Test post message |
    And I wait "1" seconds
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 3 |
      | Message | Test post message |
    When I follow "Discussion 3"
    Then I should not see "Discussion 1"
    And I should see "Discussion 2"
    And I follow "Discussion 2"
    And I should see "Discussion 1"
    And I should see "Discussion 3"
    And I follow "Discussion 1"
    And I should see "Discussion 2"
    And I should not see "Discussion 3"
    And I follow "Reply"
    And I follow "Use advanced editor and additional options"
    And I set the following fields to these values:
      | Message | Answer to discussion |
    And I press "Post to forum"
    And I wait to be redirected
    And I should not see "Discussion 2"
    And I should see "Discussion 3"
    And I follow "Discussion 3"
    And I should see "Discussion 1"
    And I should see "Discussion 2"
    And I follow "Discussion 2"
    And I should not see "Discussion 1"
    And I should see "Discussion 3"

  @javascript
    Scenario: A user can navigate between discussions with visible groups
    Given the following "activities" exist:
      | activity | name            | intro           | course | idnumber | groupmode |
      | hsuforum | Test forum name | intro  text     | C1     | hsuforum | 2         |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 0 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 0 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 1 |
      | Message | Test post message |
      | Group   | Group 1 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 1 |
      | Message | Test post message |
      | Group   | Group 1 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 2 |
      | Message | Test post message |
      | Group   | Group 2 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 2 |
      | Message | Test post message |
      | Group   | Group 2 |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I select "All participants" from the "Visible groups" singleselect
    And I follow "Discussion 1 Group 0"
    Then I should see "Discussion 2 Group 0"
    And I should not see "Group 1"
    And I should not see "Group 2"
    And I follow "Discussion 2 Group 0"
    And I should see "Discussion 1 Group 0"
    And I should see "Discussion 1 Group 1"
    And I follow "Discussion 1 Group 1"
    And I should see "Discussion 2 Group 0"
    And I should see "Discussion 2 Group 1"
    And I follow "Test forum name"
    And I follow "Discussion 1 Group 2"
    And I should see "Discussion 2 Group 1"
    And I should see "Discussion 2 Group 2"
    And I follow "Test forum name"
    And I select "Group 1" from the "Visible groups" singleselect
    And I follow "Discussion 1 Group 1"
    Then I should see "Discussion 2 Group 0"
    And I should see "Discussion 2 Group 1"
    And I follow "Discussion 2 Group 1"
    And I should see "Discussion 1 Group 1"
    And I should not see "Group 2"

  @javascript
  Scenario: A user can navigate between discussions with separate groups
    Given the following "activities" exist:
      | activity | name            | intro           | course | idnumber | groupmode |
      | hsuforum | Test forum name | intro text      | C1     | hsuforum | 1         |
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 0 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 0 |
      | Message | Test post message |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 1 |
      | Message | Test post message |
      | Group   | Group 1 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 1 |
      | Message | Test post message |
      | Group   | Group 1 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 Group 2 |
      | Message | Test post message |
      | Group   | Group 2 |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 2 Group 2 |
      | Message | Test post message |
      | Group   | Group 2 |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Discussion 1 Group 1"
    Then I should see "Discussion 2 Group 0"
    And I should see "Discussion 2 Group 1"
    And I follow "Discussion 2 Group 1"
    And I should see "Discussion 1 Group 1"
    And I should not see "Group 2"
