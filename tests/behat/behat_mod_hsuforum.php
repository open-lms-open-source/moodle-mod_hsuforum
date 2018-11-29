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

use Behat\Gherkin\Node\TableNode as TableNode;
use WebDriver\Key;

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
     * Adds a topic to the forum specified by it's name. Useful for the Announcements and blog-style forums.
     *
     * @Given /^I add a new topic to "(?P<hsuforum_name_string>(?:[^"]|\\")*)" Open Forum with:$/
     * @param string $forumname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_forum_with($forumname, TableNode $table) {
        $this->add_new_discussion($forumname, $table, get_string('addanewtopic', 'hsuforum'));
    }

    /**
     * Adds a discussion to the forum specified by it's name with the provided table data (usually Subject and Message). The step begins from the forum's course page.
     *
     * @Given /^I add a new discussion to "(?P<hsuforum_name_string>(?:[^"]|\\")*)" Open Forum with:$/
     * @param string $forumname
     * @param TableNode $table
     */
    public function i_add_a_forum_discussion_to_forum_with($forumname, TableNode $table) {
        $this->add_new_discussion($forumname, $table, get_string('addanewtopic', 'hsuforum'));
    }

    /**
     * Adds a reply to the specified post of the specified forum. The step begins from the forum's page or from the forum's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<hsuforum_name_string>(?:[^"]|\\")*)" Open Forum with:$/
     * @param string $postname The subject of the post
     * @param string $forumname The forum name
     * @param TableNode $table
     */
    public function i_reply_post_from_forum_with($postsubject, $forumname, TableNode $table) {
        // Navigate to forum.
        $this->execute('behat_general::click_link', $this->escape($forumname));
        $this->execute('behat_general::click_link', $this->escape($postsubject));
        $this->execute('behat_general::click_link', get_string('reply', 'hsuforum'));
        if ($this->running_javascript()) {
            $this->i_follow_href(get_string('useadvancededitor', 'hsuforum'));
        }

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);

        $this->execute('behat_forms::press_button', get_string('posttoforum', 'hsuforum'));
        $this->execute('behat_general::i_wait_to_be_redirected');

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
     */
    protected function add_new_discussion($forumname, TableNode $table, $buttonstr) {

        // Navigate to forum.
        $this->execute('behat_general::click_link', $this->escape($forumname));
        $this->execute('behat_forms::press_button', $buttonstr);

        if ($this->running_javascript()) {
            $this->i_follow_href(get_string('useadvancededitor', 'hsuforum'));
        }

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('posttoforum', 'hsuforum'));
        $this->execute('behat_general::i_wait_to_be_redirected');
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
        $roundedminutes = floor($minutes / 5) * 5;
        $minutefield->selectOption((string) $roundedminutes);
        $hourfield->selectOption(date('H', $value));
        $dayfield->selectOption(date('j', $value));
        $monthfield->selectOption(date('n', $value));
        $yearfield->selectOption(date('Y', $value));
    }

    /**
     * Checks the date (but not the time) of the timedate field matches the
     * value given.
     *
     * @Given /^I check the date field "(?P<field_string>(?:[^"]|\\")*)" is set to "(?P<field_value_string>(?:[^"]|\\")*)"$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $field
     * @param string $datestr
     * @return void
     */
    public function i_check_date_field_is($field, $datestr) {
        $value = strtotime($datestr);

        $dayfield = $this->find_field('id_'.$field.'_day');
        $monthfield = $this->find_field('id_'.$field.'_month');
        $yearfield = $this->find_field('id_'.$field.'_year');

        return $dayfield->getValue() == date('j', $value)
            && $monthfield->getValue() == date('n', $value)
            && $yearfield->getValue() == date('Y', $value);
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

        foreach ($table->getHash() as $row) {
            $this->execute('behat_forms::press_button', 'Add a new discussion');
            $this->execute('behat_forms::i_set_the_field_to', ['subject', $row['subject']]);
            $this->i_set_editable_div_to ('.hsuforum-textarea', 'css_element', $row['message']);

            if (isset($row['group'])) {
                $this->execute('behat_forms::i_set_the_field_to', ['groupinfo', $row['group']]);
            }

            if (isset($row['posttomygroups'])) {
                $this->execute('behat_forms::i_set_the_field_to', ['posttomygroups', $row['posttomygroups']]);
            }

            if (isset($row['timestart'])) {
                $this->execute('behat_forms::i_set_the_field_to', ['id_timestart_enabled', '1']);
                $this->i_set_date_field_to('timestart', $row['timestart']);
            }

            if (isset($row['timeend'])) {
                $this->execute('behat_forms::i_set_the_field_to', ['id_timeend_enabled', '1']);
                $this->i_set_date_field_to('timeend', $row['timeend']);
            }

            $this->execute('behat_forms::press_button', 'Submit');
            $this->execute('behat_general::assert_page_contains_text', 'post was successfully added');
        }
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
     * @Given /^Open Forums I upload file "(?P<fixturefilename_string>(?:[^"]|\\")*)" using input "(?P<input_string>(?:[^"]|\\")*)"$/
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
     * @Given /^Open Forums I upload image "(?P<link>(?:[^"]|\\")*)" using inline advanced editor$/
     */
    public function i_upload_image_using_inline_advanced_editor($fixturefilename) {
        $this->execute('behat_general::click_link', 'Use advanced editor');
        $this->execute('behat_forms::press_button', 'Image');
        $this->execute('behat_forms::press_button', 'Browse repositories...');
        $this->execute('behat_general::click_link', 'Upload a file');
        $this->i_upload_file_using_input($fixturefilename, 'input[name="repo_upload_file"]');
        $this->execute('behat_forms::press_button', 'Upload this file');
        $this->execute('behat_forms::i_set_the_field_to', ['Describe this image', 'Test fixture']);
        $this->execute('behat_forms::press_button', 'Save image');
    }

    /**
     * Upload image via inline advanced editor (TinyMCE).
     * @param string $fixturefilename
     *
     * @Given /^Open Forums I upload image "(?P<link>(?:[^"]|\\")*)" using inline advanced editor tinymce$/
     */
    public function i_upload_image_using_inline_advanced_editor_tinymce($fixturefilename) {
        $this->execute('behat_forms::press_button', 'Insert/edit image');
        $this->execute('behat_mod_hsuforum::i_find_and_switch_to_iframe', array('iframe[id^="mce_inlinepop"]', 'css'));
        $this->execute('behat_general::i_click_on', array('#srcbrowser_link', "css_element"));
        $this->execute('behat_general::switch_to_the_main_frame');
        $this->execute('behat_general::click_link', 'Upload a file');
        $this->i_upload_file_using_input($fixturefilename, 'input[name="repo_upload_file"]');
        $this->execute('behat_forms::press_button', 'Upload this file');
        $this->execute('behat_mod_hsuforum::i_find_and_switch_to_iframe', array('iframe[id^="mce_inlinepop"]', 'css'));
        $this->execute('behat_forms::i_set_the_field_to', ['Image description', 'Test fixture']);
        $this->execute('behat_forms::press_button', 'insert');
        $this->execute('behat_general::switch_to_the_main_frame');
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

    /**
     * Find and switches to the specified iframe, so this function acepts selectors like div[id^="value"].
     *
     * @Given /^I change focus to "(?P<iframe_name_string>(?:[^"]|\\")*)" iframe "(?P<selector_string>[^"]*)"$/
     * @param string $iframename
     */
    public function i_find_and_switch_to_iframe($iframename, $selectortype) {

        // We spin to give time to the iframe to be loaded.
        // Using extended timeout as we don't know about which
        // kind of iframe will be loaded.
        $node = $this->find($selectortype, $iframename);
        $iframename = $node->getAttribute('id');
        $this->spin(
            function($context, $iframename) {
                $context->getSession()->switchToIFrame($iframename);

                // If no exception we are done.
                return true;
            },
            $iframename,
            self::EXTENDED_TIMEOUT
        );
    }
}
