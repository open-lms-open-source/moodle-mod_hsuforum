@mod @mod_hsuforum
Feature: In Open Forums as a teacher I need to see an accurate list of subscribed users
  In order to see who is subscribed to a forum
  As a teacher
  I need to view the list of subscribed users

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher  | Teacher   | Teacher  | teacher@example.com |
      | student1 | Student   | 1        | student.1@example.com |
      | student2 | Student   | 2        | student.2@example.com |
      | student3 | Student   | 3        | student.3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher  | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
    And I log in as "teacher"
    And I am on "Course 1" course homepage with editing mode on

  @javascript
  Scenario: A forced forum lists all subscribers
    When I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Forced Forum 1 |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Forced subscription |
    And I follow "Forced Forum 1"
    And I navigate to "Show/edit forum subscribers" in current page administration
    Then I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should see "Student 2"
    And I should see "Student 3"

  Scenario: A forced forum does not allow to edit the subscribers
    When I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Forced Forum 2 |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Forced subscription |
      | Availability      | Show on course page |
    And I follow "Forced Forum 2"
    And I navigate to "Show/edit forum subscribers" in current page administration
    Then I should see "Teacher Teacher"
    And I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should not see "Manage subscribers"

  Scenario: A forced and hidden forum lists only teachers
    When I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Forced Forum 2 |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Forced subscription |
      | Availability      | Hide from students |
    And I follow "Forced Forum 2"
    And I navigate to "Show/edit forum subscribers" in current page administration
    Then I should see "Teacher Teacher"
    And I should not see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"

  @javascript
  Scenario: An automatic forum lists all subscribers
    When I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Forced Forum 1 |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Auto subscription |
    And I follow "Forced Forum 1"
    And I navigate to "Show/edit forum subscribers" in current page administration
    Then I should see "Student 1"
    And I should see "Teacher Teacher"
    And I should see "Student 2"
    And I should see "Student 3"
