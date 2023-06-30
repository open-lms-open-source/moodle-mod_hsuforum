@mod @mod_hsuforum
Feature: Users see their typed information in the advanced editor view when click "Use advanced editor"

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "user preferences" exist:
      | user   | preference   | value    |
      | teacher1  | htmleditor   | atto |
      | student1  | htmleditor   | atto |
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
  Scenario: User can continue writing after clicking "Use advanced editor"
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I change window size to "large"
    # This does no longer work with Moodle 4.2
    # And I follow "Test forum name"
    # But this works:
    And I click on ".aalink:contains('Test forum name')" "css_element"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And I should see "Add your discussion"
    And I set the field "subject" to "Test discussion 1 to be cancelled"
    And ".hsuforum-textarea" "css_element" should exist
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion 1 to be cancelled description"
    And I follow "Use advanced editor and additional options"
    And I wait until the page is ready
    And I should not see "Add your discussion"
    And I should see "Your new discussion topic"
    And I set the field with xpath "//*[@id='id_subject']" to "Test discussion 1 to be cancelled again"
    And I set the field with xpath "//*[@id='id_messageeditable']" to "Test discussion 1 to be cancelled description again"
    And I press "Post to forum"
    Then I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test forum name"
    And I wait until the page is ready
    And I follow "Test discussion 1 to be cancelled"
    Then I should see "Add your reply"
    And I set the field "subject" to "Test reply subject"
    And I set editable div ".hsuforum-textarea" "css_element" to "Test reply message"
    And I follow "Use advanced editor and additional options"
    And I wait until the page is ready
    And I should see "Your reply"
    And I set the field with xpath "//*[@id='id_subject']" to "Test reply subject"
    And I set the field with xpath "//*[@id='id_messageeditable']" to "Test reply message"
    And I press "Post to forum"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
#    And I follow "Test forum name"
    And I click on ".aalink:contains('Test forum name')" "css_element"
    And I follow "Test discussion 1 to be cancelled"
    And I wait until the page is ready
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion 1 to be cancelled description"
    And I click on ".hsuforum-post.depth0 .hsuforum-reply-link" "css_element"
    Then I should see "Reply to Student 1"
    And I set editable div with break line ".hsuforum-post.depth0 .hsuforum-textarea" "css_element" to "This is a reply \n This is a new line"
    And I click on ".hsuforum-post.depth0 .hsuforum-use-advanced" "css_element"
    And I wait until the page is ready
    And ".text_to_html br" "css_element" should exist
    And I should see "Your reply"
    And I set the field with xpath "//*[@id='id_messageeditable']" to "This is a reply"