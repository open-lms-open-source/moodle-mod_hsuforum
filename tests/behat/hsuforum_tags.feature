# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle. If not, see <http://www.gnu.org/licenses/>.
#
# Tests for course resource and activity editing features.
#
# @package    mod_hsuforum
# @copyright  Copyright (c) 2017 Open LMS (http://www.openlms.net)
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@mod @mod_hsuforum @core_tag
Feature: Open forum posts and new discussions handle tags correctly, in order to get forum posts or discussions labelled.
  As a user I need to introduce the tags while creating, editing, or replying.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Description | Test forum description |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Teacher post subject |
      | Message | Teacher post message |
    And I log out
    Given I log in as "admin"
    And I navigate to "Appearance > Manage tags" in site administration
    And I follow "Default collection"
    And I follow "Add standard tags"
    And I set the field "Enter comma-separated list of new tags" to "OT1, OT2, OT3"
    And I press "Continue"
    And I log out

  @javascript
  Scenario: Forum post edition of custom tags works as expected
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I reply "Teacher post subject" post from "Test forum name" Open Forum with:
      | Subject | Student post subject |
      | Message | Student post message |
      | Tags    | Tag1                 |
    Then I should see "Tag1" in the ".forum-tags" "css_element"
    And I click on "Edit" "link" in the "//div[@data-author='Student 1']" "xpath_element"
    And I follow "Use advanced editor and additional options"
    Then I should see "Tag1" in the ".form-autocomplete-selection" "css_element"

  @javascript
  Scenario: Forum post edition of standard tags works as expected
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I am on the "Test forum name" "hsuforum activity" page
    And I click on "Add a new discussion" "button"
    And I follow "Use advanced editor and additional options"
    And I expand all fieldsets
    And I click on ".form-autocomplete-downarrow" "css_element"
    And I should see "OT1" in the ".form-autocomplete-suggestions" "css_element"
    And I should see "OT2" in the ".form-autocomplete-suggestions" "css_element"
    And I should see "OT3" in the ".form-autocomplete-suggestions" "css_element"
    And I reply "Teacher post subject" post from "Test forum name" Open Forum with:
      | Subject | Student post subject |
      | Message | Student post message |
      | Tags | OT1, OT3 |
    Then I should see "OT1" in the ".forum-tags" "css_element"
    And I should see "OT3" in the ".forum-tags" "css_element"
    And I should not see "OT2" in the ".forum-tags" "css_element"
    And I click on "Edit" "link" in the "//div[@data-author='Teacher 1'][@data-ispost='true']" "xpath_element"
    And I follow "Use advanced editor and additional options"
    And I should see "OT1" in the ".form-autocomplete-selection" "css_element"
    And I should see "OT3" in the ".form-autocomplete-selection" "css_element"
    And I should not see "OT2" in the ".form-autocomplete-selection" "css_element"

  @javascript
  Scenario: Tags are displayed in a discussion that was just created.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I am on the "Test forum name" "hsuforum activity" page
    And I click on "Add a new discussion" "button"
    And I follow "Use advanced editor and additional options"
    And I expand all fieldsets
    And I click on ".form-autocomplete-downarrow" "css_element"
    And I should see "OT1" in the ".form-autocomplete-suggestions" "css_element"
    And I should see "OT2" in the ".form-autocomplete-suggestions" "css_element"
    And I should see "OT3" in the ".form-autocomplete-suggestions" "css_element"
    And I set the field "Subject" to "Subject test"
    And I set the field "Message" to "Message test"
    And I set the field "Tags" to "OT1"
    And I press "Post to forum"
    And I follow "Subject test"
    And I should see "OT1"