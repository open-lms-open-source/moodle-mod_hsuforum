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
 * Forum post renderable.
 *
 * @package    mod_hsuforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\output\email;

defined('MOODLE_INTERNAL') || die();

// To ensure class is picked up during unit tests.
require_once($CFG->dirroot . '/mod/hsuforum/renderer.php');

/**
 * Forum post renderable.
 *
 * @since      Moodle 3.0
 * @package    mod_hsuforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \mod_hsuforum_renderer {

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function hsuforum_post_template() {
        return 'hsuforum_post_email_htmlemail';
    }

    /**
     * The HTML version of the e-mail message.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_text($cm, $post) {
        $includetoken = (defined('PHPUNIT_TEST') && PHPUNIT_TEST) ? false : true;
        $context = \context_module::instance($cm->id);
        $message = file_rewrite_pluginfile_urls(
            $post->message,
            'pluginfile.php',
            $context->id,
            'mod_hsuforum',
            'post',
            $post->id,
            [
                'includetoken' => $includetoken,
            ]);
        $options = new \stdClass();
        $options->para = true;
        $options->context = $context;
        return format_text($message, $post->messageformat, $options);
    }

    /**
     * The HTML version of the attachments list.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_attachments($cm, $post) {
        return hsuforum_print_attachments($post, $cm, "html");
    }
}
