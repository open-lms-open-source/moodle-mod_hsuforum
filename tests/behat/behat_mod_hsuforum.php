<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Steps definitions related with the forum activity.
 *
 * @package    mod_hsuforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode,
    WebDriver\Key;
/**
 * Forum-related steps definitions.
 *
 * @package    mod_hsuforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_hsuforum extends behat_base {

    /**
     * Adds a topic to the forum specified by it's name. Useful for the News forum and blog-style forums.
     *
     * @Given /^I add a new topic to "(?P<hsuforum_name_string>(?:[^"]|\\")*)" advanced forum with:$/
     * @param string $forumname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_forum_with($forumname, TableNode $table) {
        return $this->add_new_discussion($forumname, $table, get_string('addanewtopic', 'hsuforum'));
    }

    /**
     * Adds a discussion to the forum specified by it's name with the provided table data (usually Subject and Message). The step begins from the forum's course page.
     *
     * @Given /^I add a new discussion to "(?P<hsuforum_name_string>(?:[^"]|\\")*)" advanced forum with:$/
     * @param string $forumname
     * @param TableNode $table
     */
    public function i_add_a_forum_discussion_to_forum_with($forumname, TableNode $table) {
        return $this->add_new_discussion($forumname, $table, get_string('addanewtopic', 'hsuforum'));
    }

    /**
     * Adds a reply to the specified post of the specified forum. The step begins from the forum's page or from the forum's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<hsuforum_name_string>(?:[^"]|\\")*)" advanced forum with:$/
     * @param string $postname The subject of the post
     * @param string $forumname The forum name
     * @param TableNode $table
     */
    public function i_reply_post_from_forum_with($postsubject, $forumname, TableNode $table) {
        $steps[] = new Given('I follow "' . $this->escape($forumname) . '"');
        $steps[] = new Given('I follow "' . $this->escape($postsubject) . '"');
        if ($this->running_javascript()) {
            $steps[] = new Given('I follow link "Use advanced editor" ignoring js onclick');
        }
        $steps[] = new Given('I set the following fields to these values:', $table);
        $steps[] = new Given('I press "' . get_string('posttoforum', 'hsuforum') . '"');
        $steps[] = new Given('I wait to be redirected');
        return $steps;

    }

    /**
     * Returns the steps list to add a new discussion to a forum.
     *
     * Abstracts add a new topic and add a new discussion, as depending
     * on the forum type the button string changes.
     *
     * @param string $forumname
     * @param TableNode $table
     * @param string $buttonstr
     * @return Given[]
     */
    protected function add_new_discussion($forumname, TableNode $table, $buttonstr) {

        $steps[] = new Given('I follow "' . $this->escape($forumname) . '"');
        $steps[] = new Given('I press "' . $buttonstr . '"');
        if ($this->running_javascript()) {
            $steps[] = new Given('I follow link "Use advanced editor" ignoring js onclick');
        }
        $steps[] = new Given('I set the following fields to these values:', $table);
        $steps[] = new Given('I press "' . get_string('posttoforum', 'hsuforum') . '"');
        $steps[] = new Given('I wait to be redirected');
        return $steps;
    }

    /**
     * Set regular field (not moodle form field) to a specific value.
     *
     * @Given /^I set editable div "([^"]*)" "([^"]*)" to "([^"]*)"$/
     * @param string $ellocator
     * @param string $selectortype
     * @param string $value
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function i_set_editable_div_to ($ellocator, $selectortype, $value) {
        // Getting Mink selector and locator.
        list($selector, $locator) = $this->transform_selector($selectortype, $ellocator);

        // Will throw an ElementNotFoundException if it does not exist.
        $div = $this->find($selector, $locator);

        // Need to empty div before setting value - as per https://github.com/minkphp/Mink/issues/520 which is
        // not in the moodle mink driver code.
        for ($i = 0; $i < strlen($div->getText()); $i++) {
            $value = Key::BACKSPACE . Key::DELETE . $value;
        }
        $div->setValue($value);
    }

    /**
     * Sets the specified value to the field.
     *
     * @Given /^I set the date field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)"$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $field
     * @param string $datestr
     * @return void
     */
    public function i_set_date_field_to($field, $datestr) {
        $value = strtotime($datestr);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $minutefield = $this->find_field('id_'.$field.'_minute');
        $hourfield = $this->find_field('id_'.$field.'_hour');
        $dayfield = $this->find_field('id_'.$field.'_day');
        $monthfield = $this->find_field('id_'.$field.'_month');
        $yearfield = $this->find_field('id_'.$field.'_year');

        $minutes = date('i', $value);
        $roundedminutes = ceil($minutes / 5) * 5;
        $minutefield->setValue($roundedminutes);
        $hourfield->setValue(date('H', $value));
        $dayfield->setValue(date('j', $value));
        $monthfield->setValue(date('n', $value));
        $yearfield->setValue(date('Y', $value));
    }

    /**
     * Adds an inline discussion to a forum.
     *
     * @Given /^I create the following inline discussions:$/
     * @param string $postname The subject of the post
     * @param string $forumname The forum name
     * @param TableNode $table
     */
    public function add_inline_discussions(TableNode $table) {
        $steps = [];

        foreach ($table->getHash() as $row) {
            $rowsteps[] = new Given ('I press "Add a new discussion"');
            $rowsteps[] = new Given ('I set the field "subject" to "' . $row['subject'] . '"');
            $rowsteps[] = new Given ('I set editable div ".hsuforum-textarea" "css_element" to "' . $row['message'] . '"');

            if (isset($row['group'])) {
                $rowsteps[] = new Given('I set the field "groupinfo" to "' . $row['group'] . '"');
            }

            if (isset($row['posttomygroups'])) {
                $rowsteps[] = new Given('I set the field "posttomygroups" to "' . $row['posttomygroups'] . '"');
            }

            if (isset($row['timestart'])) {
                $rowsteps[] = new Given('I set the field "id_timestart_enabled" to "1"');
                $rowsteps[] = new Given('I set the date field "timestart" to "' . $row['timestart'] . '"');
            }

            if (isset($row['timeend'])) {
                $rowsteps[] = new Given('I set the field "id_timeend_enabled" to "1"');
                $rowsteps[] = new Given('I set the date field "timeend" to "' . $row['timeend'] . '"');
            }

            $rowsteps[] = new Given ('I press "Submit"');
            $rowsteps[] = new Given ('I should see "Your post was successfully added."');
            $steps += $rowsteps;
        }

        return $steps;
    }

    /**
     * Bypass javascript attributed to link and just go straight to href.
     * @param string $link
     *
     * @Given /^I follow link "(?P<link>(?:[^"]|\\")*)" ignoring js onclick$/
     */
    public function i_follow_href($link) {
        $el = $this->find_link($link);
        $href = $el->getAttribute('href');
        $this->getSession()->visit($href);
    }

    /**
     * @param string $fixturefilename this is a filename relative to the snap fixtures folder.
     * @param string $input
     *
     * @Given /^Advanced Forums I upload file "(?P<fixturefilename_string>(?:[^"]|\\")*)" using input "(?P<input_string>(?:[^"]|\\")*)"$/
     */
    public function i_upload_file_using_input($fixturefilename, $input) {
        global $CFG;
        $fixturefilename = clean_param($fixturefilename, PARAM_FILE);
        $filepath = $CFG->dirroot.'/mod/hsuforum/tests/fixtures/'.$fixturefilename;
        $file = $this->find('css', $input);
        $file->attachFile($filepath);
    }

    /**
     * Upload image via inline advanced editor.
     * @param string $fixturefilename
     *
     * @Given /^Advanced Forums I upload image "(?P<link>(?:[^"]|\\")*)" using inline advanced editor$/
     */
    public function i_upload_image_using_inline_advanced_editor($fixturefilename) {
        $steps = [
            new Given('I follow "Use advanced editor"'),
            new Given('I click on ".atto_image_button" "css_element"'),
            new Given('I press "Browse repositories..."'),
            new Given('I click on "Upload a file" "link"'),
            new Given('Advanced Forums I upload file "'.$fixturefilename.'" using input "input[name=\"repo_upload_file\"]"'),
            new Given('I press "Upload this file"'),
            new Given('I set the field "Describe this image" to "Test fixture"'),
            new Given('I press "Save image"')
        ];
        return $steps;
    }

    /**
     * Image exists on page.
     * @param string $fixturefilename
     *
     * @Given /^Image "(?P<filename>(?:[^"]|\\")*)" should exist$/
     */
    public function image_exists($filename) {
        $images = $this->find_all('css', 'img[src*="'.$filename.'"]');
        return !empty($images);
    }
}
