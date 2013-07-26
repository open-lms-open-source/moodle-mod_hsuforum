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
 * Discussion services
 *
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\service;

use mod_hsuforum\response\json_response;
use mod_hsuforum\upload_file;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__).'/response/json_response.php');
require_once(dirname(__DIR__).'/upload_file.php');
require_once(dirname(dirname(__DIR__)).'/lib.php');
require_once(dirname(dirname(__DIR__)).'/post_form.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_service {
    /**
     * @var \moodle_database
     */
    protected $db;

    public function __construct(\moodle_database $db = null) {
        global $DB;

        if (is_null($db)) {
            $db = $DB;
        }
        $this->db = $db;
    }

    /**
     * Does all the grunt work for adding a discussion
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param array $options These override default post values, EG: set the post message with this
     * @return json_response
     */
    public function handle_add_discussion($course, $cm, $forum, $context, array $options) {
        global $PAGE, $OUTPUT;

        $uploader = new upload_file(
            $context, \mod_hsuforum_post_form::attachment_options($forum), 'attachment'
        );

        $discussion = $this->create_discussion_object($forum, $context, $options);
        $errors = $this->validate_discussion($cm, $forum, $context, $discussion, $uploader);

        if (!empty($errors)) {
            /** @var \mod_hsuforum_renderer $renderer */
            $renderer = $PAGE->get_renderer('mod_hsuforum');

            return new json_response((object) array(
                'errors' => true,
                'html'   => $renderer->validation_errors($errors),
            ));
        }
        $this->save_discussion($discussion, $uploader);
        $this->trigger_discussion_created($course, $cm, $forum, $discussion);

        $message = get_string('postaddedsuccess', 'hsuforum');

        return new json_response((object) array(
            'discussionid'     => (int) $discussion->id,
            'livelog'          => $message,
            'notificationhtml' => $OUTPUT->notification($message, 'notifysuccess'),
        ));
    }

    /**
     * Creates the discussion object to be saved.
     *
     * @param object $forum
     * @param \context_module $context
     * @param array $options These override default post values, EG: set the post message with this
     * @return \stdClass
     */
    public function create_discussion_object($forum, $context, array $options = array()) {
        $discussion = (object) array(
            'name'          => '',
            'subject'       => '',
            'course'        => $forum->course,
            'forum'         => $forum->id,
            'groupid'       => -1,
            'timestart'     => 0,
            'timeend'       => 0,
            'message'       => '',
            'messageformat' => FORMAT_MOODLE,
            'messagetrust'  => trusttext_trusted($context),
            'mailnow'       => 0,
        );
        foreach ($options as $name => $value) {
            if (property_exists($discussion, $name)) {
                $discussion->$name = $value;
            }
        }
        return $discussion;
    }

    /**
     * Validates the submitted discussion and any submitted files
     *
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param upload_file $uploader
     * @return moodle_exception[]
     */
    public function validate_discussion($cm, $forum, $context, $discussion, upload_file $uploader) {
        $errors = array();
        if (!hsuforum_user_can_post_discussion($forum, $discussion->groupid, -1, $cm, $context)) {
            $errors[] = new \moodle_exception('nopostforum', 'hsuforum');
        }
        try {
            hsuforum_check_throttling($forum, $cm, false);
        } catch (\Exception $e) {
            $errors[] = $e;
        }
        $subject = trim($discussion->subject);
        if (empty($subject)) {
            $errors[] = new \moodle_exception('subjectisrequired', 'hsuforum');
        }
        $message = trim($discussion->message);
        if (empty($message)) {
            $errors[] = new \moodle_exception('messageisrequired', 'hsuforum');
        }
        if ($uploader->was_file_uploaded()) {
            try {
                $uploader->validate_files();
            } catch (\Exception $e) {
                $errors[] = $e;
            }
        }
        return $errors;
    }

    /**
     * Save the discussion to the DB
     *
     * @param object $discussion
     * @param upload_file $uploader
     */
    public function save_discussion($discussion, upload_file $uploader) {
        $message        = '';
        $discussion->id = hsuforum_add_discussion($discussion, null, $message);

        $file = $uploader->process_file_upload($discussion->firstpost);
        if (!is_null($file)) {
            $this->db->set_field('hsuforum_posts', 'attachment', 1, array('id' => $discussion->firstpost));
        }
    }

    /**
     * Log, update completion info and trigger event
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param object $discussion
     */
    public function trigger_discussion_created($course, $cm, $forum, $discussion) {
        global $CFG;

        require_once($CFG->libdir.'/completionlib.php');

        add_to_log($course->id, 'hsuforum', 'add discussion',
            "discuss.php?d=$discussion->id", $discussion->id, $cm->id);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completiondiscussions || $forum->completionposts)
        ) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        events_trigger('hsuforum_discussion_add', (object) array(
            'component'    => 'mod_hsuforum',
            'discussionid' => $discussion->id,
            'timestamp'    => time(),
            'userid'       => $discussion->userid,
            'forumid'      => $forum->id,
            'cmid'         => $cm->id,
            'courseid'     => $course->id,
        ));
    }

    /**
     * Get a discussion posts and related info
     *
     * @param $discussionid
     * @return array
     */
    public function get_posts($discussionid) {
        global $PAGE, $DB, $CFG, $COURSE, $USER;

        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum      = $PAGE->activityrecord;
        $course     = $COURSE;
        $cm         = $PAGE->cm;
        $context    = $PAGE->context;

        if ($forum->type == 'news') {
            if (!($USER->id == $discussion->userid || (($discussion->timestart == 0
                        || $discussion->timestart <= time())
                    && ($discussion->timeend == 0 || $discussion->timeend > time())))
            ) {
                print_error('invaliddiscussionid', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
            }
        }
        if (!$post = hsuforum_get_post_full($discussion->firstpost)) {
            print_error("notexists", 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }
        if (!hsuforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
            print_error('nopermissiontoview', 'hsuforum', "$CFG->wwwroot/mod/hsuforum/view.php?f=$forum->id");
        }

        $forumtracked = hsuforum_tp_is_tracked($forum);
        $posts        = hsuforum_get_all_discussion_posts($discussion->id, hsuforum_get_layout_mode_sort(HSUFORUM_MODE_NESTED), $forumtracked);
        $canreply     = hsuforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);

        hsuforum_get_ratings_for_posts($context, $forum, $posts);

        return array($cm, $discussion, $posts, $canreply);
    }

    /**
     * Render a discussion
     *
     * @param int $discussionid
     * @return string
     * @throws \coding_exception
     */
    public function render_discussion($discussionid) {
        global $PAGE;

        $displayformat = get_user_preferences('hsuforum_displayformat', 'header');

        /** @var $renderer \mod_hsuforum\render_interface */
        $renderer = $PAGE->get_renderer('mod_hsuforum', $displayformat);

        list($cm, $discussion, $posts, $canreply) = $this->get_posts($discussionid);

        if (!array_key_exists($discussion->firstpost, $posts)) {
            throw new \coding_exception('Failed to find discussion post');
        }
        return $renderer->discussion($cm, $discussion, $posts[$discussion->firstpost], $posts, $canreply);
    }
}