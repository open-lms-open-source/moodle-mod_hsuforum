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
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\service;

use mod_hsuforum\attachments;
use mod_hsuforum\event\post_created;
use mod_hsuforum\event\post_updated;
use mod_hsuforum\response\json_response;
use mod_hsuforum\upload_file;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__).'/response/json_response.php');
require_once(dirname(__DIR__).'/upload_file.php');
require_once(dirname(dirname(__DIR__)).'/lib.php');

/**
 * @package   mod_hsuforum
 * @copyright Copyright (c) 2013 Blackboard Inc. (http://www.blackboard.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_service {
    /**
     * @var discussion_service
     */
    protected $discussionservice;

    /**
     * @var \moodle_database
     */
    protected $db;

    public function __construct(discussion_service $discussionservice = null, \moodle_database $db = null) {
        global $DB;

        if (is_null($discussionservice)) {
            $discussionservice = new discussion_service();
        }
        if (is_null($db)) {
            $db = $DB;
        }
        $this->discussionservice = $discussionservice;
        $this->db = $db;
    }

    /**
     * Does all the grunt work for adding a reply to a discussion
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $parent The parent post
     * @param array $options These override default post values, EG: set the post message with this
     * @return json_response
     */
    public function handle_reply($course, $cm, $forum, $context, $discussion, $parent, array $options) {
        $uploader = new upload_file(
            new attachments($forum, $context), \mod_hsuforum_post_form::attachment_options($forum)
        );

        $post   = $this->create_post_object($discussion, $parent, $context, $options);
        $errors = $this->validate_post($course, $cm, $forum, $context, $discussion, $post, $uploader);

        if (!empty($errors)) {
            return $this->create_error_response($errors);
        }
        $this->save_post($discussion, $post, $uploader, $cm);
        $this->trigger_post_created($course, $context, $cm, $forum, $discussion, $post);
        $this->handle_user_autosubscription($forum, $post);

        return new json_response((object) array(
            'eventaction'  => 'postcreated',
            'discussionid' => (int) $discussion->id,
            'postid'       => (int) $post->id,
            'livelog'      => get_string('postcreated', 'hsuforum'),
            'html'         => $this->discussionservice->render_full_thread($discussion->id),
        ));
    }

    /**
     * This function handles the scenario when a forum is set with optional subscription
     * and the user posting a reply has autosubscription set in his forum preferences.
     *
     * @param object $forum
     * @param object $post
     */
    public function handle_user_autosubscription($forum, $post) {
        global $USER;

        if (isset($forum->forcesubscribe) && $forum->forcesubscribe == HSUFORUM_CHOOSESUBSCRIBE) {
            if ($USER->autosubscribe) {
                hsuforum_subscribe($USER->id, $post->forum);
            }
        }
    }

    /**
     * Does all the grunt work for updating a post
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     * @param array $deletefiles
     * @param array $options These override default post values, EG: set the post message with this
     * @return json_response
     */
    public function handle_update_post($course, $cm, $forum, $context, $discussion, $post, array $deletefiles = array(), array $options) {

        $this->require_can_edit_post($forum, $context, $discussion, $post);

        $uploader = new upload_file(
            new attachments($forum, $context, $deletefiles), \mod_hsuforum_post_form::attachment_options($forum)
        );

        // Apply updates to the post.
        foreach ($options as $name => $value) {
            if (property_exists($post, $name)) {
                $post->$name = $value;
            }
        }
        $post->itemid = empty($options['itemid']) ? 0 : $options['itemid'];

        // If this post is actually the discussion, then update timestart and timeend.
        If (intval($post->parent) === 0) {
            if (isset($options['timestart'])) {
                $discussion->timestart = $options['timestart'];
            }
            if (isset($options['timeend'])) {
                $discussion->timeend = $options['timeend'];
            }
        }

        $errors = $this->validate_post($course, $cm, $forum, $context, $discussion, $post, $uploader);
        if (!empty($errors)) {
            return $this->create_error_response($errors);
        }
        $this->save_post($discussion, $post, $uploader, $cm);

        // If the user has access to all groups and they are changing the group, then update the post.
        if (empty($post->parent) && has_capability('mod/hsuforum:movediscussions', $context)) {
            $this->db->set_field('hsuforum_discussions', 'groupid', $options['groupid'], array('id' => $discussion->id));
        }

        $this->trigger_post_updated($context, $forum, $discussion, $post);

        return new json_response((object) array(
            'eventaction'  => 'postupdated',
            'discussionid' => (int) $discussion->id,
            'postid'       => (int) $post->id,
            'livelog'      => get_string('postwasupdated', 'hsuforum'),
            'html'         => $this->discussionservice->render_full_thread($discussion->id),
        ));
    }

    /**
     * Require that the current user can edit the post or
     * discussion
     *
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     */
    public function require_can_edit_post($forum, \context_module $context, $discussion, $post) {
        global $CFG, $USER;

        if (!($forum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
            if (((time() - $post->created) > $CFG->maxeditingtime) and
                !has_capability('mod/hsuforum:editanypost', $context)
            ) {
                print_error('maxtimehaspassed', 'hsuforum', '', format_time($CFG->maxeditingtime));
            }
        }
        if (($post->userid <> $USER->id) && !has_capability('mod/hsuforum:editanypost', $context)) {
            print_error('cannoteditposts', 'hsuforum');
        }
    }

    /**
     * Creates the post object to be saved.
     *
     * @param object $discussion
     * @param object $parent The parent post
     * @param \context_module $context
     * @param array $options These override default post values, EG: set the post message with this
     * @return \stdClass
     */
    public function create_post_object($discussion, $parent, $context, array $options = array()) {
        $post                = new \stdClass;
        $post->course        = $discussion->course;
        $post->forum         = $discussion->forum;
        $post->discussion    = $discussion->id;
        $post->parent        = $parent->id;
        $post->reveal        = 0;
        $post->privatereply  = 0;
        $post->mailnow       = 0;
        $post->subject       = $parent->subject;
        $post->attachment    = '';
        $post->message       = '';
        $post->messageformat = FORMAT_MOODLE;
        $post->messagetrust  = trusttext_trusted($context);
        $post->itemid        = 0; // For text editor stuffs.
        $post->groupid       = ($discussion->groupid == -1) ? 0 : $discussion->groupid;
        $post->flags         = null;
        $post->deleted       = 0;

        $strre = get_string('re', 'hsuforum');
        if (!(\core_text::substr($post->subject, 0, \core_text::strlen($strre)) == $strre)) {
            $post->subject = $strre.' '.$post->subject;
        }

        // Make sure post subject does not go beyond 255 chars.
        $post->subject = \mod_hsuforum\local::shorten_post_name($post->subject);

        foreach ($options as $name => $value) {
            if (property_exists($post, $name)) {
                $post->$name = $value;
            }
        }
        return $post;
    }

    /**
     * Validates the submitted post and any submitted files
     *
     * @param object $course
     * @param object $cm
     * @param object $forum
     * @param \context_module $context
     * @param object $discussion
     * @param object $post
     * @param upload_file $uploader
     * @return moodle_exception[]
     */
    public function validate_post($course, $cm, $forum, $context, $discussion, $post, upload_file $uploader) {
        global $USER;

        $errors = array();
        if (!hsuforum_user_can_post($forum, $discussion, null, $cm, $course, $context)) {
            $errors[] = new \moodle_exception('nopostforum', 'hsuforum');
        }
        if (!empty($post->id)) {
            if (!(($post->userid == $USER->id && (has_capability('mod/hsuforum:replypost', $context)
                        || has_capability('mod/hsuforum:startdiscussion', $context))) ||
                has_capability('mod/hsuforum:editanypost', $context))
            ) {
                $errors[] = new \moodle_exception('cannotupdatepost', 'hsuforum');
            }
        }
        if (empty($post->id)) {
            $thresholdwarning = hsuforum_check_throttling($forum, $cm);
            if ($thresholdwarning !== false && $thresholdwarning->canpost === false) {
                $errors[] = new \moodle_exception($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
            }
        }
        if (hsuforum_str_empty($post->subject)) {
            $errors[] = new \moodle_exception('subjectisrequired', 'hsuforum');
        }
        if (hsuforum_str_empty($post->message)) {
            $errors[] = new \moodle_exception('messageisrequired', 'hsuforum');
        }

        if ($discussion->timestart && $discussion->timeend && $discussion->timestart > $discussion->timeend) {
            $errors[] = new \moodle_exception('errortimestartgreater', 'hsuforum');
        }

        if ($post->privatereply) {
            if (!has_capability('mod/hsuforum:allowprivate', $context)
                || !$forum->allowprivatereplies
            ) {
                $errors[] = new \moodle_exception('cannotmakeprivatereplies', 'hsuforum');
            }
        }

        if ($uploader->was_file_uploaded()) {
            try {
                $uploader->validate_files(empty($post->id) ? 0 : $post->id);
            } catch (\Exception $e) {
                $errors[] = $e;
            }
        }
        return $errors;
    }

    /**
     * Save the post to the DB
     *
     * @param object $discussion
     * @param object $post
     * @param upload_file $uploader
     * @param \cm_info $cm
     */
    public function save_post($discussion, $post, upload_file $uploader, \cm_info $cm) {
        global $DB;

        $message = '';

        // Because the following functions require these...
        $post->forum     = $discussion->forum;
        $post->course    = $discussion->course;
        $post->timestart = $discussion->timestart;
        $post->timeend   = $discussion->timeend;

        $draftid = optional_param('hiddenadvancededitordraftid', false, PARAM_INT);
        if (!empty($post->id)) {
            hsuforum_update_post($post, null, $message, $uploader);
        } else {
            $postid = hsuforum_add_new_post($post, null, $message, $uploader);
            $post = $DB->get_record('hsuforum_posts', ['id' => $postid]);
        }
        // Update post record via db, can't call hsuforum_update_post as it will process file uploads again.
        if ($draftid) {
            $post->message = file_save_draft_area_files($draftid, $cm->context->id, 'mod_hsuforum', 'post',
                $post->id, array('subdirs' => true), $post->message);
            $DB->update_record('hsuforum_posts', (object)['id' => $post->id, 'message' => $post->message]);
        }
    }

    /**
     * Update completion info and trigger event
     *
     * @param object $course
     * @param \context_module $context
     * @param object $cm
     * @param object $forum
     * @param object $discussion
     * @param object $post
     */
    public function trigger_post_created($course, \context_module $context, $cm, $forum, $discussion, $post) {
        global $CFG;

        require_once($CFG->libdir.'/completionlib.php');

        // Update completion state
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) &&
            ($forum->completionreplies || $forum->completionposts)
        ) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $params = array(
            'context'  => $context,
            'objectid' => $post->id,
            'other'    => array(
                'discussionid' => $discussion->id,
                'forumid'      => $forum->id,
                'forumtype'    => $forum->type,
            )
        );
        $event = post_created::create($params);
        $event->add_record_snapshot('hsuforum_posts', $post);
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();
    }

    /**
     * Trigger event
     *
     * @param \context_module $context
     * @param object $forum
     * @param object $discussion
     * @param object $post
     */
    public function trigger_post_updated(\context_module $context, $forum, $discussion, $post) {
        global $USER;

        $params = array(
            'context'  => $context,
            'objectid' => $post->id,
            'other'    => array(
                'discussionid' => $discussion->id,
                'forumid'      => $forum->id,
                'forumtype'    => $forum->type,
            )
        );

        if ($post->userid !== $USER->id) {
            $params['relateduserid'] = $post->userid;
        }

        $event = post_updated::create($params);
        $event->add_record_snapshot('hsuforum_discussions', $discussion);
        $event->trigger();
    }

    /**
     * @param array $errors
     * @return json_response
     */
    public function create_error_response(array $errors) {
        global $PAGE;

        /** @var \mod_hsuforum_renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_hsuforum');

        return new json_response((object) array(
            'errors' => true,
            'html'   => $renderer->validation_errors($errors),
        ));
    }
}
