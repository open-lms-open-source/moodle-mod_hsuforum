@mod @mod_hsuforum
Feature: Test that I follow href feature works

  @javascript
  Scenario: I follow courses
   Given I log in as "admin"
    When I follow link "Courses" ignoring js onclick
     And I should see "Search courses"