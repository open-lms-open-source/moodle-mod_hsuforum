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
 * Edit Discussion or Post Controller
 *
 * @package    mod
 * @subpackage hsuforum
 * @copyright  Copyright (c) 2012 Blackboard Inc. (http://www.blackboard.com)
 * @author     Mark Nielsen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_hsuforum\controller;

use coding_exception;
use mod_hsuforum\response\json_response;
use mod_hsuforum\service\discussion_service;
use mod_hsuforum\service\form_service;
use mod_hsuforum\service\post_service;
use mod_hsuforum\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/controller_abstract.php');

class edit_controller extends controller_abstract {
    /**
     * @var post_service
     */
    protected $postservice;

    /**
     * @var discussion_service
     */
    protected $discussionservice;

    /**
     * @var form_service
     */
    protected $formservice;

    public function init($action) {
        parent::init($action);

        require_once(dirname(__DIR__).'/response/json_response.php');
        require_once(dirname(__DIR__).'/service/post_service.php');
        require_once(dirname(__DIR__).'/service/discussion_service.php');
        require_once(dirname(__DIR__).'/service/form_service.php');
        require_once(dirname(dirname(__DIR__)).'/lib.php');

        $this->discussionservice = new discussion_service();
        $this->postservice = new post_service($this->discussionservice);
        $this->formservice = new form_service();
    }

    /**
     * Do any security checks needed for the passed action
     *
     * @param string $action
     */
    public function require_capability($action) {
        // Checks are done in actions as they are more complex.
    }

    /**
     * Add a reply to a post
     *
     * Since we are uploading files to this action using
     * YUI, then we cannot natively detect it is an AJAX
     * request because it is going through an iframe.  This
     * allows for uploading of files.
     *
     * We must still ensure a JSON response.
     *
     * @return json_response
     */
    public function reply_action() {
        global $DB, $PAGE;

        try {
            require_sesskey();

            $reply         = required_param('reply', PARAM_INT);
            $subject       = trim(optional_param('subject', '', PARAM_TEXT));
            $privatereply  = optional_param('privatereply', 0, PARAM_BOOL);
            $reveal        = optional_param('reveal', 0, PARAM_BOOL);
            $message       = required_param('message', PARAM_RAW_TRIMMED);
            $messageformat = required_param('messageformat', PARAM_INT);
            $mailnow       = optional_param('mailnow', 0, PARAM_BOOL);

            $forum   = $PAGE->activityrecord;
            $cm      = $PAGE->cm;
            $context = $PAGE->context;
            $course  = $PAGE->course;

            $parent     = $DB->get_record('hsuforum_posts', array('id' => $reply), '*', MUST_EXIST);
            $discussion = $DB->get_record('hsuforum_discussions', array('id' => $parent->discussion, 'forum' => $forum->id), '*', MUST_EXIST);

            // If private reply, then map it to the parent author user ID.
            if (!empty($privatereply)) {
                $privatereply = $parent->userid;
            }
            $data = array(
                'privatereply'  => $privatereply,
                'message'       => $message,
                'messageformat' => $messageformat,
                'reveal'        => $reveal,
                'mailnow'       => $mailnow
            );
            if (!empty($subject)) {
                $data['subject'] = $subject;
            }
            return $this->postservice->handle_reply($course, $cm, $forum, $context, $discussion, $parent, $data);
        } catch (\Exception $e) {
            return new json_response($e);
        }
    }

    /**
     * Add a discussion
     *
     * Since we are uploading files to this action using
     * YUI, then we cannot natively detect it is an AJAX
     * request because it is going through an iframe.  This
     * allows for uploading of files.
     *
     * We must still ensure a JSON response.
     *
     * @return json_response
     */
    public function add_discussion_action() {
        global $PAGE;

        try {
            require_sesskey();

            $subject        = required_param('subject', PARAM_TEXT);
            $groupid        = optional_param('groupinfo', 0, PARAM_INT);
            $message        = required_param('message', PARAM_RAW_TRIMMED);
            $reveal         = optional_param('reveal', 0, PARAM_BOOL);
            $messageformat  = required_param('messageformat', PARAM_INT);
            $posttomygroups = optional_param('posttomygroups', 0, PARAM_BOOL);
            $mailnow        = optional_param('mailnow', 0, PARAM_BOOL);

            list($timestart, $timeend) = local::get_form_discussion_times();

            $forum   = $PAGE->activityrecord;
            $cm      = $PAGE->cm;
            $context = $PAGE->context;
            $course  = $PAGE->course;

            if (empty($groupid)) {
                $groupid = -1;
            }
            $options = array(
                'subject'       => $subject,
                'name'          => $subject,
                'groupid'       => $groupid,
                'message'       => $message,
                'messageformat' => $messageformat,
                'reveal'        => $reveal,
                'timestart'     => $timestart,
                'timeend'       => $timeend,
                'mailnow'       => $mailnow
            );
            return $this->discussionservice->handle_add_discussion($course, $cm, $forum, $context, $options, $posttomygroups);
        } catch (\Exception $e) {
            $retobj = (object) ['errors' => $e];
            return new json_response($retobj);
        }
    }

    /**
     * Update a post (can be of a discussion or reply)
     *
     * @return json_response
     */
    public function update_post_action() {
        global $DB, $PAGE;

        try {
            require_sesskey();

            $postid        = required_param('edit', PARAM_TEXT);
            $subject       = required_param('subject', PARAM_TEXT);
            $groupid       = optional_param('groupinfo', 0, PARAM_INT);
            $itemid        = required_param('itemid', PARAM_INT);
            $files         = optional_param_array('deleteattachment', array(), PARAM_FILE);
            $privatereply  = optional_param('privatereply', 0, PARAM_BOOL);
            $reveal        = optional_param('reveal', 0, PARAM_BOOL);
            $message       = required_param('message', PARAM_RAW_TRIMMED);
            $messageformat = required_param('messageformat', PARAM_INT);

            list($timestart, $timeend) = local::get_form_discussion_times();

            $forum   = $PAGE->activityrecord;
            $cm      = $PAGE->cm;
            $context = $PAGE->context;
            $course  = $PAGE->course;

            $post       = $DB->get_record('hsuforum_posts', array('id' => $postid), '*', MUST_EXIST);
            $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion, 'forum' => $forum->id), '*', MUST_EXIST);

            if (empty($groupid)) {
                $groupid = -1;
            }
            // If private reply, then map it to the parent author user ID.
            if (!empty($privatereply)) {
                $parent     = $DB->get_record('hsuforum_posts', array('id' => $post->parent), '*', MUST_EXIST);
                $privatereply = $parent->userid;
            }

            // Check if discussion has a set timestart and timeend.
            $timestart = $timestart === 0 ? $discussion->timestart : 0;
            $timeend = $timeend === 0 ? $discussion->timeend : 0;

            return $this->postservice->handle_update_post($course, $cm, $forum, $context, $discussion, $post, $files,  array(
                'subject'       => $subject,
                'name'          => $subject,
                'groupid'       => $groupid,
                'itemid'        => $itemid,
                'message'       => $message,
                'messageformat' => $messageformat,
                'reveal'        => $reveal,
                'privatereply'  => $privatereply,
                'timestart'     => $timestart,
                'timeend'       => $timeend
            ));
        } catch (\Exception $e) {
            return new json_response($e);
        }
    }

    /**
     * Get the edit post form HTML
     *
     * @return json_response
     */
    public function edit_post_form_action() {
        global $DB, $PAGE;

        $postid = required_param('postid', PARAM_INT);

        if (!$post = hsuforum_get_post_full($postid)) {
            print_error('invalidpostid', 'hsuforum');
        }
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);

        $this->postservice->require_can_edit_post(
            $PAGE->activityrecord, $PAGE->context, $discussion, $post
        );

        $draftid = required_param('draftid', PARAM_INT);

        if (!empty($post->parent)) {
            $html = $this->formservice->edit_post_form($PAGE->cm, $post, $draftid);
            return new json_response(['html' => $html]);
        } else {
            $html = $this->formservice->edit_discussion_form($PAGE->cm, $discussion, $post, $draftid);

            $timezone = new \DateTimeZone(date_default_timezone_get());
            $datetime = new \DateTime("now", $timezone);
            $offset = $timezone->getOffset($datetime);

            return new json_response([
                'html'         => $html,
                'isdiscussion' => true,
                'timestart'    => $discussion->timestart,
                'timeend'      => $discussion->timeend,
                'offset'       => $offset
            ]);
        }
    }

    /**
     * @return json_response
     * @throws \coding_exception
     */
    public function delete_post_action() {
        global $USER, $DB, $PAGE;

        if (!AJAX_SCRIPT) {
            throw new coding_exception('This is an AJAX action and you cannot access it directly');
        }
        require_sesskey();

        $postid = required_param('postid', PARAM_INT);

        $post       = $DB->get_record('hsuforum_posts', array('id' => $postid), '*', MUST_EXIST);
        $discussion = $DB->get_record('hsuforum_discussions', array('id' => $post->discussion), '*', MUST_EXIST);

        $candeleteown = ($post->userid == $USER->id && has_capability('mod/hsuforum:deleteownpost', $PAGE->context));

        if (!($candeleteown || has_capability('mod/hsuforum:deleteanypost', $PAGE->context))) {
            print_error('cannotdeletepost', 'hsuforum');
        }

        $redirect = hsuforum_verify_and_delete_post($PAGE->course, $PAGE->cm,
            $PAGE->activityrecord, $PAGE->context, $discussion, $post);

        $html = '';
        if ($discussion->firstpost != $post->id) {
            $html    = $this->discussionservice->render_full_thread($discussion->id);
            $message = get_string('postdeleted', 'hsuforum');
        } else {
            $message = get_string('deleteddiscussion', 'hsuforum');
        }
        /** @var \core_renderer $renderer */
        $renderer = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        return new json_response(array(
            'redirecturl'      => $redirect,
            'html'             => $html,
            'postid'           => $post->id,
            'livelog'          => $message,
            'notificationhtml' => $renderer->notification($message, 'success'),
            'discussionid'     => $discussion->id,
        ));
    }
}
