@mod @mod_hsuforum
Feature: Students can post anonymously or not if they choose
  In order to use anonymous forum posts
  As a user
  I need to post or edit and be able to choose my anonymous status

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity | name            | intro                  | course | idnumber | anonymous |
      | hsuforum | Test forum name | Test forum description | C1     | forum    | 1         |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Forum post subject |
      | Message | This is the body   |

 @javascript
  Scenario: Add forum post anonymously
    Given I follow "Forum post subject"
    And I follow "Reply"
    And I should see "Reveal yourself in this post"
    And I follow "Use advanced editor and additional options"
    When I set the following fields to these values:
      | Subject | Anon post subject |
      | Message | Anon post body    |
    And I press "Post to forum"
    And I wait to be redirected
    Then I should see "Anon post subject"
    And I should see "Anon post body"
    And I should not see "Anonymous User"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Forum post subject"
    And I should see "Anon post subject"
    And I should see "Anon post body"
    And I should see "Anonymous User"

 @javascript
  Scenario: Add forum post non-anonymously
    Given I follow "Forum post subject"
    And I follow "Reply"
    And I should see "Reveal yourself in this post"
    And I follow "Use advanced editor and additional options"
    When I set the following fields to these values:
      | Subject                      | Non-anon post subject |
      | Message                      | Non-anon post body    |
      | Reveal yourself in this post | 1                     |
    And I press "Post to forum"
    And I wait to be redirected
    Then I should see "Non-anon post subject"
    And I should see "Non-anon post body"
    And I should see "Non anonymously"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Forum post subject"
    And I should see "Non-anon post subject"
    And I should see "Non-anon post body"
    And I should see "Student 1"

 @javascript
  Scenario: Edit forum post from anon to non-anon
    Given I follow "Forum post subject"
    And I should not see "Non anonymously"
    And I follow "Edit"
    And I should see "Reveal yourself in this post"
    And I follow "Use advanced editor and additional options"
    When I set the following fields to these values:
      | Subject                      | Edited post subject |
      | Message                      | Edited post body    |
      | Reveal yourself in this post | 1                   |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"
    And I should see "Non anonymously"

