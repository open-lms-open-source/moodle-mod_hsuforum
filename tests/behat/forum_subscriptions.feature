@mod @mod_hsuforum
Feature: A user can control their own subscription preferences for a Open Forum
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my forum subscriptions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student   | One      | student.one@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: A disallowed subscription forum cannot be subscribed to
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Subscription disabled |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    Then I should not see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist

  Scenario: A forced subscription forum cannot be subscribed to
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Forced subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    Then I should not see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist

  Scenario: An optional forum can be subscribed to
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    Then I should see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
    And I follow "Subscribe to this forum"
    And I should see "Unsubscribe from this forum"
    And I should not see "Subscribe to this forum"

  Scenario: An Automatic forum can be unsubscribed from
    Given I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name        | Test forum name |
      | Forum type        | Standard forum for general use |
      | Description       | Test forum description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    Then I should see "Unsubscribe from this forum"
    And I should not see "Subscribe to this forum"
    And I follow "Unsubscribe from this forum"
    And I should see "Subscribe to this forum"
    And I should not see "Unsubscribe from this forum"
