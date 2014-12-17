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
 * Post services
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_service {
    /**
     * @var \mod_hsuforum_renderer
     */
    protected $renderer;

    /**
     * Lazy load renderer
     *
     * @return \mod_hsuforum_renderer|\renderer_base
     */
    protected function get_renderer() {
        global $PAGE;

        if (!$this->renderer instanceof \mod_hsuforum_renderer) {
            $this->renderer = $PAGE->get_renderer('mod_hsuforum');
        }
        return $this->renderer;
    }

    public function prepare_message_for_edit($cm, $post) {

        $this->append_edited_by($post);

        $context = \context_module::instance($cm->id);
        $post    = trusttext_pre_edit($post, 'message', $context);
        $itemid  = file_get_submitted_draft_itemid('message');
        $message = file_prepare_draft_area($itemid, $context->id, 'mod_hsuforum', 'post',
            $post->id, \mod_hsuforum_post_form::editor_options($context, $post->id), $post->message);

        return array($message, $itemid);
    }

    /**
     * When editing a post, append editing information to the message
     *
     * @param object $post
     */
    protected function append_edited_by($post) {
        global $CFG, $USER, $COURSE;

        if ($USER->id != $post->userid) { // Not the original author, so add a message to the end
            $data       = new \stdClass();
            $data->date = userdate($post->modified);
            if ($post->messageformat == FORMAT_HTML) {
                $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$COURSE->id.'">'.
                    fullname($USER).'</a>';
                $post->message .= '<p><span class="edited">('.get_string('editedby', 'hsuforum', $data).')</span></p>';
            } else {
                $data->name = fullname($USER);
                $post->message .= "\n\n(".get_string('editedby', 'hsuforum', $data).')';
            }
            unset($data);
        }
    }

    /**
     * Create the edit form for a post
     *
     * @param object $cm
     * @param object $post
     * @return string
     */
    public function edit_post_form($cm, $post) {
        list($message, $itemid) = $this->prepare_message_for_edit($cm, $post);

        return $this->get_renderer()->simple_edit_post($cm, true, $post->id, array(
            'subject' => $post->subject,
            'message' => $message,
            'privatereply' => $post->privatereply,
            'reveal' => $post->reveal,
            'itemid'  => $itemid,
        ));
    }

    /**
     * Create an edit form for a discussion
     *
     * @param object $cm
     * @param object $discussion
     * @param object $post
     * @return string
     */
    public function edit_discussion_form($cm, $discussion, $post) {
        list($message, $itemid) = $this->prepare_message_for_edit($cm, $post);

        return $this->get_renderer()->simple_edit_discussion($cm, $post->id, array(
            'subject' => $post->subject,
            'message' => $message,
            'reveal' => $post->reveal,
            'groupid' => ($discussion->groupid == -1) ? 0 : $discussion->groupid,
            'itemid'  => $itemid,
        ));
    }
}
