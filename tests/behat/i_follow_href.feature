@mod @mod_hsuforum
Feature: Test that I follow href feature works

  @javascript
  Scenario: I follow courses
   Given I log in as "admin"
    When Advanced Forums I follow href "Courses"
     And I should see "Search courses"