@mod @mod_hsuforum
Feature: In Open Forums users can change start and end date and the changes remain
  As a teacher
  I need to set a discussion time start and time end and it should be maintained through time

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And the following config values are set as admin:
      | enabletimedposts | 1 | hsuforum |
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Description | Test forum description |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Discussion 1 |
      | Message | Discussion contents 1, first message |
      | timestart[enabled] | 1 |
      | timestart[year]    | 2014 |
      | timeend[enabled] | 1 |
      | timeend[year]    | 2020 |
    And I log out

  @javascript
  Scenario: Teacher should see the start and end date after editing post
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I follow "Discussion 1"
    And I click on "//div[contains(@class, 'hsuforum-thread-tools')]//a[contains(text(), 'Edit')]" "xpath_element"
    And I click on "//form[contains(@class, 'hsuforum-discussion')]//button[contains(@type, 'submit')]" "xpath_element"
    And I click on "//div[contains(@class, 'hsuforum-thread-tools')]//a[contains(text(), 'Edit')]" "xpath_element"
    And I follow "Use advanced editor and additional options"
    Then "input[name='timestart[enabled]'][checked]" "css_element" should exist
    And "input[name='timeend[enabled]'][checked]" "css_element" should exist
    And the following fields match these values:
      | timestart[year]    | 2014 |
      | timeend[year]    | 2020 |
    Then I set the following fields to these values:
      | timestart[enabled] | 0 |
    Then I press "Save changes"
    Then I click on "//div[contains(@class, 'hsuforum-thread-tools')]//a[contains(text(), 'Edit')]" "xpath_element"
    And I follow "Use advanced editor and additional options"
    Then "input[name='timestart[enabled]'][checked]" "css_element" should not exist
    And "input[name='timeend[enabled]'][checked]" "css_element" should exist
    And the following fields match these values:
      | timeend[year]    | 2020 |
