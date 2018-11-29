@mod @mod_hsuforum
Feature: In Open Forums a user can control their default discussion subscription settings
  In order to automatically subscribe to discussions
  As a user
  I can choose my default subscription preference

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                   | autosubscribe |
      | student1 | Student   | One      | student.one@example.com | 1             |
      | student2 | Student   | Two      | student.one@example.com | 0             |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Creating a new discussion in an optional forum follows user preferences
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Optional subscription |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    When I press "Add a new discussion"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I press "Add a new discussion"
    Then the "subscribe" select box should contain "I don't want to be notified of new posts in this forum"

  Scenario: Replying to an existing discussion in an optional Open Forum follows user preferences
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Test post subject"
    When I follow "Reply"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Test post subject"
    And I follow "Reply"
    Then the "subscribe" select box should contain "I don't want to be notified of new posts in this forum"

  Scenario: Creating a new discussion in an automatic forum follows forum subscription
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Auto subscription |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    When I press "Add a new discussion"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I press "Add a new discussion"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"

  Scenario: Replying to an existing discussion in an automatic forum follows forum subscription
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Test post subject"
    When I follow "Reply"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Test post subject"
    And I follow "Reply"
    Then the "subscribe" select box should contain "I don't want to be notified of new posts in this forum"

  Scenario: Replying to an existing discussion in an automatic forum which has been unsubscribed from follows user preferences
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I click on "subscribe" "link"
    And I follow "Test post subject"
    When I follow "Reply"
    Then the "subscribe" select box should contain "Send me notifications of new posts in this forum"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I click on "subscribe" "link"
    And I follow "Test post subject"
    And I follow "Reply"
    Then the "subscribe" select box should contain "I don't want to be notified of new posts in this forum"
