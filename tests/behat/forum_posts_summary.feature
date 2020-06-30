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
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# Tests for showing the correct name when config values are set
#
# @package    mod_hsuforum
# @author     Juan Ibarra <juan.ibarra@openlms.net>
# @copyright  Copyright (c) 2020 Open LMS (https://www.openlms.net)
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@mod @mod_hsuforum
Feature: Recent forum posts summary
  As a student or teacher
  I see my name shown in the respect viewfullname capability

  Background:
    Given the following config values are set as admin:
      | fullnamedisplay | firstname lastname |
      | alternativefullnameformat | lastname firstname |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name            | intro      | course | idnumber | groupmode |
      | hsuforum | Test forum name | Test forum | C1     | hsuforum | 0         |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I wait until the page is ready
    And I follow "Test forum name"
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Forum discussion 1                    |
      | Message | How awesome is this forum discussion? |
    And I log out

  @javascript
  Scenario: Check that the links for Open forums options exists and can be activated
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    Then "//div[contains(@class, 'hsuforum-recent')]//h5[contains(text(), '1 Student')]" "xpath_element" should exist
    And I click on "//div[contains(@class, 'hsuforum-recent')]//a" "xpath_element"
    Then "//div[contains(@class, 'hsuforum-thread-author')]//a[contains(text(), '1 Student')]" "xpath_element" should exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then "//div[contains(@class, 'activityinstance')]//h5[contains(text(), 'Student 1')]" "xpath_element" should exist
    And I click on "//div[contains(@class, 'hsuforum-recent')]//a" "xpath_element"
    Then "//div[contains(@class, 'hsuforum-thread-author')]//a[contains(text(), 'Student 1')]" "xpath_element" should exist