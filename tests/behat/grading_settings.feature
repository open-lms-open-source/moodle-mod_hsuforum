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
# Tests for course resource and activity editing features.
#
# @package    mod_hsuforum
# @author     Rafael Becerra rafael.becerrarodriguez@openlms.net
# @copyright  Copyright (c) 2020 Open LMS
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@mod @mod_hsuforum
Feature: While creating a new activity, the grade settings should remain in the Open forum settings.

  @javascript
  Scenario: Gradepass and Gradecat should remain saved in the settings page after the Open forum was created.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "grade categories" exist:
      | fullname | course |
      | Grade category 1 | C1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name  | Test forum name                |
      | Forum type  | Standard forum for general use |
      | Description | Test forum description         |
    And I follow "Test forum name"
    And I click on "#region-main-settings-menu .dropdown-toggle > .icon" "css_element"
    And I follow "Edit settings"
    And I click on "#page-mod-hsuforum-mod #id_modstandardgrade a[aria-controls='id_modstandardgrade']" "css_element"
    And I set the field with xpath "//fieldset[@id='id_modstandardgrade']//select[@id='id_gradetype']" to "Manual"
    # Save information into gradecat and gradepass to see if these values are being saved correctly.
    And I set the field with xpath "//fieldset[@id='id_modstandardgrade']//select[@id='id_gradecat']" to "Grade category 1"
    And I set the field with xpath "//fieldset[@id='id_modstandardgrade']//input[@id='id_gradepass']" to "20"
    # We need to click again grades menu to not interfere with the next validation.
    And I click on "#page-mod-hsuforum-mod #id_modstandardgrade a[aria-controls='id_modstandardgrade']" "css_element"
    # Check that gradepass and gradecat doesn't exists in the ratings menu.
    And I click on "#page-mod-hsuforum-mod #id_modstandardratings a[aria-controls='id_modstandardratings']" "css_element"
    And I should not see "Grade to pass"
    And I should not see "Grade category"
    And I press "Save and return to course"
    And I follow "Test forum name"
    And I click on "#region-main-settings-menu .dropdown-toggle>.icon" "css_element"
    And I follow "Edit settings"
    And I click on "#page-mod-hsuforum-mod #id_modstandardgrade a[aria-controls='id_modstandardgrade']" "css_element"
    And I should see "Grade category 1"
    And the "value" attribute of "//fieldset[@id='id_modstandardgrade']//input[@id='id_gradepass']" "xpath_element" should contain "20.00"
    And I press "Save and return to course"
    And I click on ".action-menu-trigger .dropdown-toggle > .icon" "css_element"
    And I follow "Gradebook setup"
    # Test forum name Open forum, should exist as a Grade category 1 which is the Grade category chosen before.
    And I should see "Grade category 1"
    And I should see "Test forum name"
    And "//h4[contains(text(), 'Grade category 1')]" "xpath_element" should exist
    And "//a[contains(text(), 'Test forum name')]" "xpath_element" should exist