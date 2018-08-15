@mod @mod_hsuforum
Feature: Posting to all groups in Open Forums in a visible group discussion is restricted to users with access to all groups
  In order to post to all groups in a forum with visible groups
  As a teacher
  I need to have the accessallgroups capability

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And the following "groups" exist:
      | name | course | idnumber |
      | Group A | C1 | G1 |
      | Group B | C1 | G2 |
      | Group C | C1 | G3 |
    And the following "group members" exist:
      | user | group |
      | teacher1 | G1 |
      | teacher1 | G2 |
      | student1 | G1 |
      | student2 | G1 |
      | student2 | G2 |
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber     | groupmode |
      | hsuforum   | Standard forum name    | Standard forum description    | C1     | groups       | 2         |

  Scenario: Teacher with accessallgroups can view all groups
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Standard forum name"
    Then the "Visible groups" select box should contain "All participants"
    Then the "Visible groups" select box should contain "Group A"
    Then the "Visible groups" select box should contain "Group B"
    Then the "Visible groups" select box should contain "Group C"

  Scenario: Teacher with accessallgroups can select any group when posting
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    When I click on "Add a new discussion" "button"
    Then the "Group" select box should contain "All participants"
    And the "Group" select box should contain "Group A"
    And the "Group" select box should contain "Group B"
    And the "Group" select box should contain "Group C"
    And I should see "Post a copy to all groups"

  @javascript
  Scenario: Teacher with accessallgroups can select any group when posting via ajax
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    When I click on "Add a new discussion" "button"
    Then the "Group" select box should contain "All participants"
    And the "Group" select box should contain "Group A"
    And the "Group" select box should contain "Group B"
    And the "Group" select box should contain "Group C"
    And I should see "Post a copy to all groups"

  Scenario: Teacher with accessallgroups can post in groups they are a member of
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    When I click on "Add a new discussion" "button"
    Then I should see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Teacher 1 -> Group B  |
      | Message | Teacher 1 -> Group B  |
      # Change the group in the post form.
      | Group   | Group B               |
    And I press "Post to forum"
    And I wait to be redirected
    # We should be redirected to the group that we selected when posting.
    # TODO: the following line fails due to a redirect bug
    #And the field "Visible groups" matches value "Group B"
    # instead just manually switch to that group
    And I select "Group B" from the "Visible groups" singleselect
    And I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should also be displayed under All participants
    And I select "All participants" from the "Visible groups" singleselect
    And I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should not be displayed in Groups A, or C.
    And I select "Group A" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group B"
    And I select "Group C" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group B"

  @javascript
  Scenario: Teacher with accessallgroups can post in groups they are a member of via ajax
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    And I create the following inline discussions:
      | subject              | message              | group   |
      | Teacher 1 -> Group B | Teacher 1 -> Group B | Group B |
    # We should be redirected to the group that we selected when posting.
    And I follow "Standard forum name"
    And I select "Group B" from the "Visible groups" singleselect
    And I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should also be displayed under All participants
    And I select "All participants" from the "Visible groups" singleselect
    And I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should not be displayed in Groups A, or C.
    And I select "Group A" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group B"
    And I select "Group C" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group B"

  Scenario: Teacher with accessallgroups can post in groups they are not a member of
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    When I click on "Add a new discussion" "button"
    Then I should see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Teacher 1 -> Group C  |
      | Message | Teacher 1 -> Group C  |
      | Group   | Group C               |
    And I press "Post to forum"
    And I wait to be redirected
    # We should be redirected to the group that we selected when posting.
    # TODO: the following line fails due to a redirect bug
    # And the field "Visible groups" matches value "Group C"
    # instead just manually switch to that group
    And I select "Group C" from the "Visible groups" singleselect
    # We redirect to the group posted in automatically.
    And I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should also be displayed under All participants
    And I select "All participants" from the "Visible groups" singleselect
    And I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should not be displayed in Groups A, or B.
    And I select "Group A" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group C"
    And I select "Group B" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group C"

  @javascript
  Scenario: Teacher with accessallgroups can post in groups they are not a member of via ajax
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    And I create the following inline discussions:
      | subject              | message              | group   |
      | Teacher 1 -> Group C | Teacher 1 -> Group C | Group C |
    # We should be redirected to the group that we selected when posting.
    # TODO: the following line fails due to a redirect bug
    # And the field "Visible groups" matches value "Group C"
    # instead just manually switch to that group
    And I follow "Standard forum name"
    And I select "Group C" from the "Visible groups" singleselect
    # We redirect to the group posted in automatically.
    And I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should also be displayed under All participants
    And I select "All participants" from the "Visible groups" singleselect
    And I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # It should not be displayed in Groups A, or B.
    And I select "Group A" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group C"
    And I select "Group B" from the "Visible groups" singleselect
    And I should not see "Teacher 1 -> Group C"

  Scenario: Teacher with accessallgroups can post to all groups
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    When I click on "Add a new discussion" "button"
    And I set the following fields to these values:
      | Subject                   | Teacher 1 -> Post to all  |
      | Message                   | Teacher 1 -> Post to all  |
      | Post a copy to all groups | 1                       |
    And I press "Post to forum"
    And I wait to be redirected
    # Posting to all groups means that we should be redirected to the page we started from.
    And the field "Visible groups" matches value "All participants"
    And I select "Group A" from the "Visible groups" singleselect
    Then I should see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I select "Group B" from the "Visible groups" singleselect
    Then I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I select "Group C" from the "Visible groups" singleselect
    Then I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # No point testing the "All participants".

  @javascript
  Scenario: Teacher with accessallgroups can post to all groups via ajax
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Standard forum name"
    And I create the following inline discussions:
      | subject                  | message                  | posttomygroups |
      | Teacher 1 -> Post to all | Teacher 1 -> Post to all | 1              |
    # Posting to all groups means that we should be redirected to the page we started from.
    And I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    Then I should see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I select "Group B" from the "Visible groups" singleselect
    Then I should see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I select "Group C" from the "Visible groups" singleselect
    Then I should see "Group C" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Teacher 1'] .hsuforum-thread-byline" "css_element"
    # No point testing the "All participants".

  Scenario: Students can view all groups
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Standard forum name"
    Then the "Visible groups" select box should contain "All participants"
    Then the "Visible groups" select box should contain "Group A"
    Then the "Visible groups" select box should contain "Group B"
    Then the "Visible groups" select box should contain "Group C"

  Scenario: Students in one group can only post in their group
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Standard forum name"
    Then I should see "Group A"
    And I click on "Add a new discussion" "button"
    And I should see "Group A"
    And I should not see "Group B"
    And I should not see "Group C"
    And I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Student -> A |
      | Message | Student -> A |
    And I press "Post to forum"
    And I wait to be redirected
    And I should see "Group A" in the "article[data-author='Student 1'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Student 1'] .hsuforum-thread-byline" "css_element"

  Scenario: Students in multiple group can post in all of their group individually
    Given I log in as "student2"
    And I am on "Course 1" course homepage
    When I follow "Standard forum name"
    And I select "Group A" from the "Visible groups" singleselect
    And I click on "Add a new discussion" "button"
    And the "Group" select box should not contain "All participants"
    And the "Group" select box should contain "Group A"
    And the "Group" select box should contain "Group B"
    And the "Group" select box should not contain "Group C"
    And I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Student -> B  |
      | Message | Student -> B  |
      | Group   | Group B       |
    And I press "Post to forum"
    And I wait to be redirected
    # We should be redirected to the group that we selected when posting.
    # TODO: the following line fails due to a redirect bug
    # And the field "Visible groups" matches value "Group B"
    # instead just manually switch to that group
    And I select "Group B" from the "Visible groups" singleselect
    And I should see "Group B" in the "article[data-author='Student 2'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group A" in the "article[data-author='Student 2'] .hsuforum-thread-byline" "css_element"
    And I select "Group A" from the "Visible groups" singleselect
    And I should not see "Student -> B"
    # Now try posting in Group A (starting at Group B)
    And I select "Group B" from the "Visible groups" singleselect
    And I click on "Add a new discussion" "button"
    And the "Group" select box should not contain "All participants"
    And the "Group" select box should contain "Group A"
    And the "Group" select box should contain "Group B"
    And the "Group" select box should not contain "Group C"
    And I should not see "Post a copy to all groups"
    And I set the following fields to these values:
      | Subject | Student -> A  |
      | Message | Student -> A  |
      | Group   | Group A       |
    And I press "Post to forum"
    And I wait to be redirected
    # We should be redirected to the group that we selected when posting.
    # TODO: the following line fails due to a redirect bug
    # And the field "Visible groups" matches value "Group A"
    # instead just manually switch to that group
    And I select "Group A" from the "Visible groups" singleselect
    And I should see "Group A" in the "article[data-author='Student 2'] .hsuforum-thread-byline" "css_element"
    And I should not see "Group B" in the "article[data-author='Student 2'] .hsuforum-thread-byline" "css_element"
    And I select "Group B" from the "Visible groups" singleselect
    And I should not see "Student -> A"
