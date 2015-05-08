@mod @mod_hsuforum
Feature: Students can edit or delete their forum posts within a set time limit
  In order to refine forum posts
  As a user
  I need to edit or delete my forum posts within a certain period of time after posting

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@asd.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Security" node
    And I follow "Site policies"
    And I set the field "Maximum time to edit posts" to "1 minutes"
    And I press "Save changes"
    And I am on homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Advanced Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I log out
    And I follow "Course 1"
    And I log in as "student1"
    And I add a new discussion to "Test forum name" advanced forum with:
      | Subject | Forum post subject |
      | Message | This is the body |

 @javascript
  Scenario: Edit forum post
    When I follow "Forum post subject"
    And I follow "Edit"
    And I follow "Use advanced editor"
    And I set the following fields to these values:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"

  @javascript @_alert
  Scenario: Delete forum post
    When I follow "Forum post subject"
    And I click on "Delete" "link" confirming the dialogue
    Then I should not see "Forum post subject"

  @javascript
  Scenario: Time limit expires
    # TODO there's got to be a better way than adding an 80 second delay.
    # Previously this value was 70 seconds but it was faliing on my local setup.
    When I wait "80" seconds
    And I follow "Forum post subject"
    Then I should not see "Edit"
    And I should not see "Delete"
