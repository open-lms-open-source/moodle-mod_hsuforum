@mod @mod_hsuforum
Feature: Teachers and students can use the advanced editor for inline discussions and posts
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
    And I add a "Advanced Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I log out

  @javascript
  Scenario: Teacher can add / edit discussions and posts with message containing image set by enhanced editor
    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And I set the field "subject" to "test discussion subject"
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion body"
    And Advanced Forums I upload image "testgif_small.gif" using inline advanced editor
    And I press "Submit"
   Then I should see "Your post was successfully added."
    And Image "testgif_small.gif" should exist
    And I follow "test discussion subject"
    And I follow "Edit"
    And Advanced Forums I upload image "testgif2_small.gif" using inline advanced editor
    And I press "Submit"
   Then Image "testgif2_small.gif" should exist
    And I follow "test discussion subject"
    And I set the field "subject" to "test post subject"
    And I set editable div ".hsuforum-textarea" "css_element" to "Test post body"
    And Advanced Forums I upload image "testgif3_small.gif" using inline advanced editor
    And I press "Submit"
    And Image "testgif3_small.gif" should exist

  @javascript
  Scenario: Student can add / edit discussions and posts with message containing image set by enhanced editor
    When I log in as "student1"
    And I follow "Course 1"
    And I follow "Test forum name"
    And I wait until the page is ready
    And I press "Add a new discussion"
    And I set the field "subject" to "test discussion subject"
    And I set editable div ".hsuforum-textarea" "css_element" to "Test discussion body"
    And Advanced Forums I upload image "testgif_small.gif" using inline advanced editor
    And I press "Submit"
    Then I should see "Your post was successfully added."
    And ".posting img" "css_element" should exist
    And I follow "test discussion subject"
    And I follow "Edit"
    And Advanced Forums I upload image "testgif2_small.gif" using inline advanced editor
    And I press "Submit"
    Then ".posting img:nth-of-type(2)" "css_element" should exist
    And I follow "test discussion subject"
    And I set the field "subject" to "test post subject"
    And I set editable div ".hsuforum-textarea" "css_element" to "Test post body"
    And Advanced Forums I upload image "testgif3_small.gif" using inline advanced editor
    And I press "Submit"
    And ".posting:nth-of-type(2) img" "css_element" should exist
