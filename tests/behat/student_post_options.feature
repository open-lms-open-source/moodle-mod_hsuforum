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
# Tests for toggle course section visibility in non edit mode in snap.
#
# @package    mod_hsuforum
# @author     Rafael Becerra rafael.becerrarodriguez@blackboard.com
# @copyright  Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@mod @mod_hsuforum
Feature: In Open forums while using Boost, the student should note see the options
  to export the post, view the posters and subscribe or unsubscribe from the post, only
  manage forum subscriptions.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name            | intro      | course | idnumber | groupmode |
      | hsuforum | Test forum name | Test forum | C1     | hsuforum | 0         |

  @javascript
  Scenario: Check that the links for Open forums options does not exists

    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "li.modtype_hsuforum a" "css_element"
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Forum discussion 1                    |
      | Message | How awesome is this forum discussion? |
    And I should see "Manage forum subscriptions"
    And I should not see "Export"
    And I should not see "View posters"
    And I should not see "Unsubscribe from this forum"
